<?php

$triggers = _gwapi_get_triggers_grouped();

?>

<div class="gwapi-star-errors"></div>
<table width="100%"
       class="form-table">
    <tbody>
    <tr>
        <th width="25%">
            <?php _e('Trigger', 'gatewayapi') ?>
        </th>
        <td>
            <select id="select-trigger"
                    class="trigger-default"
                    placeholder="Select trigger...">

                <option></option>
                <?php foreach ($triggers as $group => $subtriggers): ?>

                    <optgroup label="<?php echo esc_attr($group); ?>">

                        <?php foreach ($subtriggers as $slug => $trigger) : ?>

                            <option value="<?php echo esc_attr($slug); ?>"
                                    data-id="<?php echo $trigger->getId(); ?>"
                                    data-title="<?php echo $trigger->getName(); ?>"
                                    data-text="<?php echo $trigger->getDescription(); ?>"
                            >
                                <?php echo esc_html($trigger->getName()); ?>
                                <div>
                                    <?php $description = $trigger->getDescription(); ?>
                                    <?php if (!empty($description)) : ?>
                                        ||<?php echo esc_html($description); ?>
                                    <?php endif ?>
                                </div>

                            </option>

                        <?php endforeach; ?>

                    </optgroup>

                <?php endforeach; ?>

            </select>
        </td>
    </tr>
    </tbody>
</table>