<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Returns a list of all found and valid tags.
 */
function _gwapi_extract_tags_from_message($message)
{
    $simple_search_for = array_keys(gwapi_all_tags());

    $found_tags = [];
    foreach ($simple_search_for as $ssf) {
        if (strpos($message, $ssf) !== false) {
            $found_tags[] = $ssf;
        }
    }


    return $found_tags;
}

function _gwapi_get_recipient($cc, $number)
{
    $number = preg_replace('/\D+/', '', $number);
    $cc = preg_replace('/\D+/', '', $cc);

    if (!$number || !$cc) return new WP_Error(__('Missing/invalid country code or number.', 'gatewayapi'));

    $q = new WP_Query([
        "post_type" => "gwapi-recipient",
        "meta_query" => [
            [
                'key' => 'number',
                'value' => $number
            ],
            [
                'key' => 'cc',
                'value' => $cc
            ]
        ]
    ]);
    if (!$q->have_posts()) {
        return new WP_Error(__('Not found in database.', 'gatewayapi'));
    }
    return $q->post;
}

/**
 * Returns an array of all possible tags and a pretty description. Tag is the key and description the value.
 */
function gwapi_all_tags()
{
    $tags = [];
    $fields = get_option('gwapi_recipient_fields');
    foreach ($fields as $field) {
        $tags['%' . $field['field_id'] . '%'] = trim($field['name'] . ": " . $field['description'], ':. ') . '.';
    }

    if (!isset($tags['%NAME%'])) {
        $tags['%NAME%'] = __('Name. Name of the recipient.');
    }

    return $tags;
}


function gwapi_get_tag_specification($tag)
{
    static $tag_defs;
    if (!isset($tag_defs) || !$tag_defs) $tag_defs = [];
    if (isset($tag_defs[$tag])) return $tag_defs[$tag];

    $fields = get_option('gwapi_recipient_fields');
    foreach ($fields as $field) {
        if ('%' . strtoupper($field['field_id']) . '%' == $tag) {
            $tag_defs[$tag] = $field;
            return $field;
        }
    }

    return false;
}

/**
 * Get receive-sms token if enabled
 * @return string
 */
function _gwapi_receive_sms_token()
{
    if (get_option('gwapi_receive_sms_enable')) {
        $key = 'gwapi_receive_sms_token';
        $token = get_option($key);
        if (!$token) {
            $token = wp_generate_password(32, false);
            update_option($key, $token, false);
        }
        return $token;
    }
}

function _gwapi_receive_sms_url()
{
    if (get_option('gwapi_receive_sms_enable')) {
        return admin_url('admin-ajax.php?action=gwapi_receive_sms&token=' . _gwapi_receive_sms_token());
    }
}

/**
 * Make this extra column searchable as part of the admin searching.
 *
 * Note: It is possible to do wildcard searching in meta_key as well - if including a % anywhere in the meta_key, then
 * the query will use LIKE for this part as well.
 *
 * @param $post_type
 * @param $meta_key
 */
if (!function_exists('bit_admin_add_search_column')) {
    function bit_admin_add_search_column($post_type, $meta_key)
    {
        if (!is_admin()) return;
        global $pagenow;
        if ($pagenow != 'edit.php') return;
        if (!isset($_GET['post_type']) || $_GET['post_type'] != $post_type) return;
        if (!isset($_GET['s']) || !$_GET['s']) return;

        static $col_count = 0;
        $meta_name = "bit_search_" . $col_count++;

        add_filter('posts_join', function ($join, $searchObj) use ($meta_name, $meta_key) {
            global $wpdb;
            /** @var $wpdb wpdb */
            if (!$searchObj->is_search) return $join;

            $compare = strpos($meta_key, '%') !== false ? 'LIKE' : '=';

            $join .= " LEFT JOIN $wpdb->postmeta $meta_name ON ($wpdb->posts.ID = $meta_name.post_id AND {$meta_name}.meta_key $compare '" . $meta_key . "')";

            return $join;
        }, 10, 2);

        add_filter('posts_where', function ($where, $searchObj) use ($meta_name, $meta_key, $col_count) {
            global $wp;
            if (!$searchObj->is_search) return $where;

            $before = substr($where, 0, 7);
            $in = "({$meta_name}.meta_value LIKE '%{$wp->query_vars['s']}%' ) OR ";
            $after = substr($where, 7);

            return $before . $in . $after;
        }, 10, 2);
    }
}

