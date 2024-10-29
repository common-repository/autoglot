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
<div class="wrap ag-setup-wrap">
<h1><?php _e('Autoglot Plugin Setup Wizard', 'autoglot');?></h1>
<ol class="ag-setup-steps">
    <li class="<?php echo ($this->setup_wizard>1?"done":"active");?>"><span><?php echo __('Step 1. Setup your API Key.');?></span></li>
    <li class="<?php echo array(1=>"",2=>"active",3=>"done")[$this->setup_wizard];?>"><span><?php echo __('Step 2. Choose Languages.');?></span></li>
    <li class="<?php echo ($this->setup_wizard==3?"active":"");?>"><span><?php echo __('Step 3. Plugin is Ready!');?></span></li>
</ol>
<div class="ag-setup-content">
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
  if($this->setup_wizard<3){
    echo '  <form method="POST" action="options.php">';    
    settings_fields($display_page);
    do_settings_sections($display_page);
    if($this->setup_wizard == 1)echo '<span style="float:left;padding-top: 15px;"><a href="'.wp_nonce_url(admin_url( 'admin.php?page=autoglot_translation_setup&ag_setup=skip'), "ag_setup").'" class="button"><i class="dashicons dashicons-no"></i> '.__('Skip Setup Wizard', 'autoglot').'</a></span>';
    if($this->setup_wizard == 2)echo '<span style="float:left;padding-top: 15px;"><a href="'.wp_nonce_url(admin_url( 'admin.php?page=autoglot_translation_setup&ag_setup=restart'), "ag_setup").'" class="button"><i class="dashicons dashicons-no"></i> '.__('Reset and Restart', 'autoglot').'</a></span>';
    submit_button(__('Save and continue', 'autoglot'));
    echo '</form>';
  }else{
    do_settings_sections($display_page);
  }
  ?>
  
</div>
</div>