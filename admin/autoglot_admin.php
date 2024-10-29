<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: The admin-specific functionality of the plugin.
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

class autoglot_admin {

	/**
	 * The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 */
	private $version;

	/**
	 * Parent class
	 */
    private $autoglot;

	/**
	 * For Setup
	 */
    private $setup_wizard;

	/**
	 * For Notification bubbles
	 */
    private $notification_count;

	/**
	 * For Statistics
	 */
    private $db_stats;
     
    /** @var autoglot_editor_table $editor_table the wp table */
    private $editor_table;

    /** current translation balance to show warnings */
    private $balance;
    
    private $cache_flushed;

	public function __construct( &$autoglot, $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->setup_wizard = 0;
		$this->notification_count = 0;
        
        $this->cache_flushed = false;

        $this->autoglot = &$autoglot;
        $this->autoglot_table = $GLOBALS['wpdb']->prefix . AUTOGLOT_TABLE;
        
        if(isset($_GET["ag_setup"]) && $_GET["ag_setup"]=="restart" && isset($_GET["page"]) && $_GET["page"]=="autoglot_translation_setup"){
    		add_action('admin_init', array($this, 'ag_restart'));
        }
        if(isset($_GET["ag_setup"]) && $_GET["ag_setup"]=="skip" && isset($_GET["page"]) && $_GET["page"]=="autoglot_translation_setup"){
    		add_action('admin_init', array($this, 'ag_skip'));
        }
        
        $this->autoglot->curl->curlInit();
        $this->balance = $this->autoglot->curl->getConnected()?$this->autoglot->curl->getBalance():0;

        //remove after sites updated
        if(strlen($this->autoglot->options->translation_API_key) && get_option('autoglot_translation_default_language') != false && get_option('autoglot_setup_complete') == false){
            update_option("autoglot_setup_complete",1);
        }

        if(get_option('autoglot_translation_API_key') == false && get_option('autoglot_setup_complete') == false){
            $this->setup_wizard = 1;
        }
        elseif(get_option('autoglot_translation_default_language') == false && get_option('autoglot_setup_complete') == false){
            $this->setup_wizard = 2;
        }
        if(!$this->setup_wizard && isset($_GET["page"]) && $_GET["page"]=="autoglot_translation_setup"){
            $this->setup_wizard = 3;
            update_option("autoglot_setup_complete",1);
        } 

        if($this->setup_wizard && isset($_GET["page"]) && strpos($_GET["page"], "autoglot_translation")!==false && $_GET["page"]!="autoglot_translation_setup"){
            nocache_headers(); 
            header("Location: ".admin_url('admin.php?page=autoglot_translation_setup'));
            exit();
        } 
        
        add_action( 'admin_enqueue_scripts', array($this, 'ag_enqueues') );
		// Lets add an action to setup the admin menu in the left nav
		add_action( 'admin_menu', array($this, 'add_admin_menu') );
		// Lets remove certain elements from menu while keeping focus on the main menu
		add_filter( 'submenu_file', array($this, 'remove_menu_elements') );
		// Add some actions to setup the settings we want on the wp admin page
		add_action('admin_init', array($this, 'setup_sections'));
		add_action('admin_init', array($this, 'setup_fields'));
        
        add_action('admin_notices', array($this, 'check_options'));

        // Add action links on admin plugins page 
		add_filter( 'plugin_action_links', array($this, 'add_action_links'), 10, 2);
		add_filter( 'network_admin_plugin_action_links', array($this, 'add_action_links'), 10, 2);

        //Text Replacement actions        
		add_action('admin_init', array($this, 'text_replacement'));
        add_action('edit_form_top', array($this, 'text_replacement_form_top'));
        add_filter('enter_title_here', array($this, 'text_replacement_title_text'));
        add_action('add_meta_boxes', array($this, 'text_replacement_add_custom_box'));
        add_action('save_post_'.AUTOGLOT_TEXTREPL_POSTTYPE, array($this, 'text_replacement_save_data'));
        add_action('admin_head-post.php', array($this, 'text_replacement_publishing_actions'));
        add_action('admin_head-post-new.php', array($this, 'text_replacement_publishing_actions'));     
        add_action('post_submitbox_start', array($this, 'text_replacement_publishing_box'));
        add_filter('post_row_actions', array($this, 'text_replacement_quick_actions'));
        add_filter('bulk_actions-edit-'.AUTOGLOT_TEXTREPL_POSTTYPE, array($this, 'text_replacement_remove_bulk_edit'));
        add_filter('views_edit-'.AUTOGLOT_TEXTREPL_POSTTYPE, array($this, 'text_replacement_text_top'));
        add_action('view_mode_post_types', array( $this, 'text_replacement_disable_view_mode' ) );
        add_action('admin_notices', array($this, 'text_replacement_admin_notices'));
        add_filter('manage_'.AUTOGLOT_TEXTREPL_POSTTYPE.'_posts_columns', array( $this, 'text_replacement_set_custom_columns'));
        add_action('manage_'.AUTOGLOT_TEXTREPL_POSTTYPE.'_posts_custom_column' , array( $this, 'text_replacement_custom_columns_data' ), 10, 2);
        add_filter('set-screen-option', array($this, 'on_screen_option'), 10, 3);
        
        //cache clearing
        add_action('update_option', array( $this, 'flush_cache' ), 10, 3);

        if($this->autoglot->autoglot_database->db_exists()) $this->db_stats = $this->autoglot->autoglot_database->db_stats();
        $this->db_stats["w2translate"] = 0;
        if($this->autoglot->autoglot_database->db_exists()) foreach ($this->autoglot->options->active_languages as $lng) if($lng != $this->autoglot->options->default_language) $this->db_stats["w2translate"] += max(0,$this->db_stats['wpcount'] - $this->db_stats['countactivewords'][$lng]);   
        $this->db_stats["w2translate"] = max(0, $this->db_stats["w2translate"]);//$this->db_stats['wpcount']*(count($this->autoglot->options->active_languages)-1) - array_sum($this->db_stats['countactivewords']));

        if(!$this->autoglot->curl->getConnected() || !strlen($this->autoglot->options->translation_API_key))$this->notification_count++;
        elseif($this->balance<=0)$this->notification_count++;
        if(!$this->autoglot->options->translation_enable)$this->notification_count++;
        if($this->autoglot->options->translation_adminonly)$this->notification_count++;
        if(count($this->autoglot->options->active_languages)<1)$this->notification_count++;
        if(!is_active_widget(false, false, 'autoglot_custom_widget') && !$this->autoglot->options->floatbox_enable)$this->notification_count++;
        if($this->db_stats["w2translate"]>0)$this->notification_count++;

    	// add to Content Stats table
        if($this->autoglot->options->translation_enable) add_action('dashboard_glance_items', array($this, 'glance_word_count'));

	}

    function on_screen_option($status, $option, $value) {
        return $value;
    }

	/**
	 * Restart wizard
	 */
	public function ag_restart() {
        check_admin_referer("ag_setup");

        delete_option("autoglot_translation_API_key");
        delete_option("autoglot_translation_enable");
        nocache_headers(); 
        header("Location: ".admin_url('admin.php?page=autoglot_translation_setup'));
        exit();
    }

	/**
	 * Skip wizard
	 */
	public function ag_skip() {
        check_admin_referer("ag_setup");

        update_option("autoglot_setup_complete",1);
        nocache_headers(); 
        header("Location: ".admin_url('admin.php?page=autoglot_translation'));
        exit();
    }

	/**
	 * Enqueue everything
	 */
	public function ag_enqueues() {
        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

	/**
	 * Add the menu items to the admin menu
	 */
	public function add_admin_menu() {

        //translation DB does not exist, no sense to continue
        if(!$this->autoglot->autoglot_database->db_exists()) {
    		// Main Menu Item
    	  	add_menu_page(
    			'Autoglot',
    			'Autoglot',
    			'manage_options',
    			'autoglot_translation',
                function(){$this->admin_notice(__('There was an error during installation or activation of Autoglot plugin: Autoglot table has not been created in DB. Please contact site administrator or Autoglot support.', 'autoglot'),"error");},
    			//array($this, 'display_settings'),
    			//'dashicons-image-filter',
                'data:image/svg+xml;base64,' . AUTOGLOT_PLUGIN_ICON,
    			100.000000001);
            return;
        }            

        //translation DB does not exist, no sense to continue
        if($this->setup_wizard) {
    		// Main Menu Item
    	  	add_menu_page(
    			'Autoglot',
    			__('Autoglot Setup', 'autoglot'),
    			'manage_options',
    			'autoglot_translation_setup',
                function(){
                    $display_page = 'autoglot_translation_setup';
            		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_setup.php';
                },
    			//array($this, 'display_settings'),
    			//'dashicons-image-filter',
                'data:image/svg+xml;base64,' . AUTOGLOT_PLUGIN_ICON,
    			100.000000001);
            
            return;
        }

		// Main Menu Item
	  	add_menu_page(
			'Autoglot',
			'Autoglot'.(strpos(admin_url(basename($_SERVER['REQUEST_URI'])),"autoglot")===false && $this->notification_count?' <span class="awaiting-mod">'.$this->notification_count.'</span>':""),
			'manage_options',
			'autoglot_translation',
            function(){
                $display_page = 'autoglot_translation';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_dashboard.php';
            },
			//array($this, 'display_settings'),
			//'dashicons-image-filter',
            'data:image/svg+xml;base64,' . AUTOGLOT_PLUGIN_ICON,
			100.000000001);

		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Autoglot Dashboard', 'autoglot'),
			__('Dashboard', 'autoglot').($this->notification_count?' <span class="awaiting-mod">'.$this->notification_count.'</span>':""),
			'manage_options',
			'autoglot_translation',
            function(){
                $display_page = 'autoglot_translation';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_dashboard.php';
            }
//			array($this, 'display_settings')
		);

		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Settings', 'autoglot'),
			__('Settings', 'autoglot'),
			'manage_options',
			'autoglot_translation_settings',
            function(){
                $display_page = 'autoglot_translation_settings';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_settings.php';
            }
//			array($this, 'display_settings')
		);
		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Languages', 'autoglot'),
			__('Languages', 'autoglot'),
			'manage_options',
			'autoglot_translation_languages',
            function(){
                $display_page = 'autoglot_translation_languages';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_settings.php';
            }
//			array($this, 'display_languages')
		);

		// Sub Menu Item
		$load_editor = add_submenu_page(
			'autoglot_translation',
			__('Translation Editor', 'autoglot'),
			__('Translation Editor', 'autoglot'),
			'manage_options',
			'autoglot_translation_editor',
            function(){
                $display_page = 'autoglot_translation_editor';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_editor.php';
            }
		);

		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Advanced', 'autoglot'),
			__('Advanced', 'autoglot'),
			'manage_options',
			'autoglot_translation_advanced',
            function(){
                $display_page = 'autoglot_translation_advanced';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_settings.php';
            }
//			array($this, 'display_advanced')
		);

		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Links Modifier', 'autoglot'),
			__('Links Modifier', 'autoglot'),
			'manage_options',
			'autoglot_translation_linksmod',
            function(){
                $display_page = 'autoglot_translation_linksmod';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_settings.php';
            }
//			array($this, 'display_advanced')
		);

		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Text Replacement', 'autoglot'),
			__('Text Replacement', 'autoglot'),
			'manage_options',
			'edit.php?post_type=autoglot_textrepl',
            NULL
		);

		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Utilities', 'autoglot'),
			__('Utilities', 'autoglot'),
			'manage_options',
			'autoglot_translation_utilities',
            function(){
                $display_page = 'autoglot_translation_utilities';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_utilities.php';
            }
//			array($this, 'display_settings')
		);
		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Delete Empty Translation', 'autoglot'),
			__('Delete Empty Translation', 'autoglot'),
			'manage_options',
			'autoglot_translation_delete_empty',
            function(){
                $display_page = 'autoglot_translation_delete_empty';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_utilities.php';
            }
//			array($this, 'display_settings')
		);
		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Delete Duplicate Translation', 'autoglot'),
			__('Delete Duplicate Translation', 'autoglot'),
			'manage_options',
			'autoglot_translation_delete_duplicate',
            function(){
                $display_page = 'autoglot_translation_delete_duplicate';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_utilities.php';
            }
//			array($this, 'display_settings')
		);
		// Sub Menu Item
		add_submenu_page(
			'autoglot_translation',
			__('Backup Translation Table', 'autoglot'),
			__('Backup Translation Table', 'autoglot'),
			'manage_options',
			'autoglot_translation_backup_table',
            function(){
                $display_page = 'autoglot_translation_backup_table';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_utilities.php';
            }
//			array($this, 'display_settings')
		);
		// Sub Menu Item
