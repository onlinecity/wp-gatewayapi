/**
 * GatewayAPI Plugin for Mobile CC fields.
 *
 * Initialized by $(element).gwapiMobileCc()
 * Takes arguments as either data- options or as an option-object. Options supplied at initialization takes precedence
 * over the data-options.
 *
 * Options-object key / data-option supported:
 * - only_ccs / data-only-ccs: Array or comma separated string of country codes which may be possible to select.
 * - default_cc / data-default-cc: A single country code which should be default selected when no <option selected> is presented in the base HTML select-widget.
 */
(function ($) {
    var countriesRes;
    $.fn.gwapiMobileCc = function (options) {
        $(this).each(function () {
            var field = $(this);

            // load options from element
            var el_options = {
                only_ccs: field.data('only-ccs'),
                default_cc: field.data('default-cc')
            };

            // override with options from the options-object in the argument
            $.extend(el_options, options);

            // cleanup
            options = el_options;

            // always invert the "only ccs" list
            if (options.only_ccs) {
                if (typeof options.only_ccs == 'string') {
                    options.only_ccs = options.only_ccs.split(/,/g);
                    for(var i=0; i<options.only_ccs.length; i++) {
                        options.only_ccs[i] = $.trim(options.only_ccs[i]);
                    }
                }
                if (typeof options.only_ccs != 'object') options.only_ccs = [options.only_ccs];
                options.only_ccs = _.invert(options.only_ccs);
            }

            // fetch countries list
            if (!countriesRes) countriesRes = $.get(GWAPI_PLUGINDIR + 'lib/countries/countries.min.json');

            countriesRes.done(function (countries) {

                var cur_value = field.val();
                field.empty();

                // create opt-groups
                var optgroups = {};
                _.each(countries.continents, function (name, cont_c) {
                    optgroups[cont_c] = $('<optgroup>').attr('label', name);
                });

                // add countries
                var countriesOrdered = _.sortBy(countries.countries, 'name');
                _.each(countriesOrdered, function (country) {
                    var optgroup = optgroups[country.continent];

                    if (!country.phone) return;
                    var ccs = country.phone.split(/,/g);
                    _.each(ccs, function (cc) {
                        // skip countries not in list, if a list is presented
                        if (options.only_ccs && typeof options.only_ccs[cc] == 'undefined') return;

                        var el = $('<option>').val(country.phone).text(country.name + ' (+' + cc + ')');
                        optgroup.append(el);
                    });
                });

                _.each(optgroups, function (optgroup) {
                    field.append(optgroup);
                });

                // any empty optgroups? delete
                field.find('optgroup').each(function() {
                   if (!$(this).children().length) $(this).remove();
                });

                // try set the same value again - otherwise, default to Denmark
                if (cur_value) field.val(Number(cur_value));
                else field.val(options.default_cc || 45);

                field.select2({
                    width: '100%'
                });

            });
        });

    };
})(jQuery);


/**
 * Auto-render widgets.
 */
jQuery(function($) {
    $('body').find('select[data-gwapi-mobile-cc]').each(function() {
        $(this).gwapiMobileCc();
    });
});