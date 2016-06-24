jQuery(function($) {

    var outer = $('#importingStatus');
    var per_load = 100;
    var page = 1;
    var row_count = Number(outer.find('.total').text());

    var count_failed = 0;
    var count_new = 0;
    var count_updated = 0;

    function initialize()
    {
        importNext();
    }

    function importNext()
    {
        $.post(window.ajaxurl+'?action=gwapi_import', {
            'columns': import_columns,
            'gwapi-recipient-groups': gwapi_recipient_groups,
            'page': page-1,
            'per_page': per_load
        }).success(function(res) {
            updateStatus(res);

            if (page++*per_load > row_count) return importDone();
            importNext();
        }).fail(function() {
           window.alert("An error occured. The import has failed.");
        });
    }

    function updateStatus(res)
    {
        outer.find('.processed').text(page*per_load < row_count ? page*per_load : row_count);

        count_failed += res.failed;
        outer.find('.invalid_rows').text(count_failed);

        count_new += res.new;
        outer.find('.count_new').text(count_new);

        count_updated += res.updated;
        outer.find('.count_updated').text(count_updated);
    }

    function importDone()
    {
        outer.find('.status_text').css({'color':'green'}).text('Import complete.');
    }

    initialize();

});