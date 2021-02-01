<?php

$groups = get_terms([
  'taxonomy' => 'gwapi-recipient-groups',
]);

$current_groups = get_post_meta($post->ID, 'recipient_groups', true);

$roles = get_editable_roles();
$current_role = 'editor';



?>


<script>
    (function() {


// POST Implementation
        async function postData(items = {}) {

            let formData = new FormData();
            for ( let key in items ) {
                formData.append(key, items[key]);
            }

            // Default options are marked with *
            const response = await fetch(ajaxurl, {
                method: 'POST', // *GET, POST, PUT, DELETE, etc.
                body: formData // body data type must match "Content-Type" header
            });
            return response.json(); // parses JSON response into native JavaScript objects
        }


        window.postRequest = function(type) {
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
                    {id: 'recipient', text: "Recipient", children: []},
                    {id: 'recipientGroup', text: "Recipient Groups", children: []},
                    {id: 'role', text: "Roles", children: []}
                ],
                recipientSearch: {
                    autocompleteInput: '',
                    isOpen: false,
                    open() { this.isOpen = true; },
                    close() { this.isOpen = false; },
                    selected: null,
                    selectedId() {
                        return this.selected ? this.selected.id : '';
                    }

                },
                selectedOption: 'recipient',
                select(recipient) {
                    this.recipientSearch.selected = recipient;
                    this.recipientSearch.autocompleteInput = recipient.name;
                    this.recipientSearch.close();
                },
                searchRecipient() {

                    const response =  postData({
                        action: "gwapi_callback_autocomplete_recipient",
                        search: this.recipientSearch.autocompleteInput
                    });
                    response.then((data) => {
                        console.log('Received data: ' + data);
                        this.recipients = data
                    })
                }
            }
        }


    })();

</script>




<div class="notifications"
     x-data="notification()">


  <div>
    <div>
      <label for="recipient-type"><strong>Recipient Type</strong></label>
    </div>
    <select x-model="selectedOption" id="recipient-type">
      <template x-for="option in options" :key="option.id">
        <option :value="option.id" x-text="option.text"></option>
      </template>
    </select>
  </div>

  <div x-show="selectedOption === 'recipient'">
      <div
        class="autocomplete"
      >
        <p>
        <div>
          <label for="search-input"><strong>Recipient</strong></label>
        </div>
          <input
            id="search-input"
            autocomplete="off"
            name="search"
            type="text"
            x-on:focus="recipientSearch.open()"
            x-on:input.debounce.500="searchRecipient()"
            x-model="recipientSearch.autocompleteInput"
            placeholder="Search for a recipient by name"
          />
          <p class="help">Type the name of the recipient you wish to add to the Notification</p>

          <input type="hidden" name="selectedRecipient" x-model="recipientSearch.selectedId()">

        </p>



        <div class="suggestions">
          <ul
            id="autocomplete-suggestions"
            x-show="recipientSearch.isOpen"
            x-ref="suggestions"
          >

            <template x-for="recipient in recipients" :key="recipient.id">
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
              <label class="gwapi-checkbox">
                <input type="checkbox"
                       name="gatewayapi[recipient_groups][]"
                       id=""
                       value="<?= $group->term_id; ?>">
                  <?= $group->name; ?>
                <span class="number"
                      title="<?php esc_attr_e('Recipients in group', 'gatewayapi') ?>: <?= $group->count; ?>"><?= $group->count; ?></span>
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
         data-selected_groups="<?= $current_role ? esc_attr(json_encode($current_role)) : ''; ?>">
      <div class="all-groups col-50">
        <h4><?php _e('All recipient groups', 'gatewayapi'); ?></h4>

        <div class="inner">
            <?php foreach ($roles as $role_id => $role): ?>
              <label class="gwapi-checkbox">
                <input type="checkbox"
                       name="gatewayapi[recipient_groups][]"
                       id=""
                       value="<?php $role_id ?>">
                  <?= $role['name']; ?>

              </label>
            <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  </div>


</div>

