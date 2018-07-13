<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>

<div class="wrap">
    <form method="post">
        <h1><?= __('Import recipients', 'gatewayapi'); ?></h1>

        <?php
        switch(isset($_POST['step']) ? $_POST['step'] : 1) {
            case 1:
                include _gwapi_dir().'/tpl/import-data.php';
                break;

            case 2:
                include _gwapi_dir().'/tpl/import-analysis.php';
                break;

            case 3:
                include _gwapi_dir().'/tpl/import-do.php';
                break;
        }
        ?>
    </form>
</div>