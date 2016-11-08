<?php

/**
 * Returns a list of all found and valid tags.
 */
function _gwapi_extract_tags_from_message($message) {
    $simple_search_for = array_keys(gwapi_all_tags());

    $found_tags = [];
    foreach($simple_search_for as $ssf) {
        if (strpos($message, $ssf) !== false) {
            $found_tags[] = $ssf;
        }
    }


    return $found_tags;
}

function _gwapi_get_recipient($cc, $number)
{
    $number = preg_replace('/\D+/','', $number);
    $cc = preg_replace('/\D+/','', $cc);

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
    foreach($fields as $field) {
        $tags['%'.$field['field_id'].'%'] = trim($field['name'].": ".$field['description'], ':. ').'.';
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
    foreach($fields as $field) {
        if ('%'.strtoupper($field['field_id']).'%' == $tag) {
            $tag_defs[$tag] = $field;
            return $field;
        }
    }

    return false;
}

function gwapi_get_msisdn($cc, $number) {
    $phone = preg_replace('/\D+/', '', $cc.ltrim($number, '0'));
    return $phone;
}