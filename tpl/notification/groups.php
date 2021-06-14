<?php

/** @var \WP_Post $post */
$id = $post->ID;
$post_meta = get_post_meta($id);
$selected_recipient_type = !empty($post_meta['recipient_type']) ? current($post_meta['recipient_type']) : 'recipient';
$selected_recipient_id = get_post_meta($post->ID, 'recipient_id', true);
$selected_recipient_name = get_post_meta($post->ID, 'recipient_name', true);


$groups = get_terms([
  'taxonomy' => 'gwapi-recipient-groups',
]);

$current_groups = get_post_meta($post->ID, 'recipient_groups', true);
$current_groups = !empty($current_groups) ? $current_groups : [];


$roles = get_editable_roles();
$current_roles = get_post_meta($post->ID, 'roles', true);
$current_roles = !empty($current_roles) ? $current_roles : [];


?>

<script>
    (function () {

// POST Implementation
        async function postData(items = {}) {

            let formData = new FormData();
            for (let key in items) {
                formData.append(key, items[key]);
            }

            // Default options are marked with *
            const response = await fetch(ajaxurl, {
                method: 'POST', // *GET, POST, PUT, DELETE, etc.
                body: formData // body data type must match "Content-Type" header
            });
            return response.json(); // parses JSON response into native JavaScript objects
        }

        window.postRequest = function (type) {
            return postData({
                action: "my_action",
                whatever: 12
            });
        }

        // we add a global autocomplete function
        // which will handle our client-side logic.
        // we extend this later on...
        window.notification = function notification() {
            const input = '';

            return {
                recipients: [],
                options: [
                    {
                        id: 'recipient',
                        text: <?php echo json_encode(__('Recipient', 'gatewayapi')); ?>,
                        children: []
                    },
                    {
                        id: 'recipientGroup',
                        text:<?php echo json_encode(__('Recipient Groups', 'gatewayapi')); ?>,
                        children: []
                    },
                    {
                        id: 'role',
                        text: <?php echo json_encode(__('Roles', 'gatewayapi')); ?>,
                        children: []
                    }
                ],
                recipientSearch: {
                    autocompleteInput: '<?php echo $selected_recipient_name; ?>',
                    isOpen: false,
                    open() {
                        this.isOpen = true;
                    },
                    close() {
                        this.isOpen = false;
                    },
                    selected: null,
                    selectedId() {
                        return this.selected ? this.selected.id : '<?php echo $selected_recipient_id; ?>';
                    }

                },
                selectedOption: '<?php echo $selected_recipient_type ?>',
                select(recipient) {
                    this.recipientSearch.selected = recipient;
                    this.recipientSearch.autocompleteInput = recipient.name;
                    this.recipientSearch.close();
                },
                searchRecipient() {
                    const response = postData({
                        action: "gwapi_callback_autocomplete_recipient",
                        search: this.recipientSearch.autocompleteInput
                    });
                    response.then((data) => {
                        this.recipients = data
                    })
                }
            }
        }

    })();

</script>

<div class="notifications"
     x-data="notification()">

  <table width="100%"
         class="form-table">
    <tbody>
    <tr>
      <th width="25%">
        <?php _e('Recipient Type', 'gatewayapi'); ?>
      </th>
      <td>
        <select x-model="selectedOption"
                id="recipient-type"
                name="gatewayapi[recipient_type]">
          <template x-for="option in options"
                    :key="option.id">
            <option :value="option.id"
                    :selected="option.id == '<?php echo $selected_recipient_type; ?>'"
                    x-text="option.text"></option>
          </template>
        </select>
      </td>
    </tr>
    <tr>
      <th width="25%">
        <?php _e('Recipient', 'gatewayapi'); ?>
      </th>
      <td>
        <div x-show="selectedOption === 'recipient'">
          <div
            class="autocomplete"
          >

            <input
              id="search-input"
              autocomplete="off"
              name="gatewayapi[recipient_name]"
              type="text"
              x-on:focus="recipientSearch.open()"
              x-on:input.debounce.500="searchRecipient()"
              x-model="recipientSearch.autocompleteInput"
              placeholder="<?php esc_attr_e('Search for a recipient by name', 'gatewayapi') ?>"
            />
            <p class="help">
              <?php _e('Type the name of the recipient you wish to add to the Notification', 'gatewayapi'); ?>
            </p>

            <input type="hidden"
                   name="gatewayapi[recipient_id]"
                   x-model="recipientSearch.selectedId()">

            <div class="suggestions">
              <ul
                id="autocomplete-suggestions"
                x-show="recipientSearch.isOpen"
                x-ref="suggestions"
              >

                <template x-for="recipient in recipients"
                          :key="recipient.id">
                  <li id="item-<%= index %>"
                      @click="select(recipient)"
                      class="item">
                    <span x-html="recipient.name"></span>
                  </li>

                </template>

              </ul>
            </div>
            <template x-if="recipientSearch.selected">
              <div x-html="recipientSearch.selected.name"></div>
            </template>

          </div>

        </div>
        <div x-show="selectedOption === 'recipientGroup'">

          <div class="gwapi-row recipient-groups"
               data-selected_groups="<?= $current_groups ? esc_attr(json_encode($current_groups)) : ''; ?>">
            <div class="all-groups col-50">
              <h4><?php _e('All recipient groups', 'gatewayapi'); ?></h4>

              <div class="inner">
                  <?php foreach ($groups as $group): ?>
                    <label class="gwapi-checkbox" style="margin-right:10px">
                      <input type="checkbox"
                             name="gatewayapi[recipient_groups][]"
                             id="group-id-<?= $group->term_id; ?>"
                             value="<?= $group->term_id; ?>"
                        <?php echo in_array($group->term_id, $current_groups) ? 'checked' : '' ?>
                      >
                        <?= $group->name; ?>
                      <span class="number"
                            title="<?php esc_attr_e('Recipients in group', 'gatewayapi') ?>: <?= $group->count; ?>">(<?= $group->count; ?>)</span>
                    </label>
                  <?php endforeach; ?>
              </div>
            </div>

            <div class="selected-groups col-50">
              <h4><?php _e('Selected recipient groups', 'gatewayapi'); ?></h4>
              <div class="inner"></div>
            </div>
          </div>

          <div class="footer">
            <p
              class="description"><?php _e('You will be sending to all recipients who are in any of the selected groups.', 'gatewayapi'); ?></p>
            <p
              class="description"><?php _e('Only groups with at least one recipient are listed.', 'gatewayapi'); ?></p>
          </div>
        </div>
        <div x-show="selectedOption === 'role'">
          <div class="gwapi-row recipient-groups-role"
               data-selected_groups="<?= $current_roles ? esc_attr(json_encode($current_roles)) : ''; ?>">
            <div class="all-groups col-50">
              <h4><?php _e('All roles', 'gatewayapi'); ?></h4>

              <div class="inner">
                  <?php foreach ($roles as $role_id => $role): ?>
                    <label class="gwapi-checkbox" style="display: block; margin-bottom: 5px">
                      <input type="checkbox"
                             name="gatewayapi[roles][]"
                             id="role-id-<?php echo $role_id ?>"
                             value="<?php echo $role_id ?>"
                        <?php echo in_array($role_id, $current_roles) ? 'checked' : '' ?>
                      >
                        <?= __($role['name']); ?>
                    </label>
                  <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </td>
    </tr>
    </tbody>
  </table>
</div>

