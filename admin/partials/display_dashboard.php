<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://christophercasper.com/
 * @since      1.0.0
 *
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
  <div class="ag-admin">
  <h1><?php _e('Autoglot Plugin Dashboard', 'autoglot');?></h1>
  <?php
  // Let see if we have a caching notice to show
  $admin_notice = get_option('autoglot_admin_notice');
  if(is_array($admin_notice))$admin_notice[0] = htmlspecialchars(strip_tags($admin_notice[0]));
  else $admin_notice = htmlspecialchars(strip_tags($admin_notice));
  if($admin_notice) {
    // We have the notice from the DB, lets remove it.
    delete_option( 'autoglot_admin_notice' );
    // Call the notice message
    if(is_array($admin_notice))$this->admin_notice($admin_notice[0],$admin_notice[1]);
    else $this->admin_notice($admin_notice);
  }
  if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ){
    $this->admin_notice(__('Your settings have been updated!', 'autoglot'));
  }
  ?>
  <?php
    do_settings_sections($display_page);
  ?>
  </div>
</div>