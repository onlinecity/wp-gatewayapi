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

    if (!$number || !$cc) return new WP_Error(__('Missing/invalid country code or number.', 'gwapi'));

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
        return new WP_Error(__('Not found in database.', 'gwapi'));
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
        if (!isset($token)) {
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
function bit_admin_add_search_column($post_type, $meta_key)
{
    if (!is_admin()) return;
    global $pagenow;
    if ($pagenow != 'edit.php') return;
    if (!isset($_GET['post_type']) || $_GET['post_type'] != $post_type) return;
    if (!isset($_GET['s']) || !$_GET['s']) return;

    static $col_count = 0;
    $meta_name = "bit_search_".$col_count++;

    add_filter( 'posts_join', function($join, $searchObj) use($meta_name, $meta_key) {
        global $wpdb; /** @var $wpdb wpdb */
        if ( ! $searchObj->is_search ) return $join;

        $compare = strpos($meta_key, '%') !== false ? 'LIKE' : '=';

        $join .= " LEFT JOIN $wpdb->postmeta $meta_name ON ($wpdb->posts.ID = $meta_name.post_id AND {$meta_name}.meta_key $compare '".$meta_key."')";

        return $join;
    }, 10, 2);

    add_filter( 'posts_where', function($where, $searchObj) use($meta_name, $meta_key, $col_count) {
        global $wp;
        if ( ! $searchObj->is_search ) return $where;

        $before = substr($where, 0, 7);
        $in = "({$meta_name}.meta_value LIKE '%{$wp->query_vars['s']}%' ) OR ";
        $after = substr($where, 7);

        return $before.$in.$after;
    }, 10, 2);
}

