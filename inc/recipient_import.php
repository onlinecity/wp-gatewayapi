<?php

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=gwapi-sms', __('Import recipients from spreadsheet', 'gwapi'), __('Import recipients', 'gwapi'), 'edit_posts', 'gwapi_import', function () {
        require_once(__DIR__ . "/../tpl/import.php");
    });
}, 20);

add_action('wp_ajax_gwapi_import', function () {
    header('Content-type: application/json');

    $data = get_transient('gwapi_import_' . get_current_user_id());
    $rows = array_slice(explode("\n", $data), (int)$_POST['page'] * (int)$_POST['per_page'] + 1, (int)$_POST['per_page']);

    $failed = 0;
    $new = 0;
    $updated = 0;

    foreach ($rows as $row) {
        $cols = explode("\t", $row);

        $cc = preg_replace('/\D+/','', trim($cols[$_POST['columns']['cc']]));
        $number = preg_replace('/\D+/','', trim($cols[$_POST['columns']['number']]));
        if (!$cc || !$number) {
            $failed++;
            continue;
        }

        // find out if post exists
        $q = new WP_Query([
            "post_type" => "gwapi-recipient",
            "meta_query" => [
                [
                    'key' => 'cc',
                    'value' => $cc
                ],
                [
                    'key' => 'number',
                    'value' => $number
                ]
            ]
        ]);

        $ID = null;
        if ($q->have_posts()) {
            // update
            $ID = $q->post->ID;
            $updated++;
        } else {
            $new++;
        }

        // create the recipient
        $newID = wp_insert_post([
            "ID" => $ID,
            "post_title" => isset($cols[$_POST['columns']['name']]) ? $cols[$_POST['columns']['name']] : null,
            "post_type" => "gwapi-recipient",
            "post_status" => "publish"
        ]);
        $ID = $newID ?: $ID;

        // recipient groups
        $groups = isset($_POST['gwapi-recipient-groups']) ? $_POST['gwapi-recipient-groups'] : [];
        foreach($groups as &$g) {
            $g = (int)$g;
        }
        if ($groups) wp_set_object_terms($ID, $groups, 'gwapi-recipient-groups', true);


        foreach($_POST['columns'] as $key=>$idx) {
            if (!strlen($idx)) continue;
            if ($key == 'name') continue;
            update_post_meta($ID, $key, $cols[$idx]);
        }
    }

    echo json_encode(['failed' => $failed, 'new' => $new, 'updated' => $updated]);

    exit;
});