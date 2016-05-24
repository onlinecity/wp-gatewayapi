<?php

/**
 * Returns a list of all found and valid tags.
 */
function _gwapi_extract_tags_from_message($message) {

    $simple_search_for = [ '%NAME%' ];

    $found_tags = [];
    foreach($simple_search_for as $ssf) {
        if (strpos($message, $ssf) !== false) {
            $found_tags[] = $ssf;
        }
    }

    $found_tags = apply_filters('gwapi_extract_tags_from_message', $found_tags, $message);

    return $found_tags;
}