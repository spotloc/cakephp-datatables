/**
 * Table instance
 *
 */
var table = null;

/**
 * Timer instance
 *
 */
var oFilterTimerId = null;

/**
 * Add search behavior to all search fields in column footer
 */
function initColumnSearch()
{
    table.api().columns().every( function () {
        var index = this.index();
        $('input, select', this.footer()).on('keyup change', function () {
            // -- set search
            table.api().column(index).search( this.value );
            window.clearTimeout(oFilterTimerId);
            oFilterTimerId = window.setTimeout(drawTable , params.delay);
        });
    });
};

/**
 * Function reset
 *
 */
function reset()
{
    table.api().columns().every(function() {
        this.search('');
        $('input, select', this.footer()).val('');
        drawTable();
    });
}

/**
 * Draw table again after changes
 *
 */
function drawTable() {
    table.api().draw();
}