function gwapi_get_msisdn($cc, $number)
{
    $phone = preg_replace('/\D+/', '', $cc . ltrim($number, '0'));
    return $phone;
}


if (!function_exists('bit_register_cpt_status')) {

    class _BitCptStatus
    {
        public static $cpt_status = [];
        public static $status_names = [];
    }

    /**
     * Register a new post status and use it for a specific custom post type.
     *
     * @param $status name of the status
     * @param $cpt the custom post type which this status is for
     * @param $name pretty name of the status
     * @param bool $public are posts public with this status?
     */
    function bit_register_cpt_status($status, $cpt, $name, $public = false, $admin_all = null)
    {
        if (is_null($admin_all)) $admin_all = $public;

        $lbl = $name . ' <span class="count">(%s)</span>';
        if (!isset(_BitCptStatus::$status_names[$status])) {
            register_post_status($status, array(
                'label' => $name,
                'public' => $public,
                'show_in_admin_all_list' => $admin_all,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop($lbl, $lbl)
            ));
        }
        _BitCptStatus::$status_names[$status] = $name;
        _BitCptStatus::$cpt_status[$cpt][] = $status;
    }

    add_action('admin_footer-post.php', function () {
        global $post;
        if (in_array($post->post_type, array_keys(_BitCptStatus::$cpt_status))) {

            foreach (_BitCptStatus::$cpt_status[$post->post_type] as $status) {
                $complete = $post->post_status == $status;
                ?>
                <script>
                    jQuery(document).ready(function ($) {
                        $("select#post_status").append('<option value="<?= $status ?>" <?= $complete ? 'selected="selected"' : ''; ?>><?= _BitCptStatus::$status_names[$status]; ?></option>');

                        <?php if ($complete): ?>
                        $("#post-status-display").text(<?= json_encode(_BitCptStatus::$status_names[$status]); ?>);
                        <?php endif; ?>
                    });
                </script>
                <?php
            }
        }
    });

    add_action('admin_footer-edit.php', function () {
        global $post;
        if ($post && in_array($post->post_type, array_keys(_BitCptStatus::$cpt_status))) {
            foreach (_BitCptStatus::$cpt_status[$post->post_type] as $status) {
                ?>
                <script>
                    jQuery(document).ready(function ($) {
                        $('select[name="_status"]').append('<option value="<?= $status; ?>"><?= _BitCptStatus::$status_names[$status]; ?></option>');
                    });
                </script>
                <?php
            }
        }
    });
};

if (!function_exists('bit_add_taxonomy_filter_to_cpt')) {
    function bit_add_taxonomy_filter_to_cpt($cpt, $taxonomy)
    {
        /**
         * Display a custom taxonomy dropdown in admin
         * @author Mike Hemberger
         * @link http://thestizmedia.com/custom-post-type-filter-admin-custom-taxonomy/
         */
        add_action('restrict_manage_posts', function () use ($cpt, $taxonomy) {
            global $typenow;
            if ($typenow == $cpt) {
                $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
                $info_taxonomy = get_taxonomy($taxonomy);
                wp_dropdown_categories(array(
                    'show_option_all' => __("Show All {$info_taxonomy->label}", 'gatewayapi'),
                    'taxonomy' => $taxonomy,
                    'name' => $taxonomy,
                    'orderby' => 'name',
                    'selected' => $selected,
                    'show_count' => true,
                    'hide_empty' => true,
                ));
            };
        });

        /**
         * Filter posts by taxonomy in admin
         * @author  Mike Hemberger
         * @link http://thestizmedia.com/custom-post-type-filter-admin-custom-taxonomy/
         */
        add_filter('parse_query', function ($query) use ($cpt, $taxonomy) {
            global $pagenow;
            $q_vars = &$query->query_vars;
            if ($pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $cpt && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0) {
                $term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
                $q_vars[$taxonomy] = $term->slug;
            }
        });
    }
}

/**
 * Helper function for including a template
 *
 * @param $template
 *
 * @param  mixed  ...$args
 *
 * @return null
 */
function _gwapi_render_template($template, ...$args) {

  // Split args and set dynamic variable based on key=>value of the args array
  foreach ($args as $arg) {
    $variable_name = key($arg);
    ${$variable_name} = $arg[$variable_name];
  }

  $template_file = _gwapi_dir() . '/tpl/' . $template . '.php';

  if (file_exists($template_file)) {
      include _gwapi_dir() . '/tpl/' . $template . '.php';
  }

  return null;
}
