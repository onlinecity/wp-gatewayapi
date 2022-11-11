<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<p>
    <?php _e('To import your recipients, please open a spreadsheet containing at least the country code and mobile numbers of your recipients. Copy all rows and simply paste into the big textarea below.', 'gatewayapi'); ?>
</p>
<p>
    <?php _e('First row must contain columns names.','gatewayapi'); ?>
</p>

<input type="hidden" name="step" value="2">

<table class="form-table">
    <tbody>
    <tr>
        <th>
            <?php _e('Your existing data', 'gatewayapi'); ?>
        </th>
        <td>
            <textarea name="database" id="database" style="width: 100%" rows="10"></textarea>
        </td>
    </tr>
    <tr>
        <th>&nbsp;</th>
        <td>
            <button class="button button-primary button-large" type="submit"><?php _e('Analyze data', 'gatewayapi'); ?></button>
        </td>
    </tr>
    </tbody>
</table>