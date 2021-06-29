jQuery(function($) {
    var i18n = GWAPI_PROFILE_I18N;
    var msisdn_el = null;

    function initialize()
    {
        moveMobileIntoOwnSection();
        if (!msisdn_el || !msisdn_el.length) return;

        setProperValue();

        addChangeMobileButton();
    }

    function moveMobileIntoOwnSection()
    {
        // current MSISDN field
        var msisdn_tr = $('.user-gwapi_msisdn-wrap');

        // add a new section after the current table
        var section = $('<div>').insertAfter(msisdn_tr.closest('table'));
        section.append($('<h2>').text(i18n.twofac_section_h1));
        section.append($('<p class="description">').text(i18n.twofac_section_intro))

        // create a table in the new section, and move the msisdn table row
        var table = $('<table class="form-table"><tbody></tbody></table>').appendTo(section);
        table.find('tbody').append(msisdn_tr);

        // make the field read-only
        msisdn_el = msisdn_tr.find('input').prop('readonly', true).addClass('regular-text ltr');
    }

    function setProperValue()
    {
        msisdn_el.val( GWAPI_PROFILE_I18N.twofac_msisdn );
    }

    function addChangeMobileButton()
    {
        var has_number = msisdn_el.val();
        var title = has_number ? i18n.twofac_update_number : i18n.twofac_add_number;
        var btn = $('<a href="'+i18n.twofac_link+'" class="button button-secondary">').text( title );
        btn.insertAfter(msisdn_el);
        msisdn_el.css('margin-right', '8px');
    }


    initialize();
});