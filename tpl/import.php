<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>

<div class="wrap">
  <form method="post">
    <h1><?php _e('Import recipients', 'gatewayapi'); ?></h1>

    <?php
    switch (sanitize_key($_POST['step']) ?? 1) {
      case 1:
        include gatewayapi__dir() . '/tpl/import-data.php';
        break;

      case 2:
        include gatewayapi__dir() . '/tpl/import-analysis.php';
        break;

      case 3:
        include gatewayapi__dir() . '/tpl/import-do.php';
        break;
    }
    ?>
  </form>
</div>
