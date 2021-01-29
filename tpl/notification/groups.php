<?php

$groups = get_terms([
  'taxonomy' => 'gwapi-recipient-groups',
]);

$current_groups = get_post_meta($post->ID, 'recipient_groups', true);
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




        window.dropdown = function dropdown() {
            return {
                show: false,
                open() {
                    this.show = true;

                    postData({
                        action: "my_action",
                        whatever: 42

                    })
                        .then(data => {
                            console.log(data); // JSON data parsed by `data.json()` call
                        });



                },
                close() { this.show = false },
                isOpen() { return this.show === true },
            }
        }
    })();

</script>



<!--<div-->
<!--  x-data="{ selectRecipientType: null, recipientTypes: [ 'Mexico', 'USA', 'Canada' ],  stores: [ { 'store' : 'data' } ] }"-->
<!--  x-init="$watch('selectRecipientType', (recipient) => { fetch('url?recipient=" + recipient).then(res=> res.json()).then((storeData) => { stores = storeData }) })"-->
<!--  >-->
<!--  <select x-model="selectRecipientType">-->
<!--    <template x-for="recipient in recipientTypes" :key="recipient">-->
<!--      <option :value="recipient" x-text="recipient"></option>-->
<!--    </template>-->
<!--  </select>-->
<!--  Stores:-->
<!--  <template x-for="store in stores" :key="store.id">-->
<!---->
<!--  </template>-->
<!--</div>-->


<table>
  <tr id="recipients_6013d211c57fe"
      data-field-name="recipients"
      data-carrier="email"
      class="recipients vue-repeater">
    <th><label for="recipients_6013d211c57fe">Recipients</label></th>
    <td>
      <table id="recipients_6013d211c57fe"
             data-carrier="email"
             class="fields-repeater fields-repeater-sortable widefat notification-field recipients-repeater">
        <thead>
        <tr class="row header">
          <th class="handle"></th>
          <th class="type">Type</th>
          <th class="recipient">Recipient<small class="description">You can use any valid email merge tag.</small></th>
          <th class="trash"></th>
        </tr>
        </thead>
        <tbody class="ui-sortable"
               style="">
        <tr class="row">
          <td class="handle"><span class="handle-index">1</span></td>
          <td class="subfield type">
            <div class="row-field">

              <div
                x-data="{ selectedType: null, types: [ 'Recipient', 'Recipient Group', 'Administrator' ],  recipients: [] }"
                x-init="$watch('selectedType', (type) => postRequest({type: type}).then(data => { recipients = data }))"
              >
                <select x-model="selectedType">
                  <template x-for="type in types" :key="type">
                    <option :value="type" x-text="type"></option>
                  </template>
                </select>
                <template x-for="recipient in recipients" :key="recipient.id">



                    <div>
                      <div>Name: <span x-html="recipient.name"></span></div>
                      <div>Number: <span x-html="recipient.number"></span></div>
                    </div>


                </template>
              </div>



          </td>
          <td class="subfield recipient">
            <div class="row-field"><input id="recipient_6013d25312cf0"
                                          type="text"
                                          name="notification_carrier_email[recipients][0][recipient]"
                                          placeholder="info@example.com"
                                          class="widefat notification-field recipient-value"> <small class="description">You can edit this email in
                <a href="http://ocgatewayapi.docksal/wp-admin/options-general.php">General Settings</a>
              </small></div>
          </td>
          <td class="trash"></td>
        </tr>
        </tbody>
      </table><!---->
      <a href="#"
         class="button button-secondary add-new-repeater-field">Add recipient
      </a>
    </td>
  </tr>
</table>

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