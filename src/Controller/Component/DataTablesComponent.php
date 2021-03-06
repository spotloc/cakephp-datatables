<?php
namespace DataTables\Controller\Component;

use Cake\Controller\Component;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * DataTables component
 */
class DataTablesComponent extends Component
{

    protected $_defaultConfig = [
        'start' => 0,
        'length' => 10,
        'order' => [],
        'prefixSearch' => true, // use "LIKE …%" instead of "LIKE %…%" conditions
        'conditionsOr' => [],  // table-wide search conditions
        'conditionsAnd' => [], // column search conditions
        'matching' => [],      // column search conditions for foreign tables
    ];

    protected $_viewVars = [
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => 0
    ];

    protected $_tableName = null;

    protected $_plugin = null;

    /**
     * Process draw option (pass-through)
     */
    private function _draw()
    {
        if (empty($this->request->query['draw']))
            return;

        $this->_viewVars['draw'] = (int)$this->request->query['draw'];
    }

    /**
     * Process query data of ajax request regarding order
     * Alters $options if delegateOrder is set
     * In this case, the model needs to handle the 'customOrder' option.
     * @param $options: Query options from the request
     */
    private function _order(array &$options)
    {
        if (empty($this->request->query['order']))
            return;

        // -- add custom order
        $order = $this->getConfig('order');
        foreach($this->request->query['order'] as $item) {
            $order[$this->request->query['columns'][$item['column']]['name']] = $item['dir'];
        }
        if (!empty($options['delegateOrder'])) {
            $options['customOrder'] = $order;
        } else {
            $this->getConfig('order', $order);
        }

        // -- remove default ordering as we have a custom one
        unset($options['order']);
    }

    /**
     * Process query data of ajax request regarding filtering
     * Alters $options if delegateSearch is set
     * In this case, the model needs to handle the 'globalSearch' option.
     * @param $options: Query options from the request
     * @return: returns true if additional filtering takes place
     */
    private function _filter(array &$options)
    {
        // -- add limit
        if (!empty($this->request->query['length'])) {
            $this->getConfig('length', $this->request->query['length']);
        }

        // -- add offset
        if (!empty($this->request->query['start'])) {
            $this->getConfig('start', (int)$this->request->query['start']);
        }

        // -- don't support any search if columns data missing
        if (empty($this->request->query['columns']))
            return false;

        // -- check table search field
        $globalSearch = isset($this->request->query['search']['value']) ? $this->request->query['search']['value'] : false;
        if ($globalSearch && !empty($options['delegateSearch'])) {
            $options['globalSearch'] = $globalSearch;
            return true; // TODO: support for deferred local search
        }

        // -- add conditions for both table-wide and column search fields
        $filters = false;
        foreach ($this->request->query['columns'] as $column) {
            if ($globalSearch && $column['searchable'] == 'true') {
                $this->_addCondition($column['name'], $globalSearch, 'or');
                $filters = true;
            }
            $localSearch = $column['search']['value'];
            if (strlen($localSearch)) {
                $this->_addCondition($column['name'], $column['search']['value']);
                $filters = true;
            }
        }
        return $filters;
    }

    /**
     * Find data
     *
     * @param $tableName
     * @param $finder
     * @param array $options
     * @return array|\Cake\ORM\Query
     */
    public function find($tableName, $finder = 'all', array $options = [])
    {
        $delegateSearch = !empty($options['delegateSearch']);

        // -- get table object
        $table = TableRegistry::getTableLocator()->get($tableName);
        $this->_tableName = $table->getAlias();

        // -- process draw & ordering options
        $this->_draw();
        $this->_order($options);

        // -- call table's finder w/o filters
        $data = $table->find($finder, $options);

         foreach ($this->getConfig('matching') as $association => $where) {
            $data->matching($association, function ($q) use ($where) {
                return $q->where($where);
            });
        }

        // -- retrieve total count
        $this->_viewVars['recordsTotal'] = $data->where($this->getConfig('conditionsAnd'))->count();

        // -- process filter options
        $filters = $this->_filter($options);

        // -- apply filters
        if ($filters) {
            if ($delegateSearch) {
                // call finder again to process filters (provided in $options)
                $data = $table->find($finder, $options);
            } else {
                $data->where($this->getConfig('conditionsAnd'));

                if (!empty($this->getConfig('conditionsOr'))) {
                    $data->where(['or' => $this->getConfig('conditionsOr')]);
                }
            }
        } else {
            $data->where($this->getConfig('conditionsAnd'));
        }



        // -- retrieve filtered count
        $this->_viewVars['recordsFiltered'] = $data->count();

        // -- add limit
        if ($this->getConfig('length') > 0) { // dt might provide -1
            $data->limit($this->getConfig('length'));
            $data->offset($this->getConfig('start'));
        }

        // -- sort
        $data->order($this->getConfig('order'));

        // -- set all view vars to view and serialize array
        $this->_setViewVars();
        return $data;

    }

    private function _setViewVars()
    {
        $controller = $this->_registry->getController();

        $_serialize = $controller->viewBuilder()->getVar('_serialize') ? $controller->viewBuilder()->getVar('_serialize') : [];
        $_serialize = array_merge($_serialize, array_keys($this->_viewVars));

        $controller->set($this->_viewVars);
        $controller->set('_serialize', $_serialize);
    }

    private function _addCondition($column, $value, $type = 'and')
    {
        $table = TableRegistry::getTableLocator()->get($this->_tableName);

        $hasTranslate = $table->behaviors()->has('Translate');
        $right = $this->getConfig('prefixSearch') ? "{$value}%" : "%{$value}%";

        if($hasTranslate) {
            $s = explode(".",$column);
            $simpleColumn = end($s);
            $condition = [$table->translationField($simpleColumn) . " LIKE '$right'"];
        } else {
            $condition = ["{$column} LIKE  '$right'"];
        }

        if ($type === 'or') {
            $this->getConfig('conditionsOr', $condition); // merges
            return;
        }

        list($association, $field) = explode('.', $column);
        if ($this->_tableName == $association) {
            $this->getConfig('conditionsAnd', $condition); // merges
        } else {
            $this->getConfig('matching', [$association => $condition]); // merges

        }
    }
}
