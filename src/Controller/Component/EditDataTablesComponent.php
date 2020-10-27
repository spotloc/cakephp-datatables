<?php
/*
 * Copyright (c) Spotloc 2020. Tous droits réservés.
 */

namespace DataTables\Controller\Component;

use Cake\Controller\Component;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Composant d'aide pour le plugin DataTables.
 *
 *
 */
class EditDataTablesComponent extends Component
{

    /**
     * Retourne un tableau utilisable dans une "where" clause
     *
     * @param  string  $searchText  Le texte recherché
     * @param  array  $searchedProperties  Le tableau des propriétés sur
     *     lesquelles rechercher
     */
    private function makeSearchRequest($searchText, $searchedProperties)
    {
        $searched = [];

        if ($searchText) {
            $searched = $this->addToArray($searchText, $searchedProperties);
        }

        return $searched;
    }

    /**
     * @param  string  $dataTableName  Nom de la table d'entité à modifier
     * @param  \Cake\Http\ServerRequest  $request  La requête issue de la
     *     dataTable
     * @param  array  $options  Optionds de la requete
     *
     * @return array Le résultat de la modification formattée pour DataTables
     */
    public function updateEntity($dataTableName, $request, $options)
    {
        $dataTable = TableRegistry::getTableLocator()->get($dataTableName);
        $postData = $request->getData('data');

        $action = $request->getData('action');

        $entityValues = [];
        foreach ($postData as $data) {
            foreach ($data as $name => $value) {
                $entityValues[$name] = $value;
            }

        }

        if ($action == 'create') {
            try {

                $entity = $dataTable->newEntity([]);

            } catch (Exception $e) {
                Log::error($e);
                return [];
            }

        } else {
            if ($action == 'edit') {
                $itemId = array_keys($postData)[0];
                $entity = $dataTable->get($itemId, $options);

            }
        }

        if (isset($entity)) {
            $errorsArray = ["fieldErrors" => []];

            $entity = $dataTable->patchEntity($entity, $entityValues, $options);
            try {
                $res = $dataTable->save($entity);
            } catch (Exception $e) {
                $errorsArray["error"] = __d('cake', $e->getMessage());
                return $errorsArray;
            }

            if ($res) {
                return [
                    "data" => array_merge(["DT_RowId" => $entity->id],
                        $entity->toArray()),
                ];
            } else {
                foreach ($entity->getErrors() as $e => $errors) {
                    foreach ($errors as $m => $err) {
                        if (is_array($err)) {
                            foreach ($err as $k => $v) {
                                $errorsArray["fieldErrors"][] = [
                                    "name" => $e.'.'.$m, "status" => $v,
                                ];
                            }
                        } else {
                            $errorsArray["fieldErrors"][] = [
                                "name" => $e, "status" => $err,
                            ];
                        }
                    }
                }

                Log::debug("Erreur lors de la mise à jour de l'entité",
                    $errorsArray);
                return $errorsArray;
            }
        }
        return null;
    }

    /**
     * Efface une entité.
     *
     * @param  string  $dataTableName  Le nom de la table
     * @param  object  $request  Les paramètres de requete contenant l'id
     *
     * @return array L'entité effacée.
     */
    public function deleteEntity($dataTableName, $request)
    {
        $dataTable = TableRegistry::getTableLocator()->get($dataTableName);
        $postData = $request->getData('data');
        $itemId = array_keys($postData)[0];
        $entity = $dataTable->get($itemId);

        if ($entity) {
            $entity = $dataTable->delete($entity);
            if ($entity) {
                return ["data" => []];
            }
        }
    }

    /**
     * Renvoie un tableau de résultats formattés pour le plugin DataTable.
     *
     * @param  array  $dataTable  La table de données sur laquelle rechercher
     * @param  object  $request  La requête issue de la dataTable
     * @param  array  $searchProperties  Les propriétés du model sur lesquelles
     *     rechercher.
     *
     * @return array Les résultats de recherche
     *
     */
    public function searchEntities(
        $dataTableName,
        $request,
        $searchProperties,
        $contains = []
    ) {
        $dataTable = TableRegistry::getTableLocator()->get($dataTableName);
        $order = $request->getQuery('order');

        $columns = $request->getQuery('columns');
        $searchParam = $request->getQuery('search');
        $columnNameForOrdering = str_replace($dataTable->getRegistryAlias()
            ."__", "",
            $columns[$order[0]['column']]['data']);

        $searched = $this->makeSearchRequest($searchParam['value'],
            $searchProperties);

        $totalCount
            = $recordsFiltered = $recordsFiltered = $dataTable->find()->count();


        $clientsArray = $dataTable->find()
            ->limit($request->getQuery('length'))
            ->offset($request->getQuery('start'))
            ->order([$columnNameForOrdering => $order[0]['dir']])
            ->contain($contains)
            ->where($searched);
        //->execute();
        if ($searchParam['value']) {
            $recordsFiltered = $dataTable->find()
                ->where($searched)
                ->count();
        }

        $response = [
            "data"            => $clientsArray->fetchAll('assoc'),
            "recordsTotal"    => $totalCount,
            "recordsFiltered" => $recordsFiltered,
        ];

        return $response;
    }

    /**
     * Ajoute des paramètres au tableau de requête.
     *
     * @param  array  $searchText  Le tableau de recherche
     * @param  array  $dt  Le tableau des textes à rechercher
     *
     * @return array Le tableau de requête.
     */
    private function addToArray($searchText, &$dt)
    {
        static $searched = [];
        while (count($dt)) {
            $lastArrayValue = array_pop($dt);
            $searched = [
                "OR" => array_merge($searched, [
                    $lastArrayValue.' LIKE' => "%".strval($searchText)."%",
                ]),
            ];

            $searched = $this->addToArray($searchText, $dt);
        }
        return $searched;
    }

}