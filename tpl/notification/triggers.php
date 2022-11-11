<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/** @var \WP_Post $post */
$id = $post->ID;
$triggers = gatewayapi__get_triggers_grouped();
$post_meta_triggers = get_post_meta($id, 'triggers');
$selected_trigger = $post_meta_triggers ? current($post_meta_triggers) : null;


?>

<div class="gwapi-star-errors"></div>
<div>

  <select id="select-trigger"
          name="gatewayapi[triggers]"
          class="trigger-default"
          style="width: 100%"
          placeholder="Select trigger...">

    <?php foreach ($triggers as $group => $subtriggers): ?>
      <optgroup label="<?php echo esc_attr($group); ?>">
        <?php foreach ($subtriggers as $slug => $trigger) : ?>
          <option value="<?php echo esc_attr($trigger->getId()); ?>"
                  data-id="<?php echo esc_attr($trigger->getId()); ?>"
                  data-title="<?php echo esc_attr($trigger->getName()); ?>"
                  data-text="<?php echo esc_attr($trigger->getDescription()); ?>"
            <?php if ($selected_trigger && $selected_trigger === $trigger->getId()): ?>
              selected="selected"
            <?php endif ?>
          >
            <?php echo esc_html($trigger->getName()); ?>
            <div>
              <?php $description = $trigger->getDescription(); ?>
              <?php if (!empty($description)) : ?>
                || <?php echo esc_html($description); ?>
              <?php endif ?>
            </div>
          </option>

        <?php endforeach; ?>
      </optgroup>
    <?php endforeach; ?>
  </select>
</div>