/*		add_submenu_page(
			'autoglot_translation',
			__('Reserved function', 'autoglot'),
			__('Reserved function', 'autoglot'),
			'manage_options',
			'autoglot_translation_utilities_reserved',
            function(){
                $display_page = 'autoglot_translation_utilities_reserved';
        		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/display_utilities.php';
            }
//			array($this, 'display_settings')
		);
*/
        add_action('load-'.$load_editor, array(&$this, 'load_editor'));
	}
    
    function remove_menu_elements( $submenu_file ) {
    
        global $plugin_page;
    
        $hidden_submenus = array(
            'autoglot_translation_delete_empty' => true,
            'autoglot_translation_delete_duplicate' => true,
            'autoglot_translation_utilities_reserved' => true,
            'autoglot_translation_backup_table' => true,
        );
    
        // Select another submenu item to highlight (optional).
        if ( $plugin_page && isset( $hidden_submenus[ $plugin_page ] ) ) {
            $submenu_file = 'autoglot_translation_utilities';
        }
    
        // Hide the submenu.
        foreach ( $hidden_submenus as $submenu => $unused ) {
            remove_submenu_page( 'autoglot_translation', $submenu );
        }
    
        return $submenu_file;
    }

    /**
	 * Setup sections in the settings
	 */
	public function setup_sections() {
        if($this->setup_wizard){
            switch($this->setup_wizard){
                case 2:
                    add_settings_section( 'section_setup2','', array($this, 'section_callback'), 'autoglot_translation_setup' );
                    break;
                case 3:
                    add_settings_section( 'section_setup3','', array($this, 'section_callback'), 'autoglot_translation_setup' );
                    break;
                case 1:
                default:
                    add_settings_section( 'section_setup1','', array($this, 'section_callback'), 'autoglot_translation_setup' );
                    break;
            }
            
        }
        else
            add_settings_section( 'section_dashboard','', array($this, 'section_callback'), 'autoglot_translation' );
//		add_settings_section( 'section_about', __('About Autoglot', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation' );
//		add_settings_section( 'section_account', __('Autoglot Account Stats', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation' );
//		add_settings_section( 'section_stats', __('Plugin Stats', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation' );
//		add_settings_section( 'section_support', __('Autoglot Support', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation' );
		add_settings_section( 'section_main', __('Main Configuration', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_settings' );
		add_settings_section( 'section_switcher', __('Language Switcher', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_settings' );
		add_settings_section( 'section_lang', __('Languages', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_languages');
		add_settings_section( 'section_langnames', __('Language Names', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_languages');
		add_settings_section( 'section_flags', __('Flags', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_languages');
		add_settings_section( 'section_adv_trans', __('Translation Settings', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_advanced');
		add_settings_section( 'section_adv_out', __('Output Settings', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_advanced');
		add_settings_section( 'section_linksmod', __('Language Code Insertion', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_linksmod');
		add_settings_section( 'section_utilities', __('Useful Plugin Utilities', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_utilities' );
		add_settings_section( 'section_editor', __('Translations DB', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_editor' );
		add_settings_section( 'section_delete_empty', __('Delete Empty Translations', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_delete_empty' );
		add_settings_section( 'section_delete_duplicate', __('Delete Duplicate Translations', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_delete_duplicate' );
		add_settings_section( 'section_backup_table', __('Backup Translation DB', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_backup_table' );
//		add_settings_section( 'section_utilities_reserved', __('Custom Function', 'autoglot'), array($this, 'section_callback'), 'autoglot_translation_utilities_reserved' );

	}

	/**
	 * Callback for each section
	 */
	public function section_callback( $arguments ) {
		switch( $arguments['id'] ){
			case 'section_setup1':
				echo '<p><em>' . __('Autoglot is a plugin for a WordPress platform that makes your website or blog SEO-friendly multilingual and translates all your content automatically using the best neural machine translation solutions.', 'autoglot') . '</em></p>';
				echo '<p>' . __('Thank you for choosing <strong>Autoglot Translation plugin</strong>. This quick setup wizard will help you configure the basic settings. Let\'s start with your API key!', 'autoglot') . '</p>';
                printf('<p>' . __( '<a href="%s" target="_blank">Please register in our Autoglot Control Panel</a> and receive your unique API key. This key should be kept secret and never shared with anyone.', 'autoglot' ). " ".__('Registration is free and takes only a few moments. You don\'t need a credit card, any payment or subscription to get your API key.', 'autoglot').'</p>', esc_url(AUTOGLOT_CP_SIGNUP));
				echo '<p>'  . '</p>';
                break;
			case 'section_setup2':
				echo '<p>' . __('Please setup your languages here. You can choose as many languages as you want but we recommend that you start with only one language.', 'autoglot').' '. __('You may skip this step and choose languages for translation later.', 'autoglot') . '</p>';
                break;
			case 'section_setup3':
				echo '<p>' . __('<strong>Autoglot plugin has been successfully configured and is now ready to translate your website!</strong> Please use the links below to access dashboard or open your website:', 'autoglot') . '</p>';
                echo '<ol>';
                if($this->autoglot->options->translation_enable){
                    echo '<li>';
                    //printf('<li>'.__( '<a href="%s" class="button" target="_blank"><i class="dashicons dashicons-admin-home"></i> Site Homepage</a>', 'autoglot'), home_url().'</li>');
                    if(count($this->autoglot->options->active_languages)>1)
                    {
                        echo __('<strong>Open your website in chosen languages!</strong> Please be aware of your balance. We recommend that you start with one language and once satisfied, proceed with more languages:', 'autoglot'). '<br /><br /><ul>';
                        foreach($this->autoglot->options->active_languages as $lang) if($lang!=$this->autoglot->options->default_language){
                            echo '<li><a href="'.autoglot_utils::add_language_to_url(home_url(),home_url(),$lang).'" class="button" target="_blank"><i class="dashicons dashicons-admin-site"></i> '.autoglot_utils::get_language_original_name($lang).'</a></li>';
                        }
                        echo '</ul>';
                    }
                    else {//no languages
                        printf(__('<strong>You have not selected languages for translation.</strong> No worries, you may do so later on language settings page:', 'autoglot'). '<br /><br />'.
                        __('<a href="%s" class="button" target="_blank"><i class="dashicons dashicons-admin-site"></i> Language Settings</a>','autoglot'), admin_url( 'admin.php?page=autoglot_translation_languages'));
                    }
                    echo '</li>';
                    echo '<li>';
                    if(current_theme_supports('widgets')){
                        printf(__( '<strong>You may want to add an Autoglot language switcher to your widgets.</strong> This will let your visitors switch site languages. This widget can be added to almost any widgets area: sidebars, footers, etc.', 'autoglot'). '<br /><br />'.
                        __( '<a href="%s" class="button" target="_blank"><i class="dashicons dashicons-screenoptions"></i> Setup Autoglot Widget</a>', 'autoglot'),
                        admin_url( 'widgets.php'));
                    }
                    else {//no widgets
                        echo __('<strong>Your theme currently does not support widgets.</strong> No worries, Autoglot will function without widgets area. You may add language switcher in popup or as a shortcode via Autoglot Dashboard.', 'autoglot');
                    }
                    echo '</li>';
                }
                else {//translation not enabled
                    printf('<li>'.__( '<strong>Autoglot translation has not been enabled.</strong> Before trying and testing plugin you should enable Autoglot translation in main settings:', 'autoglot'). '<br /><br />'.
                    __( '<a href="%s" class="button"><i class="dashicons dashicons-admin-settings"></i> Main Settings</a>', 'autoglot').'</li>', 
                    admin_url( 'admin.php?page=autoglot_translation_settings'));
                }
                printf('<li>'.__( '<strong>Autoglot Dashboard is the main place to start.</strong> This will display your translation statistics, available balance (number of words you can translate), and other useful statistics:', 'autoglot'). '<br /><br />'.
                __( '<a href="%s" class="button"><i class="dashicons dashicons-analytics"></i> Autoglot Dashboard</a>', 'autoglot'). '</li>',
                //'<li>'.__( '<a href="%s" class="button"><i class="dashicons dashicons-admin-settings"></i> Main Settings</a>', 'autoglot').'</li>', 
                admin_url( 'admin.php?page=autoglot_translation')/*, admin_url( 'admin.php?page=autoglot_translation_settings')*/);
                echo '</ol>';
                break;


			case 'section_dashboard':
                echo '
                <form name="my_form" method="post">
                <input type="hidden" name="action" value="some-action">';
                wp_nonce_field( 'some-action-nonce' );
                /* Used to save closed meta boxes and their order */
                wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
                wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
                echo '			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-'. (1 == get_current_screen()->get_columns() ? '1' : '2').' ">
					<div id="postbox-container" class="postbox-container">';

                echo '<h2>'.__('Autoglot Account Stats', 'autoglot').'</h2>';
                if($this->autoglot->curl->getConnected()){
					echo '<p>' . __('Statistics of your Autoglot account.', 'autoglot') . '</p>';
                    echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                    echo '<strong>' . __('Translation balance', 'autoglot') . '</strong>';
                    echo '</td><td></td></tr></thead><tbody>';
                    printf('<tr><td>'.($this->balance<=0?'<span class="autoglot-bubble-red">!</span> ':($this->balance < AUTOGLOT_LOW_BALANCE?'<span class="autoglot-bubble-yellow">!</span> ':'')) . __('Your current Autoglot translation balance (number of words you can translate):', 'autoglot') . '</td><td'.($this->balance?($this->balance >= AUTOGLOT_LOW_BALANCE?" style='background-color:#99FF99'":" style='background-color:#FFDD99'"):" style='background-color:#FF9999'").'><strong>%s</strong></td></tr>', number_format_i18n($this->balance,0));
                    echo '</tbody></table>';
    				printf('<p>' . __('You can replenish your translation balance in your <a href="%s" target="_blank" class="button">Autoglot Control Panel</a>', 'autoglot') . '</p>', esc_url(AUTOGLOT_CP_URL_ORDER));
                }
                elseif(strlen($this->autoglot->options->translation_API_key)) {
                    echo '<div class="postbox"><div class="inside">';
                    printf('<p style="color:#cc0000">' . __( 'We could not connect to Autoglot API with your API key.', 'autoglot' ).' '.__('Please login to <a href="%s" target="_blank">Autoglot Control Panel</a> and retrieve your API key.', 'autoglot').' '.__('Please then set your API key in <a href="%s">Autoglot Settings Page</a>.', 'autoglot'). '</p>', esc_url(AUTOGLOT_CP_URL), admin_url( 'admin.php?page=autoglot_translation_settings'));
                    echo '</div></div>';
                }
                else {
                    echo '<div class="postbox"><div class="inside">';
                    printf('<p style="color:#cc0000">' .__( 'You have not set up your API key! Autoglot Translation Plugin will not translate your content without a correct API key.', 'autoglot' )."<br /><br />".__('You can get your API key in your <a href="%s" target="_blank">Autoglot Control Panel</a>.', 'autoglot')."<br /><br />".__('Please then set your API key in <a href="%s">Autoglot Settings Page</a>.', 'autoglot') . '</p>', esc_url(AUTOGLOT_CP_URL), admin_url( 'admin.php?page=autoglot_translation_settings'));
                    echo '</div></div>';
                }

                echo '<h2>'.__('Autoglot Plugin Stats', 'autoglot').'</h2>';
				echo '<p>' . __('Status of Autoglot plugin.', 'autoglot') . '</p>';
                echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                echo '<strong>' . __('Autoglot plugin status', 'autoglot') . '</strong>';
                echo '</td><td></td></tr></thead><tbody>';
                printf('<tr><td>'.(!$this->autoglot->curl->getConnected() || !strlen($this->autoglot->options->translation_API_key)?'<span class="autoglot-bubble-red">!</span> ':'') . __('Valid API key:', 'autoglot') . '</td>'.($this->autoglot->curl->getConnected() && strlen($this->autoglot->options->translation_API_key)?'<td style="background-color:#99FF99"><strong><i class="dashicons dashicons-yes"></i></strong></td></tr>':'<td style="background-color:#FF9999"><strong><i class="dashicons dashicons-no"></i></strong> <a href="%s">'.__('Please set up your API key here.', 'autoglot').'</a></td></tr>').'', admin_url( 'admin.php?page=autoglot_translation_settings'));
                printf('<tr><td>'.(!$this->autoglot->options->translation_enable?'<span class="autoglot-bubble-red">!</span> ':'') . __('New translations active:', 'autoglot') . '</td>'.($this->autoglot->options->translation_enable?'<td style="background-color:#99FF99"><strong><i class="dashicons dashicons-yes"></i></strong></td></tr>':'<td style="background-color:#FF9999"><strong><i class="dashicons dashicons-no"></i></strong> <a href="%s">'.__('Click here to enable translation.', 'autoglot').'</a></td></tr>').'', admin_url( 'admin.php?page=autoglot_translation_settings'));
                printf('<tr><td>'.($this->autoglot->options->translation_adminonly?'<span class="autoglot-bubble-red">!</span> ':'') . __('Translation available to every visitor:', 'autoglot') . '</td>'.(!$this->autoglot->options->translation_adminonly ?'<td style="background-color:#99FF99"><strong><i class="dashicons dashicons-yes"></i></strong></td></tr>':'<td style="background-color:#FF9999"><strong><i class="dashicons dashicons-no"></i></strong> '.__('Translation is visible for administrator only.', 'autoglot').' <a href="%s">'.__('Click here to enable translation for everyone.', 'autoglot').'</a></td></tr>').'', admin_url( 'admin.php?page=autoglot_translation_settings'));
                printf('<tr><td>'.(count($this->autoglot->options->active_languages)-$this->autoglot->options->translation_enable<1?'<span class="autoglot-bubble-red">!</span> ':'') . __('Translation languages enabled:', 'autoglot') . '</td>'.(count($this->autoglot->options->active_languages)-$this->autoglot->options->translation_enable>=1?'<td style="background-color:#99FF99"><strong><i class="dashicons dashicons-yes"></i></strong></td></tr>':'<td style="background-color:#FF9999"><strong><i class="dashicons dashicons-no"></i></strong> <a href="%s">'.__('Click here to activate languages.', 'autoglot').'</a></td></tr>').'', admin_url( 'admin.php?page=autoglot_translation_languages'));
                printf('<tr><td>'.(!is_active_widget(false, false, 'autoglot_custom_widget') && !$this->autoglot->options->floatbox_enable ? '<span class="autoglot-bubble-red">!</span> ':'') . __('Language switcher enabled:', 'autoglot') . '</td><td style="background-color:'.(is_active_widget(false, false, 'autoglot_custom_widget') || $this->autoglot->options->floatbox_enable?'#99FF99':'#FF9999').'"><strong><i class="dashicons dashicons-yes"></i></strong> '.
                (is_active_widget(false, false, 'autoglot_custom_widget')?__('<a href="%1$s">Autoglot widget</a> is active.', 'autoglot'):(current_theme_supports('widgets')?__('<a href="%1$s">Autoglot widget</a> is not active.', 'autoglot'):__('Widgets are not supported.', 'autoglot'))).' '.
                ($this->autoglot->options->floatbox_enable?__('<a href="%s">Floating language switcher</a> is active.', 'autoglot'):__('<a href="%2$s">Floating language switcher</a> is not active.', 'autoglot')).'</td></tr>', admin_url( 'widgets.php'), admin_url( 'admin.php?page=autoglot_translation_settings'));
                echo '</tbody></table>';
                
                echo '<h2>'.__('Word Counter', 'autoglot').'</h2>';
				echo '<p>' . __('How many words do you need to translate?', 'autoglot') . '</p>';
                echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                echo '<strong>' . __('Word count information', 'autoglot') . '</strong>';
                echo '</td><td></td></tr></thead><tbody>';
                printf('<tr><td>' . __('Number of original words in WordPress posts and pages:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['wpcount']));
                printf('<tr><td>' . __('Number of active languages:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', count($this->autoglot->options->active_languages)-1);
                if(count($this->autoglot->options->active_languages)>1){
                    //printf('<tr><td>' . __('Number of translated words in currently active languages:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n(array_sum ($this->db_stats['countactivewords'])));
                    printf('<tr><td>' .($this->db_stats["w2translate"]>0?'<span class="autoglot-bubble-red">!</span> ':'') . __('Approximate number of words that should be translated to currently active languages:', 'autoglot') . '</td><td style="background-color:%s"><strong>%s</strong> *</td></tr>', ($this->db_stats["w2translate"]>0?"#FF9999":"#99FF99"), number_format_i18n($this->db_stats["w2translate"]));   
                }
                echo '</tbody></table>';
				echo '<p>&#42; ' . __('Please be aware, we cannot calculate 100% correct information about your word count. Your translation DB may include outdated records, which may interfere with these calculations. Also, there can be unspecified records in WordPress DB such as category descriptions, meta tags, etc. As a result, Autoglot may need to translate more words than calculated here.', 'autoglot') . '</p>';

                echo '<h2>'.__('Translation Stats', 'autoglot').'</h2>';
				echo '<p>' . __('Statistics of Autoglot plugin usage.', 'autoglot') . '</p>';
                echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                echo '<strong>' . __('Translation DB stats', 'autoglot') . '</strong>';
                echo '</td><td></td></tr></thead><tbody>';
                printf('<tr><td>' . __('Number of records in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['countall']));
                printf('<tr><td>' . __('Number of unique phrases in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['countunique']));
                printf('<tr><td>' . __('Number of all translated words in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['countwords']));
                printf('<tr><td>' . __('Number of unique languages in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', $this->db_stats['countlang']);
                if(isset($this->db_stats['size']))printf('<tr><td>' . __('Total size of Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', autoglot_utils::format_bytes($this->db_stats['size']));
                printf('<tr><td>' . __('Most recent translation:', 'autoglot') . '</td><td><strong>%1s</strong> - <strong>%2s</strong></td></tr>', $this->db_stats['recent_d'], $this->db_stats['recent_l']);
                echo '</tbody></table>';
				echo '<p>' . __('Statistics of Autoglot plugin settings.', 'autoglot') . '</p>';
                echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                echo '<strong>' . __('Plugin settings stats', 'autoglot') . '</strong>';
                echo '</td><td></td></tr></thead><tbody>';
                printf('<tr><td>' . __('Number of all active languages (including default):', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', count($this->autoglot->options->active_languages));
                printf('<tr><td>' . __('Number of all available languages:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', count(autoglot_utils::get_all_language_names()));
                printf('<tr><td>' . __('Plugin version:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', AUTOGLOT_PLUGIN_VER);
                echo '<tr><td>' . __('Active languages:', 'autoglot') . '</td><td>';
                $showlangs = array();
                foreach($this->autoglot->options->active_languages as $lang) if($lang!=$this->autoglot->options->default_language){
                    $showlangs[] = '<a href="'.autoglot_utils::add_language_to_url(home_url(),home_url(),$lang).'" target="_blank">'.autoglot_utils::get_language_original_name($lang).'</a>';
                }
                echo implode(", ",$showlangs);
                echo '</td></tr>';
                echo '</tbody></table><br /><br />';

                echo '<div class="postbox"><div class="inside">';
                echo '<h2>'.__('Your Feedback', 'autoglot').'</h2>';
				echo '<hr><p>' . __('Your feedback provides us with key information about what you think as a user of our plugin and helps us make informed decisions about future enhancements; it also helps us identify area where we are doing a good job and where we need to improve.', 'autoglot') . '</p>';
				echo '<p>' . __('If you enjoy our plugin, please do not hesitate to rate us and submit your feedback. It\'s always much appreciated!', 'autoglot') . '</p>';
				printf('<a href="%s" target="_blank" class="button"><i class="dashicons dashicons-star-filled"></i> ' . __('Rate this plugin', 'autoglot'). '</a>', esc_url(AUTOGLOT_WP_REVIEWS));
				echo '</div></div>';


                echo '					</div>
					<div id="postbox-container-1" class="postbox-container">';

                echo '<div class="postbox"><div class="inside">';
                echo '<h2>'.__('About Autoglot', 'autoglot').'</h2>';
				echo '<hr><p>' . __('Autoglot is a plugin for a WordPress platform that makes your website or blog SEO-friendly multilingual and translates all your content automatically using the best neural machine translation solutions.', 'autoglot') . '</p>';
				echo '<hr><p>' . __('Learn more about Autoglot using the links below:', 'autoglot'). '</p>';
                echo '<ul>';
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-admin-home"></i> ' . __('Official Website', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_MAIN_URL));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-money-alt"></i> ' . __('Pricing Information', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_PRICING_URL));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-analytics"></i> ' . __('Control Panel', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_CP_URL));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-wordpress"></i> ' . __('Official Documentation', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_WP_URL));
                echo '</ul>';
                echo '</div></div>';

                echo '<div class="postbox"><div class="inside">';
                echo '<h2>'.__('Autoglot Support', 'autoglot').'</h2>';
				echo '<hr><p>' . __('Do you need some help with our plugin? Or you may want to ask us a question, offer an idea, or request some assistance.', 'autoglot') . '</p>';
				echo '<p>' . __('Our support team is always eager to help you get the most out of Autoglot plugin by answering your support questions, preventing possible issues, and helping you resolve all technical questions.', 'autoglot') . '</p>';
//				echo '<p>' . __('Sometimes we are unable to resolve a compatibility problem brought on by a third-party theme or plugin. We will, though, try our best to provide you with alternatives or recommend options that will help you complete your task.', 'autoglot') . '</p><hr>';
				echo '<hr><p>' . __('There are a few channels to request help from us:', 'autoglot') . '</p>';
                echo '<ul>';
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-bell"></i> ' . __('Support Ticket', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_CP_SUPPORT));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-groups"></i> ' . __('WordPress Forums', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_WP_SUPPORT));
//				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-email"></i> ' . __('Contact Form', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_CONTACT_URL));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-facebook-alt"></i> ' . __('Facebook Page', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_FB_URL));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-twitter-alt"></i> ' . __('Twitter Profile', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_TW_URL));
				printf('<li><a href="%s" target="_blank" class="button"><i class="dashicons dashicons-linkedin"></i> ' . __('LinkedIn Community', 'autoglot'). '</a></li>', esc_url(AUTOGLOT_LI_URL));

				echo '</ul></div></div>';

                echo '					</div>
				        </div> <!-- #post-body -->
                    </div> <!-- #poststuff -->
            		</form>';
				break;
/*			case 'section_about':
                echo '<div class="postbox"><div class="inside">';
				echo '<p>' . __('Autoglot is a plugin for a WordPress platform that makes your website or blog SEO-friendly multilingual and translates all your content automatically using the best neural machine translation solutions.', 'autoglot') . '</p>';
				printf('<p>' . __('Learn more about Autoglot in our official website: <a href="%s" target="_blank">Autoglot.com</a>', 'autoglot'). '</p>', esc_url(AUTOGLOT_MAIN_URL));
                echo '</div></div>';
				break;

			case 'section_account':
                if($this->autoglot->curl->getConnected()){
//					echo '<p>' . __('Statistics of your Autoglot account.', 'autoglot') . '</p>';
                    echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                    echo '<strong>' . __('Translation balance', 'autoglot') . '</strong>';
                    echo '</td><td></td></tr></thead><tbody>';
                    printf('<tr><td>' . __('Your current Autoglot translation balance (number of words you can translate):', 'autoglot') . '</td><td'.($this->balance?($this->balance>AUTOGLOT_LOW_BALANCE?" style='background-color:#99FF99'":" style='background-color:#FFDD99'"):" style='background-color:#FF9999'").'><strong>%s</strong></td></tr>', number_format_i18n($this->balance,0));
                    echo '</tbody></table>';
    				printf('<p>' . __('You can replenish your translation balance in your <a href="%s" target="_blank">Autoglot Control Panel</a>.', 'autoglot') . '</p>', esc_url(AUTOGLOT_CP_URL_ORDER));
                }
                elseif(strlen($this->autoglot->options->translation_API_key)) {
                    printf('<p style="color:#cc0000">' . __( 'We could not connect to Autoglot API with your API key. ', 'autoglot' ).__('Please login to <a href="%s" target="_blank">Autoglot Control Panel</a> and retrieve your API key.', 'autoglot'). '</p>', esc_url(AUTOGLOT_CP_URL));
                }
                else {
                    printf('<p style="color:#cc0000">' .__( 'You have not set up your API key! Autoglot Translation Plugin will not translate your content without a correct API key.', 'autoglot' )."<br /><br />".__('You can get your API key in your <a href="%s" target="_blank">Autoglot Control Panel</a>.', 'autoglot')."<br /><br />".__('Please then set you API key in <a href="%s">Autoglot Settings Page</a>.', 'autoglot') . '</p>', esc_url(AUTOGLOT_CP_URL), admin_url( 'admin.php?page=autoglot_translation_settings'));

                }
				break;

			case 'section_stats':
//				echo '<p>' . __('Statistics of Autoglot plugin usage.', 'autoglot') . '</p>';
                echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                echo '<strong>' . __('Translation DB stats', 'autoglot') . '</strong>';
                echo '</td><td></td></tr></thead><tbody>';
                $this->db_stats = $this->autoglot->autoglot_database->db_stats();
                printf('<tr><td>' . __('Number of records in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['countall']));
                printf('<tr><td>' . __('Number of unique phrases in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['countunique']));
                printf('<tr><td>' . __('Number of translated words in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', number_format_i18n($this->db_stats['countwords']));
                printf('<tr><td>' . __('Number of unique languages in Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', $this->db_stats['countlang']);
                if(isset($this->db_stats['size']))printf('<tr><td>' . __('Total size of Autoglot translation DB:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', autoglot_utils::format_bytes($this->db_stats['size']));
                printf('<tr><td>' . __('Most recent translation:', 'autoglot') . '</td><td><strong>%1s</strong> - <strong>%2s</strong></td></tr>', $this->db_stats['recent_d'], $this->db_stats['recent_l']);
                echo '</tbody></table><br />';
                echo '<table class="wp-list-table widefat fixed"><thead><tr><td>';
                echo '<strong>' . __('Plugin settings stats', 'autoglot') . '</strong>';
                echo '</td><td></td></tr></thead><tbody>';
                printf('<tr><td>' . __('Number of active languages (including default):', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', count($this->autoglot->options->active_languages));
                printf('<tr><td>' . __('Number of all available languages:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', count(autoglot_utils::get_all_language_names()));
                printf('<tr><td>' . __('Plugin version:', 'autoglot') . '</td><td><strong>%s</strong></td></tr>', AUTOGLOT_PLUGIN_VER);
                echo '</tbody></table>';
				break;

			case 'section_support':
                echo '<div class="postbox"><div class="inside">';
				echo '<p>' . __('Do you think you have a problem with our plugin and need our help? Or you may want to ask us a question, offer a solution, or request some help.', 'autoglot') . '</p>';
				echo '<p>' . __('Our support team is always eager to help you get the most out of Autoglot plugin by answering your support questions, resolving possible issues, and helping you answer all technical questions.', 'autoglot') . '</p>';
				echo '<p>' . __('Sometimes we are unable to resolve a compatibility problem brought on by a third-party theme or plugin. We will, though, try our best to provide you with alternatives or recommend options that will help you complete your task.', 'autoglot') . '</p><hr>';
				echo '<strong>' . __('There are a few channels to request help from us:', 'autoglot') . '</strong>';
                echo '<ol>';
				printf('<li>' . __('Use contact form in <a href="%s" target="_blank">our official website</a>', 'autoglot'). '</li>', esc_url(AUTOGLOT_MAIN_URL));
				printf('<li>' . __('Submit support ticket in <a href="%s" target="_blank">your control panel</a>', 'autoglot'). '</li>', esc_url(AUTOGLOT_CP_URL));
				printf('<li>' . __('Join support forums in <a href="%s" target="_blank">WordPress plugin repository</a>', 'autoglot'). '</li>', esc_url(AUTOGLOT_WP_URL));
				printf('<li>' . __('Find help in <a href="%s" target="_blank">our Facebook page</a>', 'autoglot'). '</li>', esc_url(AUTOGLOT_FB_URL));
				printf('<li>' . __('Join <a href="%s" target="_blank">our LinkedIn community</a>', 'autoglot'). '</li>', esc_url(AUTOGLOT_LI_URL));
				echo '</ol></div></div>';
				break;
*/
			case 'section_main':
				echo '<p>' . __('These are settings for the basic configuration of Autoglot plugin.', 'autoglot') . '</p>';
				break;

			case 'section_switcher':
                echo '<p><strong>1. ' . __('Widget', 'autoglot') . '</strong></p>';
                if(current_theme_supports('widgets')){
    				echo '<p>' . __('Autoglot provides a useful widget that lets your visitors switch languages and open appropriate version of your website. You can find our widget in your WordPress widget area by searching for "Autoglot".', 'autoglot') . '</p>';
                    printf(__( '<a href="%s" class="button" target="_blank"><i class="dashicons dashicons-screenoptions"></i> Setup Autoglot Widget</a>', 'autoglot'),
                    admin_url( 'widgets.php'));
                } else {
                    echo '<p>' . __('<strong>Your theme currently does not support widgets.</strong> No worries, Autoglot will function without widgets area. You may add language switcher in popup or as a shortcode via Autoglot Dashboard.', 'autoglot') . '</p>';
                }
                
                echo '<p><strong>2. ' . __('Shortcode', 'autoglot') . '</strong></p>';
                printf('<p>' . __('Alternatively, you can use a <code>%s</code> shortcode to add a language switcher to your website posts, pages, popups, etc.', 'autoglot') . '</p>', '[ag_switcher]');
                printf('<p>' . __('Add a "%s" argument if you want to set a custom title of this box: <code>%s</code>.', 'autoglot') . '</p>', 'title', '[ag_switcher title="Website Translation"]');
                printf('<p>' . __('Use a "%s" argument in order to select a type of language switcher:', 'autoglot') . '</p>', 'type');
                echo "<ol>"; 
                printf('<li>' . __('<code>%s</code> &ndash; small flags.', 'autoglot') . '</li>', '[ag_switcher type="smallflagslist"]');
                printf('<li>' . __('<code>%s</code> &ndash; large flags.', 'autoglot') . '</li>', '[ag_switcher type="flagslist"]');
                printf('<li>' . __('<code>%s</code> &ndash; list of languages with flags.', 'autoglot') . '</li>', '[ag_switcher type="languageflagslist"]');
                printf('<li>' . __('<code>%s</code> &ndash; list of languages without flags (default).', 'autoglot') . '</li>', '[ag_switcher type="languagelist"]');
                echo "</ol>"; 

                echo '<p><strong>3. ' . __('Popup', 'autoglot') . '</strong></p>';
  				echo '<p>' . __('Finally, you can enable a popup language switcher. This will add a floating box to your website. By clicking on this box, users will see a popup window with language switcher. This is the best solution if you don\'t want to add widgets or shortcodes to your website.', 'autoglot') . '</p>';
				break;

			case 'section_lang':
				echo '<p>' . __('Please setup your languages here.', 'autoglot') . '</p>';
				break;

			case 'section_langnames':
				echo '<p>' . __('Please choose how to display language names.', 'autoglot') . '</p>';
				break;

			case 'section_flags':
				echo '<p>' . __('Please choose the most appropriate flags for a language switcher.', 'autoglot') . '</p>';
				break;

			case 'section_adv_trans':
				echo '<p>' . __('These are advanced translation settings. Please use with caution!', 'autoglot') . '</p>';
				break;

			case 'section_adv_out':
				echo '<p>' . __('These are advanced output settings. Please use with caution!', 'autoglot') . '</p>';
				break;

			case 'section_editor':
				echo '<p>' . __('"Translation Editor" tool in Autoglot plugin lets you manually modify translations.', 'autoglot') . ' ';
				echo '' . __('Search for content in "Translated" and "Original" fields and filter by language.', 'autoglot') . ' ';
				echo '' . __('Delete translation in order to automatically generate a new one; or click on "Quick Edit" to edit the translation.', 'autoglot') . '</p>';
				echo '<p>' . __('Please be careful when updating translated content and make sure you keep all HTML tags and attributes! All "agtr" attributes will be replaced by attributes from original strings.', 'autoglot') . '</p>';
				$this->editor();
				break;

			case 'section_linksmod':
				echo '<p>' . __('You can add current language code to any link or text on your website. Default language does not update or replace any link.', 'autoglot') . '</p>';
				printf('<p>' . __('The <code>%s</code> shortcode in your updated link will be replaced by current language code e.g. "es", "de", "el", "da", "sv".', 'autoglot') . '</p>', esc_html(AUTOGLOT_ADDLINKCODE_LNG));
				printf('<p>' . __('The <code>%s</code> shortcode in your updated link will be replaced by top level domain extension of the country e.g. "es", "de", "gr", "dk", "se", etc.', 'autoglot') . '</p>', esc_html(AUTOGLOT_ADDLINKCODE_DMN));
				printf('<p>' . __('For example, if you want to replace all links to wikipedia.com to the corresponding pages of es.wikipedia.org on the Spanish version of your website, you would enter <code>en.wikipedia.com</code> in the "Original Link" box and <code>%s.wikipedia.com</code> in the "Updated Link" box.', 'autoglot') . '</p>', esc_html(AUTOGLOT_ADDLINKCODE_LNG));
				echo '<p>' . __('As a result, a link to <ins>https://en.wikipedia.org/wiki/WordPress</ins> in your English version will be replaced with <ins>https://es.wikipedia.org/wiki/WordPress</ins> on the Spanish version of your website; <ins>https://de.wikipedia.org/wiki/WordPress</ins> on the German version, etc.', 'autoglot') . '</p>';
				echo '<p><em>' . __('* This will not update your original blog posts. Links will be replaced only before the output on non-default languages.', 'autoglot') . '</em></p>';
				echo '<p><em>' . __('* These shortcodes work only for these settings. They will not work anywhere else and cannot be used on your blog posts.', 'autoglot') . '</em></p>';
				echo '<p><em>' . __('* Make sure to use the correct text in "Original Link" box. If you enter <code>wikipedia.com</code>, you will have <ins>en.es.wikipedia.com</ins> on your website. Correct option is <code>en.wikipedia.com</code>.', 'autoglot') . '</em></p>';
				break;

			case 'section_utilities':
                $db_check = $this->autoglot->autoglot_database->db_utilities_check();
				echo '<p>' . __('Use these utilities for plugin maintainance.', 'autoglot') . '</p>';
				echo '<ol>';
				echo '<li>';
                if(isset($db_check['countempty']) && $db_check['countempty']>0){
    				printf('<strong>' . __('We found %d empty translation records in DB. You can safely remove them for better DB performance.', 'autoglot') . '</strong>',$db_check['countempty']);
                    echo '<div style="margin:10px 0"><a id="autoglot_delete_empty_translation" href="'.admin_url( 'admin.php?page=autoglot_translation_delete_empty').'" class="button">' . __('Delete empty translation from DB', 'autoglot') . '</a></div>';
    				echo '<p><em>' . __('This will remove all empty translation records from DB.', 'autoglot') . '</em></p>';
                }else {
    				echo '<strong>' . __('No empty translations found in DB. This is great!', 'autoglot') . '<br /><br /></strong>';
                }
				echo '</li>';
				echo '<li>';
                if(isset($db_check['countduplicate']) && $db_check['countduplicate']>0){
    				printf('<strong>' . __('We found %d duplicate translation records in DB. You can safely remove them for better DB performance.', 'autoglot') . '</strong>',$db_check['countduplicate']);
                    echo '<div style="margin:10px 0"><a id="autoglot_delete_duplicate_translation" href="'.admin_url( 'admin.php?page=autoglot_translation_delete_duplicate').'" class="button">' . __('Delete duplicate translation from DB', 'autoglot') . '</a></div>';
	       			echo '<p><em>' . __('Sometimes, duplicate translation records may appear due to poor connection between WordPress and Autoglot servers. This will remove all duplicates except for the most recent one.', 'autoglot') . '</em></p>';
                }else {
    				echo '<strong>' . __('No duplicate translations found in DB. This is great!', 'autoglot') . '<br /><br /></strong>';
                }
				echo '</li>';
				echo '<li>';
                echo '<strong>' . __('Backup Translations Table', 'autoglot') . '</strong>';
                echo '<div style="margin:10px 0"><a id="autoglot_backup_table" href="'.admin_url( 'admin.php?page=autoglot_translation_backup_table').'" class="button">' . __('Backup translations', 'autoglot') . '</a></div>';
                echo '<p><em>' . __('This will generate an SQL file with the backup of your translation table. This may take some time and require server resources! Keep in cool and dry place.', 'autoglot') . '</em></p>';
				echo '</li>';
/*				echo '<li>';
                echo '<strong>' . __('Custom DB update', 'autoglot') . '</strong>';
                echo '<div style="margin:10px 0"><a id="autoglot_utilities_reserved" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities_reserved').'" class="button">' . __('Custom DB update', 'autoglot') . '</a></div>';
				echo '</li>';*/
				echo '</ol>';
				break;

			case 'section_delete_empty':
				$this->delete_empty();
				break;

			case 'section_delete_duplicate':
				$this->delete_duplicate();
				break;
                
            case 'section_utilities_reserved':
				$this->utilities_reserved();
				break;
              
            case 'section_backup_table':
				$this->backup_table();
				break;
                
            default:
                break;
		}
	}

	/**
	 * Field Configuration, each item in this array is one field/setting we want to capture
	 */
	public function setup_fields() {

        if(isset($this->setup_wizard) && in_array($this->setup_wizard, array(1,2,3))){
            switch($this->setup_wizard){
                case 3:
                    $fields = array();
                break;
                case 2:
            		$fields = array(
            			array(
            				'uid' => 'autoglot_translation_default_language',
            				'label' => __('Default Language', 'autoglot'),
            				'section' => 'section_setup2',
                            'page' => 'autoglot_translation_setup',
            				'supplemental' => __('Please choose a default language of your site. We will not add language code to the URL of default language.', 'autoglot'),
            				'type' => 'select',
            				'options' => autoglot_utils::get_all_language_names(),
            				'default' => array(autoglot_utils::get_locale_code()),
                            'sanitize_callback' => array($this, 'sanitize_default_language'),
            			),
            			array(
                			'uid' => 'autoglot_translation_active_languages',
                			'label' => __('Choose Active Languages', 'autoglot'),
                			'section' => 'section_setup2',
                            'page' => 'autoglot_translation_setup',
                			'type' => 'checkbox',
                			'options' => autoglot_utils::get_all_language_names($this->autoglot->options->default_language),
            				'default' => array(),
                            'sanitize_callback' => array($this, 'sanitize_active_languages'),
                        )
                    );
                break;
                case 1:
                default:
            		$fields = array(
            			array(
            				'uid' => 'autoglot_translation_API_key',
            				'label' => __('Autoglot API key', 'autoglot'),
            				'section' => 'section_setup1',
                            'page' => 'autoglot_translation_setup',
            				'type' => 'text',
            				'placeholder' => __('Your API key', 'autoglot'),
            				'supplemental' => sprintf(__('You can get your free API key in your <a href="%s" target="_blank">Autoglot Control Panel</a>', 'autoglot'), AUTOGLOT_CP_URL),
            				//'supplemental' => '',
            				'default' => "",
                            'sanitize_callback' => array($this, 'sanitize_api_key'),
            			),
            			array(
            				'uid' => 'autoglot_translation_enable',
            				'label' => __('Translate New Content', 'autoglot'),
            				'section' => 'section_setup1',
                            'page' => 'autoglot_translation_setup',
            				'type' => 'checkbox',
            				'helper' => '',
            				'supplemental' => __('Enable translation of new content. You can pause translation of new content to prevent excess charges. When not enabled, Autoglot will not translate new content but all existing translations will be shown.', 'autoglot') . ' ' . __('By default, only signed-in administrator will see the translation.', 'autoglot'),
            				'options' => array(
            					'selected' => __('Enable translation', 'autoglot'),
            				),
            				'default' => array("selected"),
                            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            			),
            			array(
            				'uid' => 'autoglot_translation_floatbox',
            				'label' => __('Enable Language Switcher', 'autoglot'),
            				'section' => 'section_setup1',
                            'page' => 'autoglot_translation_setup',
            				'type' => 'radio',
            				//'supplemental' => __('You can adjust custom CSS with id #ag_floatblox.', 'autoglot'),
            				'options' => array(
            					1 => __('Enable language switcher popup.', 'autoglot'),
            					0 => __('Disable language switcher popup. You can still use widgets and shortcodes.', 'autoglot'),
            				),
            				'default' => array(1),
                            'sanitize_callback' => array($this, 'sanitize_radio'),
            			),
                    );
                break;
                
            }
        } else {

            $translatedelays = array(
                60=>__('1 minute', 'autoglot'), 
                600=>__('10 minutes', 'autoglot'),
                1800=>__('30 minutes', 'autoglot'), 
                3600=>__('1 hour', 'autoglot'), 
                43200=>__('12 hours', 'autoglot'),
                86400=>__('1 day', 'autoglot'),
                604800=>__('1 week', 'autoglot'),
                2592000=>__('1 month', 'autoglot'),
            );
            $language_switcher_options = array(
				'languagelist' => __( 'List of Languages', 'autoglot'),
				'languageflagslist' => __( 'List of Languages and Flags', 'autoglot'),
				'flagslist' => __( 'Box with Flags', 'autoglot'),
				'smallflagslist' => __( 'Box with Small Flags', 'autoglot'),
			);
            $language_name_options = array(
				'nativeenglish' => __( 'Native name (English name)', 'autoglot'),
				'native' => __( 'Native name', 'autoglot'),
				'english' => __( 'English name', 'autoglot'),
				'englishnative' => __( 'English name (Native name)', 'autoglot'),
				'iso' => __( '2-letter ISO code', 'autoglot'),
				'nativeiso' => __( 'Native name (2-letter ISO code)', 'autoglot'),
			);
            
    		$fields = array(
    			array(
    				'uid' => 'autoglot_translation_enable',
    				'label' => __('Translate New Content', 'autoglot'),
    				'section' => 'section_main',
                    'page' => 'autoglot_translation_settings',
    				'type' => 'checkbox',
    				'helper' => '',
    				'supplemental' => __('Enable translation of new content. You can pause translation of new content to prevent excess charges. When not enabled, Autoglot will not translate new content but all existing translations will be shown.', 'autoglot'),
    				'options' => array(
    					'selected' => __('Enable translation', 'autoglot'),
    				),
    				'default' => array(""),
                    'sanitize_callback' => array($this, 'sanitize_checkbox'),
    			),
    			array(
    				'uid' => 'autoglot_translation_adminonly',
    				'label' => __('Translation for Admin Only', 'autoglot'),
    				'section' => 'section_main',
                    'page' => 'autoglot_translation_settings',
    				'helper' => '',
    				'supplemental' => __('This can be useful to check translation by admin before publishing it for all visitors. This will hide "alternate hreflang" block and will make widget visible only for admin.', 'autoglot'),
    				'type' => 'radio',
    				'options' => array(
    					1 => __('Site admin will be the only one to see and use Autoglot translation.', 'autoglot'),
    					0 => __('Translation will be available to every user.', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_ADMINONLY),
                    'sanitize_callback' => array($this, 'sanitize_radio'),
    			),
    			array(
    				'uid' => 'autoglot_translation_API_key',
    				'label' => __('Autoglot API key', 'autoglot'),
    				'section' => 'section_main',
                    'page' => 'autoglot_translation_settings',
    				'type' => 'text',
    				'placeholder' => __('Your API key', 'autoglot'),
    				'helper' => sprintf(__('You can get your API key in your <a href="%s" target="_blank">Autoglot Control Panel</a>', 'autoglot'), AUTOGLOT_CP_URL),
    				'supplemental' => '',
    				'default' => "",
                    'sanitize_callback' => array($this, 'sanitize_api_key'),
    			),
    			array(
    				'uid' => 'autoglot_translation_floatbox',
    				'label' => __('Enable Floating Language Switcher', 'autoglot'),
    				'section' => 'section_switcher',
                    'page' => 'autoglot_translation_settings',
    				'type' => 'radio',
    				'supplemental' => __('You can adjust custom CSS with id #ag_floatblox.', 'autoglot'),
    				'options' => array(
    					1 => __('Enable floating box that will open language switcher popup.', 'autoglot'),
    					0 => __('Disable floating box. You can still use widgets and shortcodes.', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_FLOATBOX_SWITCHER),
                    'sanitize_callback' => array($this, 'sanitize_radio'),
    			),
    			array(
    				'uid' => 'autoglot_translation_popup_switcher',
    				'label' => __('Type of Language Switcher in Popup', 'autoglot'),
    				'section' => 'section_switcher',
                    'page' => 'autoglot_translation_settings',
    				'supplemental' => __('Type of language switcher in popup.', 'autoglot')."<br /><span class='autoglot-bubble-green'>!</span><br />".__('If you do not see language switcher on your website, please start with clearing your cache.','autoglot'),
    				'type' => 'select',
    				'options' => $language_switcher_options,
    				'default' => array(autoglot_consts::LANGUAGE_SWITCHER_TYPES[0]),
                    'sanitize_callback' => array($this, 'sanitize_language_switcher'),
    			),
    			array(
    				'uid' => 'autoglot_translation_default_language',
    				'label' => __('Default Language', 'autoglot'),
    				'section' => 'section_lang',
                    'page' => 'autoglot_translation_languages',
    				'supplemental' => __('Please choose a default language of your site. We will not add language code to the URL of default language.', 'autoglot'),
    				'type' => 'select',
    				'options' => autoglot_utils::get_all_language_names(),
    				'default' => array(autoglot_utils::get_locale_code()),
                    'sanitize_callback' => array($this, 'sanitize_default_language'),
    			),
    			array(
        			'uid' => 'autoglot_translation_active_languages',
        			'label' => __('Choose Active Languages', 'autoglot'),
        			'section' => 'section_lang',
                    'page' => 'autoglot_translation_languages',
        			'type' => 'checkbox',
        			'options' => autoglot_utils::get_all_language_names($this->autoglot->options->default_language),
    				'default' => array(),
                    'sanitize_callback' => array($this, 'sanitize_active_languages'),
                ),
    			array(
    				'uid' => 'autoglot_translation_language_names',
    				'label' => __('How to Display Language Names', 'autoglot'),
    				'section' => 'section_langnames',
                    'page' => 'autoglot_translation_languages',
    				'supplemental' => __('Show language names in native languages, English, as ISO code, or combination.', 'autoglot'),
    				'type' => 'select',
    				'options' => $language_name_options,
    				'default' => array(autoglot_consts::LANGUAGE_NAME_TYPES[0]),
                    'sanitize_callback' => array($this, 'sanitize_language_names'),
    			),
    			array(
    				'uid' => 'autoglot_translation_manual_strings',
    				'label' => __('Additional Strings for Translation', 'autoglot'),
    				'section' => 'section_adv_trans',
                    'page' => 'autoglot_translation_advanced',
    				'placeholder' => __('String that is not translated.', 'autoglot'),
    				'helper' => '',
        			'supplemental' => __('Please enter strings that are not automatically translated. They can be hard-coded in plugins, themes, or even functions. Please include all HTML code, if necessary.', 'autoglot'),
    				'type' => 'textarea',
    				'default' => "",
                    'sanitize_callback' => "wp_filter_post_kses",
    			),
    			array(
    				'uid' => 'autoglot_translation_repeat_balance',
    				'label' => __('Resend Low Balance Notifications', 'autoglot'),
    				'section' => 'section_adv_trans',
                    'page' => 'autoglot_translation_advanced',
    				'supplemental' => __('How often to send low balance notifications to admin e-mail. Minimum delay before resending a notification.', 'autoglot'),
    				'type' => 'select',
    				'options' => array_replace(autoglot_consts::REPEAT_BALANCE_NOTIFICATIONS,array_intersect_key($translatedelays,autoglot_consts::REPEAT_BALANCE_NOTIFICATIONS)),
                    //(function($a, $t){return isset($t[$a])?$t[$a]:$a;}, autoglot_consts::REPEAT_BALANCE_NOTIFICATIONS, $translatedelays),
    //                array_combine(autoglot_consts::REPEAT_BALANCE_NOTIFICATIONS,$translatedelays),
    				'default' => array(AUTOGLOT_DEFAULT_NOTIFY_BALANCE),
                    'sanitize_callback' => array($this, 'sanitize_balance_notifications'),
    			),
    			array(
    				'uid' => 'autoglot_translation_translate_urls',
    				'label' => __('Translate URLs', 'autoglot'),
    				'section' => 'section_adv_trans',
                    'page' => 'autoglot_translation_advanced',
    				'helper' => '',
    				'supplemental' => __('Enable/disable translation of URLs (links to posts and pages). If enabled, Autoglot will translate all WordPress permalinks, e.g. <code>http://site.com/page/</code> to <code>http://site.com/fr/p&aacute;gina/</code>. It can also transliterate URLs to url-friendly format, e.g. <code>http://site.com/page/</code> to <code>http://site.com/fr/pagina/</code>', 'autoglot')."<br /><span class='autoglot-bubble-green'>!</span><br />".__('Please note, Translation and Transliteration of URLs will affect only URLs that have not been translated yet. If you need to update already translated URLs, you need to modify or delete them in Translation Editor.','autoglot'),
    				'type' => 'radio',
    				'options' => array(
    					'0' => __('Do not translate URLs', 'autoglot'),
    					'1' => __('Translate URLs', 'autoglot'),
    					'2' => __('Translate and transliterate URLs', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_WIDGET_SIGNATURE),
                    'sanitize_callback' => array($this, 'sanitize_radio2'),
    			),
    			array(
    				'uid' => 'autoglot_translation_hreflangs',
    				'label' => __('Show "alternate hreflang" Block', 'autoglot'),
    				'section' => 'section_adv_out',
                    'page' => 'autoglot_translation_advanced',
    				'helper' => '',
    				'supplemental' => __('Show section with "alternate hreflang" links to other languages on each page.', 'autoglot'),
    				'type' => 'radio',
    				'options' => array(
    					'1' => __('Show hreflangs', 'autoglot'),
    					'0' => __('Hide hreflangs', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_SHOW_HREFLANGS),
                    'sanitize_callback' => array($this, 'sanitize_radio'),
    			),
    			array(
    				'uid' => 'autoglot_translation_custom_titles',
    				'label' => __('Show custom HTML titles on certain pages', 'autoglot'),
    				'section' => 'section_adv_out',
                    'page' => 'autoglot_translation_advanced',
    				'helper' => '',
    				'supplemental' => __('Show custom (almost WordPress default) HTML titles on search results, categories, tags, taxonomies, and archive pages. These titles will be generated by Autoglot in order to minimize translation costs. Otherwise, each title will be translated for each tag, category, and even search request.', 'autoglot'),
    				'type' => 'radio',
    				'options' => array(
    					'1' => __('Show custom Autoglot titles', 'autoglot'),
    					'0' => __('Show default titles (by WordPress or SEO plugin)', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_CUSTOM_TITLES),
                    'sanitize_callback' => array($this, 'sanitize_radio'),
    			),
    			array(
    				'uid' => 'autoglot_translation_skip_caching',
    				'label' => __('Skip caching of translated pages', 'autoglot'),
    				'section' => 'section_adv_out',
                    'page' => 'autoglot_translation_advanced',
    				'helper' => '',
    				'supplemental' => __('This will try to prevent caching of translated pages in the most common caching plugins. This may be helpful if caching plugin saves and outputs original content instead of translated page. The options works only if caching plugin supports "DONOTCACHEPAGE" directive. Manually deleting cache might be required after switching this on.', 'autoglot'),
    				'type' => 'radio',
    				'options' => array(
    					'1' => __('If possible, do not cache translated pages', 'autoglot'),
    					'0' => __('Translated pages may be cached', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_SKIP_CACHING),
                    'sanitize_callback' => array($this, 'sanitize_radio'),
    			),
    			array(
    				'uid' => 'autoglot_translation_widget_signature',
    				'label' => __('Show Widget Signature', 'autoglot'),
    				'section' => 'section_adv_out',
                    'page' => 'autoglot_translation_advanced',
    				'helper' => '',
    				'supplemental' => __('Show "Powered by" widget signature with a link to official Autoglot.com website.', 'autoglot'),
    				'type' => 'radio',
    				'options' => array(
    					'1' => __('Show', 'autoglot'),
    					'0' => __('Hide', 'autoglot'),
    				),
    				'default' => array(AUTOGLOT_DEFAULT_WIDGET_SIGNATURE),
                    'sanitize_callback' => array($this, 'sanitize_radio'),
    			),
    			array(
    				'uid' => 'autoglot_translation_sitemap_priority',
    				'label' => __('Decrease Page Priorities in Sitemap', 'autoglot'),
    				'section' => 'section_adv_out',
                    'page' => 'autoglot_translation_advanced',
    				'supplemental' => __('Reduce translated page priority in sitemaps. Not compatible with every sitemap generation plugin.', 'autoglot'),
    				'type' => 'select',
    				'options' => array_combine($r = range(0,0.5,0.1), $r),
    				'default' => array(AUTOGLOT_DEFAULT_SITEMAP_PRIORITY),
                    'sanitize_callback' => array($this, 'sanitize_sitemap_priority'),
    			),
    			array(
    				'uid' => 'autoglot_translation_add_lngcode',
    				'label' => __('Links or Text For Replacement', 'autoglot'),
    				'section' => 'section_linksmod',
                    'page' => 'autoglot_translation_linksmod',
    				'helper' => '',
                    'toplabel' => array(__('Original Links or Text, one per line.', 'autoglot'), __('Replaced Links or Text, one per line', 'autoglot')),
    				'placeholder' => array("en.wikipedia.com\ngoogle.com", AUTOGLOT_ADDLINKCODE_LNG.".wikipedia.com\ngoogle.".AUTOGLOT_ADDLINKCODE_DMN),
    				'type' => 'textarea2',
    				'default' => array("",""),
                    'sanitize_callback' => array($this, 'sanitize_textarea2'),
    			),
    /*			array(
    				'uid' => 'autoglot_translation_password_example',
    				'label' => 'Sample Password Field',
    				'section' => 'section_main',
    				'type' => 'password',
    			),
    			array(
    				'uid' => 'autoglot_translation_number_example',
    				'label' => 'Sample Number Field',
    				'section' => 'section_lang',
    				'type' => 'number',
    			),
    			array(
    				'uid' => 'autoglot_translation_textarea_example',
    				'label' => 'Sample Text Area',
    				'section' => 'section_lang',
    				'type' => 'textarea',
    
    			array(
    				'uid' => 'autoglot_translation_select_example',
    				'label' => 'Sample Select Dropdown',
    				'section' => 'section_lang',
    				'type' => 'select',
    				'options' => array(
    					'option1' => 'Option 1',
    					'option2' => 'Option 2',
    					'option3' => 'Option 3',
    					'option4' => 'Option 4',
    					'option5' => 'Option 5',
    				),
    				'default' => array()
    			),
    			array(
    				'uid' => 'autoglot_translation_multiselect_example',
    				'label' => 'Sample Multi Select',
    				'section' => 'section_lang',
    				'type' => 'multiselect',
    				'options' => array(
    					'option1' => 'Option 1',
    					'option2' => 'Option 2',
    					'option3' => 'Option 3',
    					'option4' => 'Option 4',
    					'option5' => 'Option 5',
    				),
    				'default' => array()
    			),
    			array(
    				'uid' => 'autoglot_translation_radio_example',
    				'label' => 'Sample Radio Buttons',
    				'section' => 'section_lang',
    				'type' => 'radio',
    				'options' => array(
    					'option1' => 'Option 1',
    					'option2' => 'Option 2',
    					'option3' => 'Option 3',
    					'option4' => 'Option 4',
    					'option5' => 'Option 5',
    				),
    				'default' => array()
    			),
    			'default' => array()
    			)*/
    		);
            
            $allflags = autoglot_utils::get_all_language_flags();
            foreach($allflags as $lang => $options) {
                $fields[] = array(
        			'uid' => 'autoglot_translation_language_flags_'.$lang,
        			'label' => $options["name"],
        			'section' => 'section_flags',
                    'page' => 'autoglot_translation_languages',
        			'type' => 'select',
        			'options' => array_combine($options["flags"], array_map('strtoupper',$options["flags"])),
        			'addflags' => 1,
    				'default' => array($options["flags"][0]),
                    'sanitize_callback' => array($this, 'sanitize_language_flags'),
                );
            }
            

        }
		// Lets go through each field in the array and set it up
		foreach( $fields as $field ){
			add_settings_field( $field['uid'], $field['label'], array($this, 'field_callback'), $field['page'], $field['section'], $field );
			register_setting( $field['page'], $field['uid'], isset($field['sanitize_callback']) ? array("sanitize_callback" => $field['sanitize_callback']) : null);
		}
	}

	/**
	 * Check current Autoglot options and display warnings 
	 */
    function check_options() {
        global $pagenow;
        if ( $pagenow == 'admin.php' && strpos($_GET['page'], 'autoglot_translation')!==false && !$this->setup_wizard) {
            if(!strlen($this->autoglot->options->translation_API_key)){
                $this->admin_notice(sprintf(__( 'You have not set up your API key! Autoglot Translation Plugin will not translate your content without a correct API key.', 'autoglot' )."<br /><br />".__('You can get your API key in your <a href="%s" target="_blank">Autoglot Control Panel</a>.', 'autoglot')." ".__('Please then set you API key in <a href="%s">Autoglot Settings Page</a>.', 'autoglot'), esc_url(AUTOGLOT_CP_URL), admin_url( 'admin.php?page=autoglot_translation_settings')),"error");
            }
            elseif(!$this->autoglot->curl->getConnected()){
                $this->admin_notice(sprintf(__( 'We could not connect to Autoglot API with your API key and received the following response:', 'autoglot' )."<br /><br /><em>".$this->autoglot->curl->getResponse()."</em><br /><br />".__('Please login to <a href="%s" target="_blank">Autoglot Control Panel</a> and retrieve your API key.', 'autoglot').' '.__('Please then set you API key in <a href="%s">Autoglot Settings Page</a>.', 'autoglot'), esc_url(AUTOGLOT_CP_URL), admin_url( 'admin.php?page=autoglot_translation_settings')),"error");
            }
            elseif(!$this->balance) {
                $this->admin_notice(sprintf(__( 'Your Autoglot translation balance is empty. Please login to <a href="%s" target="_blank">Autoglot Control Panel</a> and replenish your translation balance.', 'autoglot'), esc_url(AUTOGLOT_CP_URL_ORDER)),"error");
                
            }elseif($this->balance < AUTOGLOT_LOW_BALANCE) {
                $this->admin_notice(sprintf(__( 'Your Autoglot translation balance is low. Please login to <a href="%s" target="_blank">Autoglot Control Panel</a> and replenish your translation balance.', 'autoglot'), esc_url(AUTOGLOT_CP_URL_ORDER)),"warning");
                
            }
        }
    }

	/**
	 * Add action links to admin plugin page
     *  
	 * @param array  $actions Array of links for the plugins, adapted when the current plugin is found.
	 * @param string $file  The filename for the current plugin, which the filter loops through.
	 */
    function add_action_links($actions, $plugin_file) {

        if (AUTOGLOT_PLUGIN_BASENAME == $plugin_file && !is_network_admin()) {
            if(strlen($this->autoglot->options->translation_API_key)) {
                $dashboard = array('dashboard' => '<a href="' . admin_url( 'admin.php?page=autoglot_translation' ) . '">'.__('Dashboard', 'autoglot').'</a>');
                $settings = array('settings' => '<a href="' . admin_url( 'admin.php?page=autoglot_translation_settings' ) . '">'.__('Settings', 'autoglot').'</a>');
                $languages = array('languages' => '<a href="' . admin_url( 'admin.php?page=autoglot_translation_languages' ) . '">'.__('Languages', 'autoglot').'</a>');
    
                $actions = array_merge( $languages, $actions );
                $actions = array_merge( $settings, $actions );
                $actions = array_merge( $dashboard, $actions );
            }
            else {
                $settings = array('settings' => '<a href="' . admin_url( 'admin.php?page=autoglot_translation_settings' ) . '" style="color:#3db634;">'.__('Set up your API key here to start', 'autoglot').'</a>');
                $actions = array_merge( $settings, $actions );

            }
        }        
        return $actions;
    }

	/**
	 * This handles all types of fields for the settings
	 */
	public function field_callback($arguments) {
		// Set our $value to that of whats in the DB
		$value = get_option( $arguments['uid'] );
		// Only set it to default if we get no value from the DB and a default for the field has been set
		if(!$value) {
			$value = $arguments['default'];
		}
		// Lets do some setup based ont he type of element we are trying to display.
		switch( $arguments['type'] ){
			case 'text':
			case 'password':
			case 'number':
				printf( '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" style="width: 250px;" />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], $value );
				break;
			case 'textarea':
				printf( '<textarea name="%1$s" id="%1$s" placeholder="%2$s" rows="7" cols="70">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], esc_textarea(wp_unslash($value)) );
				break;
			case 'textarea2':
				printf( '<span class="autoglot_spantxt2">%5$s<br /><textarea name="%1$s[]" id="%1$s_1" class="autoglot_txt2" placeholder="%2$s" rows="15" cols="40" wrap="off">%3$s</textarea><br />'. __("Total Number of Lines:", 'autoglot').' <span id="%1$s_1_lines">%4$s</span></span>', $arguments['uid'], $arguments['placeholder'][0], esc_textarea($value[0]), count(explode("\n", trim($value[0]))), $arguments['toplabel'][0]);
				printf( '<span class="autoglot_spantxt2">%5$s<br /><textarea name="%1$s[]" id="%1$s_2" class="autoglot_txt2" placeholder="%2$s" rows="15" cols="40" wrap="off">%3$s</textarea><br />'. __("Total Number of Lines:", 'autoglot').' <span id="%1$s_2_lines">%4$s</span></span>', $arguments['uid'], $arguments['placeholder'][1], esc_textarea($value[1]), count(explode("\n", trim($value[1]))), $arguments['toplabel'][1]);
				break;
			case 'select':
			case 'multiselect':
				if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
					$attributes = '';
					$options_markup = '';
                    $class = '';
					foreach( $arguments['options'] as $key => $label ){
						$options_markup .= sprintf( '<option value="%s" %s>%s</option>', $key, selected( $value[ @array_search( $key, $value, true ) ], $key, false ), $label );
					}
					if( $arguments['type'] === 'multiselect' ){
						$attributes = ' multiple="multiple" ';
					} 
                    if(isset($arguments['addflags']) && $arguments['addflags']){
                        printf('<span id="flag_%1$s" class="cssflag cssflag-'.$value[0].'"></span>', $arguments['uid']);
                        $class = 'autoglot_changeflag_select';
                    }
					printf( '<select name="%1$s[]" id="%1$s" class="%4$s" %2$s>%3$s</select>', $arguments['uid'], $attributes, $options_markup, $class);
				}
				break;
			case 'radio':
			case 'checkbox':
				if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
					$options_markup = '';
					$iterator = 0;
					foreach( $arguments['options'] as $key => $label ){
						$iterator++;
						$is_checked = '';
						// This case handles if there is only one checkbox and we don't have anything saved yet.
						if(isset($value[ @array_search( $key, $value, true ) ])) {
							$is_checked = checked( $value[ array_search( $key, $value, true ) ], $key, false );
						} else {
							$is_checked = "";
						}
						// Lets build out the checkbox
						$options_markup .= sprintf( '<label for="%1$s_%6$s" class="checkboxlabel"><input id="%1$s_%6$s" name="%1$s[]" type="%2$s" value="%3$s" %4$s class="checkboxinput" /> <span class="checkboxtext">%5$s</span></label><br/>', $arguments['uid'], $arguments['type'], $key, $is_checked, $label, $iterator )."\r\n";
					}
					printf( '<fieldset id="%s">%s</fieldset>', (/*count($arguments['options'])>10*/$arguments['type']=="checkbox"?"autoglot_tgs":""), $options_markup );
                    if(count($arguments['options'])>10) {
                        echo '<br /><span><a href="#" id="autoglot_checkon">' . __('Check all', 'autoglot') . '</a></span> | <span><a href="#" id="autoglot_checkoff">' . __('Uncheck all', 'autoglot') . '</a></span>';
                    }
				}
				break;
/*			case 'image':
				// Some code borrowed from: https://mycyberuniverse.com/integration-wordpress-media-uploader-plugin-options-page.html
				$options_markup = '';
				$image = [];
				$image['id'] = '';
				$image['src'] = '';

				// Setting the width and height of the header iamge here
				$width = '1800';
				$height = '1068';

				// Lets get the image src
				$image_attributes = wp_get_attachment_image_src( $value, array( $width, $height ) );
				// Lets check if we have a valid image
				if ( !empty( $image_attributes ) ) {
					// We have a valid option saved
					$image['id'] = $value;
					$image['src'] = $image_attributes[0];
				} else {
					// Default
					$image['id'] = '';
					$image['src'] = $value;
				}

				// Lets build our html for the image upload option
				$options_markup .= '
				<img data-src="' . $image['src'] . '" src="' . $image['src'] . '" width="180px" height="107px" />
				<div>
					<input type="hidden" name="' . $arguments['uid'] . '" id="' . $arguments['uid'] . '" value="' . $image['id'] . '" />
					<button type="submit" class="upload_image_button button">Upload</button>
					<button type="submit" class="remove_image_button button">&times; Delete</button>
				</div>';
				printf('<div class="upload">%s</div>',$options_markup);
				break;*/
		}
		// If there is helper text, lets show it.
		if( array_key_exists('helper',$arguments) && $helper = $arguments['helper']) {
			printf( '<span class="helper"> %s</span>', $helper );
		}
		// If there is supplemental text lets show it.
		if( array_key_exists('supplemental',$arguments) && $supplemental = $arguments['supplemental'] ){
			printf( '<p class="description">%s</p>', $supplemental );
		}
	}
    
	/**
	 * Check and sanitize options before saving
	 */
    public function sanitize_api_key( $input ) {
        $newinput = strip_tags(trim( $input ));
        if ( ! preg_match( '/^[a-zA-Z_0-9]{30}$/i', $newinput ) ) {
            $newinput = '';
            update_option('autoglot_admin_notice',array(__( 'Your API key is not valid.', 'autoglot' ).' '.__('Please login to Autoglot Control Panel and retrieve your API key.', 'autoglot'),"error"));

            
        }
        return $newinput;
    }
    
    public function sanitize_checkbox ($input){
        if(is_array($input) && $input[0] == "selected") return $input;
        else return;
    }
   
    public function sanitize_radio2 ($input){
        return $this->sanitize_radio($input, 2);
    }

    public function sanitize_radio ($input, $end = 1){
        if(is_array($input) && in_array($input[0], range(0,$end))) return $input;
        else return;
    }
   
    public function sanitize_sitemap_priority ($input){
        if(is_array($input) && in_array($input[0], array_combine($r = range(0,0.5,0.1), $r))) return $input;
        else return;
    }
   
    public function sanitize_balance_notifications ($input){
        if(is_array($input) && array_key_exists($input[0], autoglot_consts::REPEAT_BALANCE_NOTIFICATIONS)) return $input;
        else return;
    }
   
    public function sanitize_textarea2($input){
        if(is_array($input) && count($input) == 2){
            $from = explode("\r\n", $option[0]);
            $to = explode("\r\n", $option[1]);
            if(is_array($from) && is_array($to) && count($from) == count ($to))
                return $input;
            else return;
            
        }
        else return;
    }

    public function sanitize_default_language ($input){
        if(is_array($input) && in_array($input[0], array_keys(autoglot_utils::get_all_language_names()))) return $input;
        else return;
    }
    
    public function sanitize_language_switcher ($input){
        if(is_array($input) && in_array($input[0], autoglot_consts::LANGUAGE_SWITCHER_TYPES, 1)) {return $input;}
        else return;
    }
    
    public function sanitize_language_names ($input){
        if(is_array($input) && in_array($input[0], autoglot_consts::LANGUAGE_NAME_TYPES, 1)) {return $input;}
        else return;
    }
   
    public function sanitize_language_flags($input){
        if(is_array($input)){
            foreach(autoglot_consts::LANGUAGES as $code => $langarr) if(is_array($langarr["flag"]) && count($langarr["flag"])>1){
                if( in_array($input[0], $langarr["flag"])) return $input;
            }
        } 
        return;
    }
   
    public function sanitize_active_languages ($input){
//        file_put_contents( plugin_dir_path( dirname( __FILE__ ) )."test.txt", print_r($input, true));
        $newinput = array();
        if(is_array($input) && count($input))foreach($input as $ln) {
            if(in_array($ln, array_keys(autoglot_utils::get_all_language_names()))){
                $newinput[] = $ln;
            }
        }
        return $newinput;
    }


	/**
	 * Prepare translation editor
	 */
    public function load_editor(){
        if(isset($_GET["page"]) && $_GET["page"]=="autoglot_translation_editor") {
            require_once ("autoglot_editor.php");
    
            $this->editor_table = new autoglot_editor($this->autoglot, $this);
            $this->editor_table->add_screen_options();
            $this->editor_table->perform_actions();
        }
    } 

	/**
	 * Show translation editor
	 */
    public function editor(){
        $this->editor_table->render_table();
    } 


	/**
	 * Custom Post Type for Text Replacement
	 */
    function text_replacement() {

    	$labels = array(
    		'name'                  => _x( 'Text Replacement Records', 'Post Type General Name', 'autoglot' ),
    		'singular_name'         => _x( 'Text Replacement Record', 'Post Type Singular Name', 'autoglot' ),
    		'menu_name'             => __( '', 'autoglot' ),
    		'name_admin_bar'        => __( '', 'autoglot' ),
    		'archives'              => __( '', 'autoglot' ),
    		'attributes'            => __( '', 'autoglot' ),
    		'parent_item_colon'     => __( '', 'autoglot' ),
    		'all_items'             => __( '', 'autoglot' ),
    		'add_new_item'          => __( 'Add New Text Replacement Record', 'autoglot' ),
    		'add_new'               => __( 'Add New Text Replacement', 'autoglot' ),
    		'new_item'              => __( 'New Item', 'autoglot' ),
    		'edit_item'             => __( 'Edit Record', 'autoglot' ),
    		'update_item'           => __( 'Update Record', 'autoglot' ),
    		'view_item'             => __( 'View Record', 'autoglot' ),
    		'view_items'            => __( 'View Records', 'autoglot' ),
    		'search_items'          => __( 'Search Records', 'autoglot' ),
    		'not_found'             => __( 'Not found', 'autoglot' ),
    		'not_found_in_trash'    => __( 'Not found in Trash', 'autoglot' ),
    		'featured_image'        => __( '', 'autoglot' ),
    		'set_featured_image'    => __( '', 'autoglot' ),
    		'remove_featured_image' => __( '', 'autoglot' ),
    		'use_featured_image'    => __( '', 'autoglot' ),
    		'insert_into_item'      => __( 'Insert into item', 'autoglot' ),
    		'uploaded_to_this_item' => __( '', 'autoglot' ),
    		'items_list'            => __( 'Items list', 'autoglot' ),
    		'items_list_navigation' => __( 'Items list navigation', 'autoglot' ),
    		'filter_items_list'     => __( 'Filter items list', 'autoglot' ),
    	);
    	$args = array(
    		'label'                 => __( 'Text Record', 'autoglot' ),
    		'description'           => __( 'Text Replacement', 'autoglot' ),
    		'labels'                => $labels,
    		'supports'              => array( 'title'),
    		'hierarchical'          => false,
    		'public'                => false,
    		'show_ui'               => true,
    		'show_in_menu'          => "autoglot_translation",
    		'menu_position'         => 55,
    		'show_in_admin_bar'     => false,
    		'show_in_nav_menus'     => false,
    		'can_export'            => false,
    		'has_archive'           => false,
    		'exclude_from_search'   => true,
    		'publicly_queryable'    => false,
    		'rewrite'               => false,
    		'capability_type'       => 'page',
    	);
    	register_post_type( AUTOGLOT_TEXTREPL_POSTTYPE, $args );
    
    }
    function text_replacement_title_text( $title ){
        $screen = get_current_screen();
        
        if  ( AUTOGLOT_TEXTREPL_POSTTYPE == $screen->post_type ) {
            $title = __( 'Please enter the name of your text record', 'autoglot' );
        }
        
        return $title;
    }
    function text_replacement_form_top( $title ){
        $screen = get_current_screen();
        
        if  ( AUTOGLOT_TEXTREPL_POSTTYPE == $screen->post_type ) {
            echo "<p>".__( 'Please choose a name of your text record (only visible for site administrator), content in your default language that will be replaced, and content for each language where you want your content replaced. If you don\'t set a new content for any particular language, default content will be displayed.', 'autoglot' )."</p>";
        }
        
        return $title;
    }
    function text_replacement_add_custom_box(){
        add_meta_box(
            'autoglot_textrepl_box',                            // Unique ID
            __( 'Text Replacement', 'autoglot' ),     // Box title
            array($this, 'text_replacement_custom_box_html'),   // Content callback, must be of type callable
            AUTOGLOT_TEXTREPL_POSTTYPE                          // Post type
            );
    }
    function text_replacement_save_data($post_id){
        if(wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if(!array_key_exists('autoglot_text_replacement_content', $_POST)) return;
        $autoglot_text_replacement_content = array();
        $autoglot_text_replacement_content_raw = filter_input(INPUT_POST, 'autoglot_text_replacement_content', FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);
        if(is_array($autoglot_text_replacement_content_raw)) {
            foreach($autoglot_text_replacement_content_raw as $id=>$content) {
                $autoglot_text_replacement_content[$id] = wp_kses_post($content);
            }
            update_post_meta(
                $post_id,
                '_autoglot_textrepl_meta',
                $autoglot_text_replacement_content
            );
        }
        if(!(isset($autoglot_text_replacement_content['default']) && strlen(trim($autoglot_text_replacement_content['default'])))) {
            add_settings_error(
                'autoglot_text_replacement',
                'autoglot_text_replacement',
                __( 'Warning: you have not specified default language content that should be replaced. That\'s OK, we will save the record but it will not work.', 'autoglot' ),
                'error'
            );
            set_transient( 'text_replacement_settings_errors', get_settings_errors(), 30 );
        }
    }
    function text_replacement_disable_view_mode($post_types){
        unset( $post_types[AUTOGLOT_TEXTREPL_POSTTYPE] );
		return $post_types;
    }
    function text_replacement_admin_notices() {
        // If there are no errors, then we'll exit the function
        if ( ! ( $errors = get_transient( 'text_replacement_settings_errors' ) ) ) {
            return;
        }
        
        // Otherwise, build the list of errors that exist in the settings errores
        $message = '<div id="text_replacement-message" class="error"><p><ul>';
        foreach ( $errors as $error ) {
        $message .= '<li>' . $error['message'] . '</li>';
        }
        $message .= '</ul></p></div><!-- #error -->';
        
        // Write them out to the screen
        echo wp_kses_post($message);
        
        // Clear and the transient and unhook any other notices so we don't see duplicate messages
        delete_transient( 'text_replacement_settings_errors' );
        remove_action( 'admin_notices', 'text_replacement_admin_notices' );
}
    function text_replacement_custom_box_html($post){
        if(!is_array($meta_value = get_post_meta($post->ID, '_autoglot_textrepl_meta', true))) $meta_value = array();
        echo "<p>".__("Please enter content that should be replaced when switching to another language.", 'autoglot')."</p>";
    	printf( '<textarea name="autoglot_text_replacement_content[default]" id="autoglot_text_replacement_content" placeholder="%1$s" rows="3" cols="70">%2$s</textarea>', __("Content in your default language.", 'autoglot'), esc_textarea($meta_value['default']));
        echo "<p>".__("Please enter content that will be shown in each other language.", 'autoglot')."</p>";
        echo "<p style=\"color:#007cba\">".__("This color means currently active language.", 'autoglot')."</p>";
        echo "<table><thead><tr><th>".__("Language", 'autoglot')."</th><th>".__("Language code", 'autoglot')."</th><th>".__("New content", 'autoglot')."</th></tr></thead><tbody>";
        foreach(autoglot_utils::get_all_language_names(1) as $lng => $nm){
            if(in_array($lng,$this->autoglot->options->active_languages, true)) echo "<tr><td><strong style=\"color:#007cba\">".esc_html($nm)."</strong></td><td><strong style=\"color:#007cba\">".esc_html($lng)."</strong></td><td>"; else echo "<tr><td>".esc_html($nm)."</td><td>".esc_html($lng)."</td><td>";
           	printf( '<textarea name="autoglot_text_replacement_content['.esc_attr($lng).']" id="autoglot_text_replacement_content_'.esc_attr($lng).'" rows="2" cols="50">%1$s</textarea>', esc_textarea($meta_value[$lng]) );
            echo "</td></tr>";
        }
        echo "</tbody></table>";
    }
    function text_replacement_publishing_actions(){
        global $post;
        if($post->post_type == AUTOGLOT_TEXTREPL_POSTTYPE){
            echo '<style type="text/css">
            #misc-publishing-actions,
            #minor-publishing-actions{
                display:none;
            }
            </style>';
        }
    }
    function text_replacement_publishing_box(){
        global $post;
        if($post->post_type == AUTOGLOT_TEXTREPL_POSTTYPE){
            echo '<div style="margin:10px 0"><a href="'.admin_url( 'edit.php?post_type=autoglot_textrepl').'" class="button">' . __('Back to list', 'autoglot') . '</a></div>';
        }
    }
    function text_replacement_quick_actions($actions){
        global $current_screen;
        if($current_screen->post_type == AUTOGLOT_TEXTREPL_POSTTYPE){
            unset( $actions['inline hide-if-no-js'] );
        }
        return $actions;
    }
    function text_replacement_remove_bulk_edit( $actions ){
        unset( $actions['edit'] );
        return $actions;
    }
    function text_replacement_text_top($content){
        echo "<p>".__("The \"Text Replacement\" feature of Autoglot plugin lets you easily change pieces of content on your translated pages.", 'autoglot')."</p>";
        echo "<p>".__("This can be useful for:", 'autoglot')."</p><ol>";
        echo "<li>".__("Displaying different affiliate or any other links on different language pages of your blog, e.g. <ins>https://www.amazon.com/product-one</ins> may be replaced with <ins>https://www.amazon.es/producto-uno</ins> on your Spanish pages.", 'autoglot')."</li>";
        echo "<li>".__("Embedding different videos on different language pages of your blog, e.g. youtube.com/watch?v=EnglishVideoCode may be replaced with youtube.com/watch?v=GermanVideoCode on your German pages.", 'autoglot')."</li>";
        echo "<li>".__("And so on...", 'autoglot')."</li>";
        echo "</ol>";
        echo '<p><em>' . __('* Please note, text replacement happens after translation of content.', 'autoglot') . '</em></p>';
        return $content;
    }
    function text_replacement_set_custom_columns($columns) {
        return $this->array_insert_after($columns, "title", array("numrecords" => __( '# of replacement records', 'autoglot' )));
    }
    function text_replacement_custom_columns_data( $column, $post_id ) {
        if( "numrecords" == $column ) {
            $meta_value = get_post_meta($post_id, '_autoglot_textrepl_meta', true);
            if(is_array($meta_value)){
                $c = 0;
                foreach($meta_value as $lng=>$cnt){
                    if($lng!="default" && strlen(trim($cnt))) $c++;
                }
                echo esc_html($c);
            }
            else echo "0";
        }
    }
    function array_insert_after( array $array, $key, array $new ) {
    	$keys = array_keys( $array );
    	$index = array_search( $key, $keys );
    	$pos = false === $index ? count( $array ) : $index + 1;
    
    	return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
    }

	/**
	 * Admin Notice
	 * 
	 * This displays the notice in the admin page for the user
	 */
	public function admin_notice($message, $notice_type="success") { ?>
		<div class="notice notice-<?php echo esc_attr($notice_type);?>">
			<p><?php echo wp_kses_post($message); ?></p>
		</div><?php
	}

    function flush_cache( $option_name, $old_value, $new_value ){
        if(stripos($option_name, "autoglot") !== false && !$this->cache_flushed){
            $this->cache_flushed = true;
            $this->autoglot->third_party->flush_caches();
        }
        
        return;
    }
    

	/**
	 * This handles setting up the rewrite rules
	 */
	public function setup_rewrites() {
		//
		$url_slug = 'autoglot_translation';
		// Lets setup our rewrite rules
//		add_rewrite_rule( $url_slug . '/?$', 'index.php?autoglot_translation=index', 'top' );
//		add_rewrite_rule( $url_slug . '/page/([0-9]{1,})/?$', 'index.php?autoglot_translation=items&autoglot_translation_paged=$matches[1]', 'top' );
//		add_rewrite_rule( $url_slug . '/([a-zA-Z0-9\-]{1,})/?$', 'index.php?autoglot_translation=detail&autoglot_translation_vehicle=$matches[1]', 'top' );


		// Lets flush rewrite rules on activation
		flush_rewrite_rules();
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {

		wp_enqueue_style( 'autoglot-translation', plugin_dir_url( __FILE__ ) . 'css/autoglot_translation_admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'autoglot-translation-flags', plugin_dir_url( __FILE__ ) . 'css/autoglot_flags.css', array(), $this->version, 'all' );
        if(isset($_GET["page"]) && $_GET["page"]=="autoglot_translation_editor") {
            echo '<style type="text/css">';
            echo '.wp-list-table .column-lang { width: 8%; }';
            echo '.wp-list-table .column-original { width: 35%; }';
            echo '.wp-list-table .column-translated { width: 35%; }';
            echo '.wp-list-table .column-type { width: 5%; }';
            echo '.wp-list-table .column-date { width: 8%; }';
            echo '.wp-list-table .column-translated textarea { height: 100px; width: 100%; clear:both; display:none;}';
            echo '</style>';
            
        }

	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'autoglot-translation', plugin_dir_url( __FILE__ ) . 'js/autoglot_translation_admin.js', array( 'jquery' ), $this->version, false );
		if(isset($_GET["page"]) && $_GET["page"]=="autoglot_translation_editor") wp_enqueue_script( 'autoglot-translation-editor', plugin_dir_url( __FILE__ ) . 'js/autoglot_translation_editor.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Delete empty translations from DB
	 */
	private function delete_empty() {

        $db_check = $this->autoglot->autoglot_database->db_utilities_check();
        if(isset($db_check['countempty']) && $db_check['countempty']>0){
            $deleted = $GLOBALS['wpdb']->delete($this->autoglot->autoglot_database->get_translation_table(),array('translated' => ""));
            $deleted += $GLOBALS['wpdb']->delete($this->autoglot->autoglot_database->get_translation_table(),array('translated' => AUTOGLOT_TRANSLATION_INPROGRESS));
            printf('<p>' . __('%d empty translation records have been deleted from DB.', 'autoglot') . '</p>',$deleted);
        }else {
			echo '<p>' . __('No empty translations found in DB. This is great!', 'autoglot') . '</p>';
        }
        echo '<div style="margin:10px 0"><a id="autoglot_translation_utilities" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities').'" class="button">' . __('Back to utilities page', 'autoglot') . '</a></div>';
    }

	/**
	 * Delete duplicate (and empty) translations from DB
	 */
	private function delete_duplicate() {

        $db_check = $this->autoglot->autoglot_database->db_utilities_check();
        if(isset($db_check['countduplicate']) && $db_check['countduplicate']>0){
            $query = $GLOBALS['wpdb']->prepare("DELETE FROM `".$this->autoglot->autoglot_database->get_translation_table()."` WHERE id NOT IN (SELECT * FROM (SELECT MAX(t.id) FROM `".$this->autoglot->autoglot_database->get_translation_table()."` t WHERE translated NOT LIKE '' GROUP BY texthash, lang) x);");
            $GLOBALS['wpdb']->query($query);
            echo esc_html($row->cntall);
            printf('<p>' . __('%d duplicate translation records have been deleted from DB.', 'autoglot') . '</p>',$db_check['countduplicate']);
        }else {
			echo '<p>' . __('No duplicate translations found in DB. This is great!', 'autoglot') . '</p>';
        }
        echo '<div style="margin:10px 0"><a id="autoglot_translation_utilities" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities').'" class="button">' . __('Back to utilities page', 'autoglot') . '</a></div>';
    }


	/**
	 * Backup translations table
	 */
	private function backup_table() {
        $table          = $this->autoglot->autoglot_database->get_translation_table();
        $st_counter     = 0;
        $result         = $GLOBALS['wpdb']->get_results('DESCRIBE '.$table,ARRAY_A);
        $columns        = array();
        foreach($result as $row) {
            $columns[] = $row['Field'];
        }
        $fields_amount  = count($columns);

        $result         = $GLOBALS['wpdb']->get_results('SELECT * FROM '.$table.' ORDER BY id ASC');  
        $rows_num       = count($result);     
        $res            = $GLOBALS['wpdb']->get_row('SHOW CREATE TABLE '.$table);
        $content        = "DROP TABLE IF EXISTS ".$table.";\n\n";
        $content       .= $res->{'Create Table'}.";\n";

        foreach($result as $row) {
            $arrow = json_decode(json_encode($row), true);
            if ($st_counter%100 == 0 || $st_counter == 0 )  
            {
                $content .= "\nINSERT INTO ".$table." VALUES";
            }
            $content .= "\n(";
            for($j=0; $j<$fields_amount; $j++)  
            { 
                $arrow[$j] = str_replace("\n","\\n", addslashes(array_values($arrow)[$j]) ); 
                if (isset($arrow[$j]))
                {
                    $content .= '"'.$arrow[$j].'"' ; 
                }
                else 
                {   
                    $content .= '""';
                }     
                if ($j<($fields_amount-1))
                {
                        $content.= ',';
                }      
            }            
            $content .=")";
            $st_counter++;
            if ( (($st_counter)%100==0 && $st_counter-1!=0) || $st_counter==$rows_num) 
            {   
                $content .= ";\n";
            } 
            else 
            {
                $content .= ",";
            }  
        }
        $content = esc_textarea($content);
        echo "<textarea style='width: 80%;height: 400px;'>".$content."</textarea>";

        echo '<div style="margin:10px 0"><a id="autoglot_translation_utilities" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities').'" class="button">' . __('Back to utilities page', 'autoglot') . '</a></div>';
    }


	/**
	 * Add Word Counts 2 Admin Dashboard
	 */
	function glance_word_count() {
	   
		$url_posts = admin_url( 'edit.php' );
		$url_langs = admin_url( 'admin.php?page=autoglot_translation_languages' );
		$url_ag = admin_url( 'admin.php?page=autoglot_translation' );

        printf('<style scoped>.ag_word-count a:before { content:\'\\f497\' !important; }</style><li class=\'ag_word-count\'><a href='.$url_posts.'><tr><td' . __('>%1s Words (%2s)', 'autoglot') . '</a></li>', number_format_i18n($this->db_stats['wpcount']), autoglot_utils::get_language_name($this->autoglot->options->default_language));
        printf('<style scoped>.ag_langs-count a:before { content:\'\\f533\' !important; }</style><li class=\'ag_langs-count\'><a href='.$url_langs.'><tr><td>' . __('%s Languages for translation', 'autoglot') . '</a></li>', count($this->autoglot->options->active_languages)-1);
        printf('<style scoped>.ag_tword-count a:before { content:\'\\f326\' !important; }</style><li class=\'ag_tword-count\'><a href='.$url_ag.'><tr><td>' . __('%s Words not translated', 'autoglot') . '</a></li>', number_format_i18n($this->db_stats["w2translate"]));   

	}
    
	/**
	 * Custom utilities function
	 */
	private function utilities_reserved() {

        $my_c = isset( $_GET['c']) ? $_GET['c'] : "";
        
        switch($my_c) {
/*            case "":
            case "confirm1":

                $query = $GLOBALS['wpdb']->prepare("SELECT * FROM `".$this->autoglot->autoglot_database->get_translation_table()."` WHERE translated NOT LIKE '' AND original NOT LIKE '' AND texthash LIKE 'bb832b9e86f1534fca49ba8ce0114233'");
                $res = $GLOBALS['wpdb']->get_results($query);
                $cnt = 0;
                foreach($res as $row){
                    if(strlen(strip_tags($row->original))){
                        $lang = $row->lang;
                        $oldhash = $row->texthash;
                        $oldtran = $row->translated;
                        $oldorig = $row->original;
                        //$neworig = wptexturize($oldorig);
                        $arrayorig = $this->autoglot->domo->HTML2Array(wptexturize($oldorig));
                        $neworig = $arrayorig[array_keys($arrayorig)[0]];
/*                        $dd = new DOMDocument;
                        $dd->loadHTML('<?xml encoding="UTF-8">' . wptexturize($oldorig), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $dd->normalizeDocument();
                        $neworig = $dd->saveHTML();
  */        /*              
                        $newhash = autoglot_utils::gettexthash($neworig);
                        if($newhash != $oldhash){
                            //$newtran = autoglot_utils::prepare_HTML_translation($oldtran);
                            //$newhash = autoglot_utils::gettexthash($neworig);
                            
                            if($my_c == "confirm1") {
        //                        $GLOBALS['wpdb']->update($this->autoglot->autoglot_database->get_translation_table(),array('texthash' => $newhash, 'original' => $neworig, 'translated' => $newtran), array('texthash' => $oldhash, 'lang' => $lang));
                                $insertarray = array("texthash" => $newhash, "lang" => $lang, "original" => $neworig, "translated" => $oldtran, "timestamp" => current_time("mysql"), "type" => $row->type, "postid" => $row->postid);
                                $formatarray = array("%s", "%s", "%s", "%s", "%s", "%s", "%d");
                                $insertquery = $GLOBALS['wpdb']->insert($this->autoglot->autoglot_database->get_translation_table(), $insertarray, $formatarray);
                                echo "DELETE FROM ".$this->autoglot->autoglot_database->get_translation_table()." WHERE texthash = '".$oldhash."' AND lang = '".$lang."';<br />";
                            } else {
                                echo "New hash: ".$newhash." (old: ".$oldhash.", lang: ".$lang.")<br />";
                                echo "New original: ".htmlspecialchars($neworig)."<br />";
                                echo "Old: ".htmlspecialchars($oldorig).", lang: ".$lang."<br /><br />";
                                //echo "Try New original: ".htmlspecialchars(autoglot_utils::prepare_HTML_translation($neworig))."<br />";
                                $cnt++;
                            }
                        }
                    }
                }
                echo "<br /><br />Total records for update: <strong>".$cnt."</strong><br /><br />";
        		if($my_c == "confirm") {
                    echo "STEP 1 DONE!<br /><br />";
                    echo '<div style="margin:10px 0"><a id="autoglot_translation_utilities" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities_reserved&c=step2').'" class="button">' . __('Proceed to step 2', 'autoglot') . '</a></div>';
        		} else {
                    echo '<div style="margin:10px 0"><a id="autoglot_translation_utilities" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities_reserved&c=confirm1').'" class="button">' . __('Click to update DB', 'autoglot') . '</a></div>';
        		} 
            break;*/
/*
            case "":
            case "confirm1":
        
                $query = $GLOBALS['wpdb']->prepare("SELECT * FROM `".$this->autoglot->autoglot_database->get_translation_table()."` WHERE translated LIKE '%<li%' AND original LIKE '%<li%'");
                $res = $GLOBALS['wpdb']->get_results($query);
                $cnt = 0;
                foreach($res as $row){
                    if(strlen(strip_tags($row->original)) && autoglot_utils::get_tags_count($row->original) == autoglot_utils::get_tags_count($row->translated)){
                        $lang = $row->lang;
                        $oldhash = $row->texthash;
                        $oldtran = $row->translated;
                        $oldorig = $row->original;
                        $arrayorig = $this->autoglot->domo->HTML2Array(mb_convert_encoding($oldorig, 'HTML-ENTITIES', 'UTF-8'));
                        $arraytran = $this->autoglot->domt->HTML2Array(mb_convert_encoding($oldtran, 'HTML-ENTITIES', 'UTF-8'));
                        if(count($arrayorig) == count($arraytran)) {
                            $cnt++;
                            //if($cnt>10) break;
        
                            if($my_c == "confirm1") {
        //                        $GLOBALS['wpdb']->update($this->autoglot->autoglot_database->get_translation_table(),array('texthash' => $newhash, 'original' => $neworig, 'translated' => $newtran), array('texthash' => $oldhash, 'lang' => $lang));
                                $index = 0;
                                $keystran = array_keys($arraytran);
                                foreach($arrayorig as $newhash => $neworig){
                                    $insertarray = array("texthash" => $newhash, "lang" => $lang, "original" => $neworig, "translated" => $arraytran[$keystran[$index]], "timestamp" => current_time("mysql"), "type" => $row->type, "postid" => $row->postid);
                                    $formatarray = array("%s", "%s", "%s", "%s", "%s", "%s", "%d");
                                    $insertquery = $GLOBALS['wpdb']->insert($this->autoglot->autoglot_database->get_translation_table(), $insertarray, $formatarray);
                                    //echo nl2br(htmlspecialchars(print_r($insertarray,true)))."<br />";
                                    $index++;
                                }
                                echo "DELETE FROM ".$this->autoglot->autoglot_database->get_translation_table()." WHERE texthash = '".$oldhash."' AND lang = '".$lang."';<br />";
                            } else {
                                echo "New hash: ".$newhash." (old: ".$oldhash.", lang: ".$lang.")<br />";
                                //echo "Original: ".nl2br(htmlspecialchars($oldorig))."    <br />";
                                //echo "Translated: ".nl2br(htmlspecialchars($oldtran))."    <br />";
                                //echo nl2br(htmlspecialchars(print_r($arrayorig,true)))."<br />";
                                //echo nl2br(htmlspecialchars(print_r($arraytran,true)))."<br />";
                                echo "Original array: ".count($arrayorig)."<br />Translated array: ".count($arraytran)."<br /><br />";
                            }
                        }
                    }
                }
                echo "<br /><br />Total records for update: <strong>".$cnt."</strong><br /><br />";
        		if($my_c == "confirm1") {
                    echo "ALL DONE!";
        		} else {
                    echo '<div style="margin:10px 0"><a id="autoglot_translation_utilities" href="'.admin_url( 'admin.php?page=autoglot_translation_utilities_reserved&c=confirm1').'" class="button">' . __('Click to update DB', 'autoglot') . '</a></div>';
        		} 
            break;*/

            default:
            break;
        }
        

/*
*/
    }

}
