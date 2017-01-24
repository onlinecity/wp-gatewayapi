jQuery(function($) {

    function initialize()
    {
        handleUpdateSmsCounter();
        handleShowTags();
    }

    function handleUpdateSmsCounter()
    {
        var counterEl = $('.gwapi-counter');
        if (!counterEl.length) return;

        var textarea = counterEl.parent().find('textarea');
        var i18n = counterEl.data('i18n');

        var updateFn = function() {
            var chars = textarea.val().length;
            var regex_matches = textarea.val().match(/[\^{}\\~€|\[\]]/gm) || [];
            chars += regex_matches.length;
            var split = chars <= 160 ? 160 : 153;

            var smses = Math.ceil(chars / split);
            if (smses == 0) smses = 1;

            var charsText = chars == 1 ? i18n.character : i18n.characters;
            var smsText = smses == 1 ? i18n.sms : i18n.smses;

            counterEl.text(chars + ' '+charsText+' (' + smses + ' '+smsText+')');
        };

        textarea.keyup(updateFn).change(updateFn);
        updateFn();
    }

    function handleShowTags()
    {
        var tagsEl = $('.gwapi-tags');
        if (!tagsEl.length) return;

        tagsEl.find('a').click(function(ev) {
            ev.preventDefault();

            var popup = open("", "gwapi_tags", "width=600,height=300,menubar=0,resizable=1,scrollbars=1,status=0,toolbar=0");
            var body = $(popup.document.body);
            body.css({
                'font-family': 'sans-serif',
                'font-size': '14px'
            }).empty();

            var table = $('<table border="1" cellpadding="5" cellspacing=0 align="center">').appendTo(body);

            var tags = $(this).data('tags');
            $.each(tags, function(tag, text) {
                var tr = $('<tr>');
                tr.append($('<th>', {width: '33%', 'style': 'font-family: monospace; text-align: left;'}).text(tag));
                tr.append($('<td>', {width: '66%' }).text(text))
                table.append(tr);
            });
        });
    }

    initialize();

});