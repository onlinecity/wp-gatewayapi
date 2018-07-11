<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

// save raw data transient
delete_transient('gwapi_import_' . get_current_user_id());
$data = trim($_POST['database']);
set_transient('gwapi_import_' . get_current_user_id(), $data, 60 * 60 * 2);

$fields = [];
foreach(_gwapi_all_recipient_fields() as $f) {
    $fields[strtolower($f['field_id'])] = $f['name'];
}

// analyse first row: extract column titles
$column_row = substr($data, 0, strpos($data, "\n"));
$columns = explode("\t", $column_row);
?>

<input type="hidden" name="step" value="3">
<p>
    <?php _e('The system has crunched the data you pasted. Please map the column titles from your spreadsheet, with the fields from the system below.'); ?>
</p>

<table class="form-table">
    <tbody>
    <?php foreach ($fields as $col => $field): ?>
        <?php $required = $col == 'cc' || $col == 'number'; ?>
        <tr>
            <th>
                <?= esc_html($field); ?> <?= $required ? '<strong style="color: red">*</strong>' : ''; ?>
            </th>
            <td>
                <select name="columns[<?= $col; ?>]" style="width: 100%" <?= $required ? 'required' : ''; ?>>
                    <option value="">- <?php _e('Ignore', 'gwapi'); ?> -</option>
                    <?php foreach ($columns as $i => $col): ?>
                        <option value="<?= $i; ?>"><?= esc_html($col); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<hr>

<h3><?php _e('Assign to groups'); ?></h3>
<p class="description"><?php _e('Which groups should these recipients be assigned to? For existing recipients, their current groups will only be expanded with the new groups and none of their current groups will be removed.'); ?></p>
<table class="form-table">
    <tbody>
        <tr>
            <th>
                <?php _e('Groups'); ?>
            </th>
            <td>
                <?php foreach(get_terms('gwapi-recipient-groups', ['hide_empty' => false]) as $t): ?>
                    <label class="gwapi-checkbox">
                        <input type="checkbox" name="gwapi_recipient_groups[]" value="<?= $t->term_id; ?>"> <?= $t->name; ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
    </tbody>
</table>


<table class="form-table">
    <tbody>
    <tr>
        <th></th>
        <td>
            <button class="button button-primary button-large" type="submit"><?php _e('Import', 'gwapi'); ?></button>
            <p class="description">
                <?php _e('At import the system compares your new data with your existing, based on the mobile country code and number.', 'gwapi'); ?>
                <?php _e('If a recipient already exists, the recipient is overwritten with the not-ignored fields above.', 'gwapi'); ?>
                <?php _e('Fields which you have chosen to "Ignore", will remain untouched for existing members.', 'gwapi'); ?>
            </p>
        </td>
    </tr>
    </tbody>
</table>


<script>
    jQuery(function($) {
        $('.toggle_enable').change(function(ev) {
            $(this).parents('label').next().toggleClass('hidden');
        });
    })
</script>