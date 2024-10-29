<?php
/**
Plugin Name: Autoglot Wordpress Translation
Plugin URI: https://autoglot.com/download/
Description: Autoglot Wordpress Translation Plugin - fully automatic SEO-friendly plugin for multilingual Wordpress translation.
Version: 2.4.8
Text Domain: autoglot
Author: Autoglot Wordpress Team
Author URI: https://autoglot.com/
*/
//avoid direct calls to this file where wp core files not present
if (!function_exists('add_action')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}
if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

if ( ! defined( 'AUTOGLOT_PLUGIN_BASENAME' ) ) {
	define( 'AUTOGLOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

require_once("utils/autoglot_db.php");
require_once("utils/autoglot_constants.php");
require_once("utils/autoglot_options.php");
require_once("utils/autoglot_dom.php");
require_once("admin/autoglot_admin.php");
require_once("utils/autoglot_utils.php");
require_once("utils/autoglot_widget.php");
require_once("utils/autoglot_3rdparty.php");
require_once("utils/autoglot_curl.php");

if ( !class_exists('Autoglot') ) {

    class Autoglot {

        public $langURL;
        public $homeURL;        
        public $plugin_url;

        private $postID;        
        private $orgcontent;
        private $trcontent;
        private $default_strings = array();
        private $manual_strings = array();
        private $manual_strings_translated = array();
        
        public $page_links = array();
        public $page_links_translated = array();
        
        /** The database class */
        public $autoglot_database;
        
        /** 3rd party tools */
        public $third_party;
    
        /** curl class */
        public $curl;
    
        /** admin class */
        private $plugin_admin;
    
        /** options */
        public $options;
    
        /** DOM functions */
        public $dom;
    
        /** admin error message */
        private $admin_msg;
        
        /** allowed html for sanitizing */
        public $allowed_html;

        /** (if someone flushed buffer) */
        private $flushed_buffer = false;
        
        /** allowed html for sanitizing */
        public $json_request = false;

        function __construct() {

            $this->homeURL = home_url();//get_option('home');
            $this->plugin_url = plugin_dir_url(__FILE__); 
            $this->langURL = autoglot_utils::get_language_from_url($_SERVER['REQUEST_URI'], $this->homeURL);

            if (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] == 'update_order_review') {
                $this->langURL = autoglot_utils::get_language_from_url($_SERVER['HTTP_REFERER'], $this->homeURL);
                $this->json_request = true;
            }

            $this->autoglot_database = new autoglot_database($this);
            $this->options = new autoglot_options($this);
            $this->curl = new autoglot_curl($this);
            $this->dom = new autoglot_dom($this);

            //Allow autoglot custom attribute in wp_kses_* - will need this in admin                    
            $this->allowed_html = wp_kses_allowed_html( 'post' );
    		$this->allowed_html = array_merge(
    			$this->allowed_html,
    			autoglot_consts::ADDITIONAL_TAGS_FOR_FORM
    		);
            $keeparray = array();
            foreach($this->allowed_html as $t => $a){
                $keeparray[$t] = $a;
                if(is_array($keeparray[$t]))$keeparray[$t][AUTOGLOT_REPLACE_ATTRIBUTES] = 1;
            }
            $this->allowed_html = $keeparray;
                                
            $this->plugin_admin = new autoglot_admin($this, AUTOGLOT_PLUGIN_NAME, AUTOGLOT_PLUGIN_VER);
            
			register_activation_hook(__FILE__, array($this, 'plugin_activated'));
            register_deactivation_hook(__FILE__, array($this, 'plugin_deactivated'));
            
            add_action('plugins_loaded', array($this, 'plugin_loaded'));
            add_action('widgets_init', array($this, 'autoglot_register_widget') );  
            add_action('wpmu_new_blog', array($this, 'autoglot_activate_new_blog'));
                        
            add_action('comment_post', array($this, 'autoglot_add_comment_meta_language'));
            add_filter('comment_post_redirect', array($this, 'autoglot_comment_redirect'));

            //translation not enabled, no sense to continue
            //if(!$this->options->translation_enable) return;
            
            add_shortcode( 'ag_switcher', array($this, 'autoglot_register_shortcode'));
            
            //if lang from URL not active
            if(!in_array($this->langURL, $this->options->active_languages, true)) $this->langURL="";
            
            $this->third_party = new autoglot_3rdparty($this);

            if(!in_array($this->options->default_language, $this->options->active_languages, true))array_unshift($this->options->active_languages, $this->options->default_language);//$this->options->active_languages[] = $this->options->default_language;
            
            if($this->langURL != "en")$this->default_strings = AUTOGLOT_DEFAULT_STRINGS;//no need to translate default (eng) strings to English
            $this->default_strings[] = get_bloginfo( 'name' );//will be used in many areas later            
            if(strlen($this->options->manual_strings)){
                $this->manual_strings = autoglot_utils::get_hash_strings(explode("\r\n", $this->options->manual_strings));
            }

            if($this->langURL == $this->options->default_language)$this->langURL = "";
            
            if ( ! is_admin() && count($this->options->active_languages) > 1){//no admin dashboard and active_languages > default language
                
				if(!$this->options->translation_adminonly){
					add_action('wp_head', array($this, 'add_autoglot_hreflangs')); // need hreflangs anyway
				}
				if($this->options->floatbox_enable){
					add_action('wp_footer', array($this, 'add_autoglot_floatbox')); // add float box with language switcher popup
				}
                add_action('wp_print_styles', array(&$this, 'add_autoglot_css'));
                add_action('wp_print_scripts', array(&$this, 'add_autoglot_js'));

				if(strlen($this->langURL) && $this->language_active($this->langURL) && $this->autoglot_database->db_exists()) {

                    if($this->options->skip_caching) {autoglot_utils::stop_cache();}
				    
                    $this->curl->curlInit();//not in admin, in active language - let's connect!
                    
					add_action( 'init', array($this,'wp_init'), 0);//start buffering here
					
                    add_action( 'parse_request', array($this, 'autoglot_parse_request'), 0); // for rtl in themes
					add_filter( 'redirect_canonical', array($this, 'on_redirect_canonical'), 10, 2);//prevent bad redirects
					add_filter( 'request', array($this, 'autoglot_request_filter'));//save wp vars as if no language folder

					add_action( 'wp', array($this,'wp_main'));//

                    remove_filter( 'the_content', 'wptexturize' );  //wptexturize delivers unpreditable results in different locales, so let's disable it for translation 
					remove_filter( 'the_excerpt', 'wptexturize' );  // 
					remove_filter( 'comment_text', 'wptexturize' ); // 
					remove_filter( 'the_title', 'wptexturize' );    // 

                    add_filter( "language_attributes", array($this, 'autoglot_set_lang_attr'), 1, 2 );

					$this->orgcontent = autoglot_utils::get_hash_strings($this->default_strings);

					add_filter( 'posts_search', array($this, 'autoglot_search'), 10000, 2);

                    add_action( 'comment_form', array(&$this, 'autoglot_comment_language') );
                }
            }      
			
        }
        
        public static function start() {
    
            if ( !is_admin() ) {
            }
    
        }

        /**
         * Plugin activation
         */
        function plugin_activated($networkwide) {

			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				if ( $networkwide ) {
					if ( false == is_super_admin() ) {
						return;
					}
					$blogs = wp_get_sites();
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog[ 'blog_id' ] );
						$this->autoglot_database->setup_db(true);
                        // permalink rewrite
                        $GLOBALS['wp_rewrite']->flush_rules();
						restore_current_blog();
					}
				} else {
					if ( false == current_user_can( 'activate_plugins' ) ) {
						return;
					}
					$this->autoglot_database->setup_db(true);
                    // permalink rewrite
                    $GLOBALS['wp_rewrite']->flush_rules();
				}
			} else {
				$this->autoglot_database->setup_db(true);
                // permalink rewrite
                $GLOBALS['wp_rewrite']->flush_rules();
			}

        }
    
        /**
         * Plugin deactivation
         */
        function plugin_deactivated() {
            // permalink rewrite
            $GLOBALS['wp_rewrite']->flush_rules();
        }

        /**
         * Callback from admin_notices - display error message to the admin.
         */
        function plugin_install_error() {
            $this->plugin_admin->admin_notice(__('There was an error during installation or activation of Autoglot plugin:', 'autoglot').' ',"error");
        }
    
        /**
         * Callback when all plugins have been loaded. Serves as the location
         * to check that the plugin loaded successfully else trigger notification
         * to the admin and deactivate plugin.
         */
        function plugin_loaded() {

            // load translation files for plugin
            load_plugin_textdomain('autoglot', false, dirname(plugin_basename(__FILE__)) . '/translation');

            $db_version = get_option(AUTOGLOT_DB_VERSION_KEY);

            // update DB
            if ($db_version != AUTOGLOT_DB_VERSION) {
                $this->autoglot_database->setup_db();
                $db_version = get_option(AUTOGLOT_DB_VERSION_KEY);
            }
    
            // update DB failed
            if ($db_version != AUTOGLOT_DB_VERSION) {
                
                $this->admin_msg = "Failed to install or update the translation table  <em> " . AUTOGLOT_TABLE . "</em> in local database. <br>";
    
                //Some error occured - notify admin and deactivate plugin
                add_action('admin_notices', array($this, 'plugin_install_error'));
                deactivate_plugins( plugin_basename( __FILE__ ) );
            }
        }
        
        /**
         * Callback when new blog added 2 multisite
         * Check and activate plugin for this site if it's network active.
         */
        function autoglot_activate_new_blog($blog_id) {
        	if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
        		switch_to_blog($blog_id);
        		$this->autoglot_database->setup_db(true);
                // permalink rewrite
                $GLOBALS['wp_rewrite']->flush_rules();
        		restore_current_blog();
        	}
        }
                
        /**
         * Adds link rel alternate for links to alternative versions
         */
        function add_autoglot_hreflangs() {
			global $wp;
            if (is_404() || is_search() || !$this->options->show_hreflangs || (defined('REST_REQUEST') && REST_REQUEST)) {
                return;
            }
            if(count($this->options->active_languages)){
                $current_url = $this->get_original_url(home_url( add_query_arg( array(), $wp->request ) ),$this->homeURL,$this->langURL, 0);
				$current_link = $current_url;//str_replace($this->homeURL, "", $current_url);
                if(strlen($this->langURL)){
                    foreach($this->options->active_languages as $lang){
                        $lang_url = '';
                        if($lang == $this->options->default_language){
                            $lang_url = $current_link;
                        }
                        elseif($lang != $this->langURL){
                            if($this->options->translate_urls) {
                                $lang_url = autoglot_utils::add_language_to_url($this->translate_url($current_link, $lang), $this->homeURL, $lang);
                            } else {
                                $lang_url = autoglot_utils::add_language_to_url($current_link, $this->homeURL, $lang);
                            }
                        }
                        if(strlen($lang_url)) echo '<link rel="alternate" hreflang="'.esc_attr($lang).'" href="'.trailingslashit(esc_url($lang_url)).'">'."\r\n";
                    } 
                }
                else { // we are in default language
                    foreach($this->options->active_languages as $lang){
                        $lang_url = '';
                        if($lang != $this->options->default_language) {
                            if($this->options->translate_urls) {
                                $lang_url = autoglot_utils::add_language_to_url($this->translate_url($current_link, $lang), $this->homeURL, $lang);
                            } else {
                                $lang_url = autoglot_utils::add_language_to_url($current_link, $this->homeURL, $lang);
                            }
                        }
                        if(strlen($lang_url)) echo '<link rel="alternate" hreflang="'.esc_attr($lang).'" href="'.trailingslashit(esc_url($lang_url)).'">'."\r\n";
                    } 
                }
            }
        }
        
        /**
         * Adds floating box with language switcher popup
         */
        function add_autoglot_floatbox() {
            if (is_404() || is_search() || !$this->options->floatbox_enable || (defined('REST_REQUEST') && REST_REQUEST)) {
                return;
            }
            if(count($this->options->active_languages) && (!$this->options->translation_adminonly || $this->options->translation_adminonly && current_user_can('manage_options'))){
                $lang = strlen($this->langURL)?$this->langURL:$this->options->default_language;
                $addsmallcss = "";
                $addflag = 0;
                $flagimage = "";
                $lang_flag = isset($this->options->language_flags[$lang])?$this->options->language_flags[$lang]:autoglot_utils::get_language_flag($lang);

                echo '<div id="ag_floatblox" class="'.AUTOGLOT_NOTRANSLATE_LANGUAGESWITCHER.'"><a href="#" name="ag_modal" box="ag_languageswitcher">';
                switch($this->options->popup_switcher){
                    case "smallflagslist":
                        $addsmallcss = "_small";
                    case "flagslist":
                        echo '<span class="languagelist">';
                        echo '<span class="cssflag'.esc_attr($addsmallcss).' cssflag-'.$lang_flag.esc_attr($addsmallcss).'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->options->language_names)).'"></span>';
                        //echo '<span class="dashicons dashicons-translation"></span>';
                        echo '</span>';
                    break;
                    case "flagsselect":
                    break;
                    case "languageflagslist":
                        $addflag = 1;
                    case "languagelist":
                    default:
                        echo '<span class="languagelist">';
                        if($addflag) $flagimage = '<span class="cssflag cssflag-'.$lang_flag.'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->options->language_names)).'"></span>';
                        echo $flagimage.esc_html(autoglot_utils::get_full_name($lang,$this->options->language_names));
                        //echo '&nbsp;<span class="dashicons dashicons-translation"></span>';
                        echo '</span>';
                }
                echo '</a></div>';
                
                echo '                <div id="boxes" class="'.AUTOGLOT_NOTRANSLATE_LANGUAGESWITCHER.'">
                <div id="ag_languageswitcher" class="ag_window" style="text-align:left">';
                echo do_shortcode( '[ag_switcher type="'.$this->options->popup_switcher.'"]' );
                if(count($this->options->active_languages)>15)echo "<style>#boxes #ag_languageswitcher ul {columns: ".(ceil(count($this->options->active_languages)/14)).";}</style>";
                echo '                
                <div style="clear:both;"></div>
                <br />
                <a href="#" class="closebox">&times; Close</a>
                </div>
                
                <div id="ag_mask"></div>
                </div>
                ';
            }
        }
        
        
        /**
         * Use autoglot CSS
         */
        function add_autoglot_css() {
    
            //include the autoglot.css
            wp_enqueue_style('autoglot', $this->plugin_url.AUTOGLOT_FOLDER_CSS.'autoglot.css', array(), AUTOGLOT_PLUGIN_VER);
    
        }

        /**
         * Use autoglot JS
         */
        function add_autoglot_js() {
    
            //include the autoglot.js
            wp_register_script('autoglot',$this->plugin_url.AUTOGLOT_FOLDER_JS.'/autoglot.js', array('jquery'), AUTOGLOT_PLUGIN_VER);
            wp_enqueue_script('autoglot');
    
        }
    
        /**
         * Register autoglot widget
         */
        function autoglot_register_widget() {
            if(count($this->options->active_languages) > 1 && (
            (!$this->options->translation_adminonly || $this->options->translation_adminonly && current_user_can('manage_options')))) {
                
            	register_widget( 'autoglot_widget' );
            }
        }
        
        /**
         * Register autoglot shortcode
         */
        function autoglot_register_shortcode($atts) {
            
            if(count($this->options->active_languages) > 1 && (
            (!$this->options->translation_adminonly || $this->options->translation_adminonly && current_user_can('manage_options')))) {
                global $wp_widget_factory;
                
                // Configure defaults and extract the attributes into variables
                extract( shortcode_atts(
                    array(
                        'type'   => "",
                        'title'  => "",
                        'hidebox'=> 0,
                    ),
                    $atts
                ));
                
                $widget_name = "autoglot_widget";
                
                $instance = array(
                    'selectstyle'   => $type,
                    'title'         => $title,
                );
                
                $args = $hidebox ? array(
                    'before_widget' => '',
                    'after_widget'  => '',
                    'before_title'  => '',
                    'after_title'   => '',
                ):array(
                    'before_widget' => '<div class="box widget">',
                    'after_widget'  => '</div>',
                    'before_title'  => '<div class="widget-title">',
                    'after_title'   => '</div>',
                );
                
                ob_start();
                the_widget( $widget_name, $instance, $args );
                $output = ob_get_clean();
                
                return $output;
            }
        }

        /**
         * Main WP funciton.
         */

        function wp_main()
        {

        }

        /**
         * Start buffering after init
         * Register widgets
         * Add filters to 3rd party SEO plugins
         */

        function wp_init()
        {            
            ob_start(array($this, "process_page"));
        }
        
        /**
         * Global language, locales, rtl support
         * @param WP $wp - here we get the WP class
         */
        function autoglot_parse_request($wp) {

            //if someone flushed - retrigger the output buffering
            if ($this->flushed_buffer) {
                ob_start(array(&$this, "process_page"));
            }
    
            // make themes that support rtl - go rtl http://wordpress.tv/2010/05/01/yoav-farhi-right-to-left-themes-sf10
            if (in_array($this->langURL, autoglot_consts::RTL_LANGUAGES)) {
                global $wp_locale;
                $wp_locale->text_direction = 'rtl';
            }
            
        }
    

        /**
         * Get the original URL without language ID, restore if translated
         */
        function get_original_url($href, $home_url, $target_language, $try_restore = 1) {
            
            if(!$target_language) return $href;

            if(strlen($home_url) && strpos($href, $home_url)!==false) $href = substr($href, strlen($home_url));
            $url = stripslashes(urldecode($href));
            $params = ($pos = strpos($url, '?')) ? substr($url, $pos) : '';
            $url = (($pos = strpos($url, '?')) ? substr($url, 0, $pos) : $url);
            $url2 = '';
            $parts = explode('/', $url);
            foreach ($parts as $part) {
                if (!$part)
                    continue;
                // don't attempt for lang
                if ($part != $target_language) {

                    if($this->options->translate_urls && $try_restore){//restore this part
                        $savepart = $part;
                        $part = str_replace('--', 'DDAASSHH', $part);
                        $part = str_replace('-', ' ', $part);
                        $part = str_replace('DDAASSHH', '-', $part);
                        
                        $opart = $this->autoglot_database->restore_url($part, $target_language);
                        if($opart) {
                            $url2 .= '/' . mb_strtolower(str_replace(' ', '-', $opart), 'UTF-8');
                        } else {
                            $url2 .= '/' . $savepart;
                        } 
                    } else {
                        $url2 .= '/' . $part;
                    } 
                    continue;
                }
                
    
            }
            if ($url2 == '')
                $url2 = '/';
    
            if (substr($href, strlen($href) - 1) == '/')
                $url2 .= '/';
            $url2 = str_replace('//', '/', $url2);
            return $home_url . $url2 . $params;
        }
    
        /**
         * Prevent canonical redirects if WP is trying to redirect from URL with language ID to original page
         */
    
        function on_redirect_canonical($red, $req) {
            if($red == $this->get_original_url($req, $this->homeURL, autoglot_utils::get_language_from_url($_SERVER['REQUEST_URI'], $this->homeURL), 0)) return 0;
            else 
            {
                $redlang = autoglot_utils::get_language_from_url($red, $this->homeURL);//redirect already contains language? 
                if($redlang && strlen($redlang) && $this->language_active($redlang)) {
                    return $red;
                }

                $parsed_url = parse_url($req);//check if request contains language
                if (isset($parsed_url['query'])) {
                    $query_params = array();
                    parse_str($parsed_url['query'], $query_params);
                    if (isset($query_params['p'])) { // redirecting from ?p=<pageid>, need to keep language and URL translation 
                        $reqlang = autoglot_utils::get_language_from_url($_SERVER['REQUEST_URI'], $this->homeURL);
                        if($reqlang && strlen($reqlang) && $this->language_active($reqlang)) {
                            //if($this->options->translate_urls) { $red = $this->translate_url($red, $reqlang);} //TODO: URL translation is not supported in redirects for some reason
                            $red = autoglot_utils::add_language_to_url($red, $this->homeURL, $reqlang);
                        }
                    }
                }
                return $red;
            }
        }

        /**
         * Launch WP parser function with original URL, then place it back
         */
        function autoglot_request_filter($query_vars ) {
            $saveuri = $_SERVER['REQUEST_URI'];
            $langURL = autoglot_utils::get_language_from_url($saveuri, $this->homeURL);
            $add_home = "";
            $parse_home = parse_url($this->homeURL);
            if(isset($parse_home['path']) && $parse_home['path'] != '/') $add_home = $parse_home['path'];   
            $saveuri = substr($saveuri, strlen($add_home));
            if (strlen($langURL)) {
                remove_filter( 'request', array($this, 'autoglot_request_filter'));
                $_SERVER['REQUEST_URI'] = $add_home.$this->get_original_url($saveuri, '', $langURL);
                global $wp;
                $savemr = $wp->matched_rule;//save, remove, then $wp->matched_rule - otherwise warnings on translated index pages
                $wp->matched_rule = "";
                $wp->parse_request();
                $query_vars = $wp->query_vars;
                $_SERVER['REQUEST_URI'] = $add_home.$saveuri;
                $wp->matched_rule = $savemr;
            }
            return $query_vars;
        }
    
        /**
         * Process page after end of buffer
         */

        function process_page($buffer) {
            
            if(!strlen($buffer)) return $buffer;
            
            $json_content = false;
            //check content-type, need to be html, we don't handle other types now
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') !== false) {
                    if (stripos($header, 'html') === false && stripos($header, 'json') === false) {
                        return $buffer;
                    } elseif (stripos($header, 'json') !== false && !$this->json_request) {//not our json
//file_put_contents(__DIR__."/debug".time().".txt", "Not our json:\r\n\r\n".$buffer);
                        return $buffer;
                    }
                }
            }
//if($this->json_request)file_put_contents(__DIR__."/debug".time().".txt", $buffer);

            //someone flushed buffer?
            if ($this->langURL == '') {
                if (!$buffer) {
                    $this->flushed_buffer = true;
                    return $buffer;
                }
            }
            
            if($this->json_request) return $buffer;//temporary
    
            $arraycontent = $this->dom->HTML2Array($buffer);//mb_convert_encoding($buffer, 'HTML-ENTITIES', 'UTF-8'));
//            return nl2br(htmlspecialchars(print_r($arraycontent, true)));

            $arraycontent = array_merge($this->orgcontent, $arraycontent);
            
            $arraytranslated = $this->translate_content($arraycontent, $this->langURL, "content", get_the_ID());
//debug echo nl2br(htmlspecialchars(print_r($arraytranslated, true)));

            $this->page_links_translated = $this->translate_urls($this->page_links, $this->langURL);                        

            $buffer_translated = $this->dom->Array2HTML($arraytranslated);
//if($_GET["debug"] == "original") return nl2br(htmlspecialchars(print_r($arraycontent,true)));
//elseif($_GET["debug"] == "translated") return nl2br(htmlspecialchars(print_r($arraytranslated,true)));
//debug echo htmlspecialchars($html);

            if(count($this->manual_strings)) {
                $searchcontent = array();
                $this->manual_strings_translated = $this->translate_content($this->manual_strings, $this->langURL, "manual");
                foreach($this->manual_strings as $h => $ms){
                    $searchcontent["/(?<![a-zA-Z0-9-_])".preg_quote($ms,"/")."(?![^<>\"]*[>=])(?![a-zA-Z0-9-_])/u"] = $this->manual_strings_translated[$h];
                }
                uksort($searchcontent,  array($this, 'sortByLength'));
                //then, replace remaining manual text pieces,..
                $buffer_translated = preg_replace(array_keys($searchcontent), ($searchcontent), $buffer_translated);
            }

            //update local links to include language ID
            //$buffer_translated = autoglot_utils::add_language_to_links($buffer_translated, $this->langURL, $this->homeURL);

            //finally, restore local links to other languages without changes
            //$buffer_translated = str_replace(AUTOGLOT_FAKE_URL, $this->homeURL, $buffer_translated);
            
            //replace links to important sites
            if(is_array($this->options->add_lngcode)) $buffer_translated = $this->languagelinks($buffer_translated);
            
            //replace other things
            if(isset($this->options->replace_text) && @count($this->options->replace_text)) $buffer_translated = strtr($buffer_translated, $this->options->replace_text);

            return $buffer_translated/*.print_r($this->page_links_translated,true)."<br /><br />".print_r($this->page_links,true)*/;
        }
        
        function sortByLength ($a, $b) {
            return strlen($b) - strlen($a);
        }
        
        /**
         * Add language codes and tld's to links and text
         */

        function languagelinks($content){
            $links_from = explode("\r\n", $this->options->add_lngcode[0]);
            $links_to = explode("\r\n", str_replace(array(AUTOGLOT_ADDLINKCODE_LNG, AUTOGLOT_ADDLINKCODE_LNG2, AUTOGLOT_ADDLINKCODE_DMN), array($this->langURL, strtr($this->langURL, AUTOGLOT_MORE_LANGCODES), autoglot_utils::get_language_flag($this->langURL)),$this->options->add_lngcode[1]));

            return str_replace($links_from, $links_to, $content);
        }

        /**
         * Set main lang attribute
         */

        function autoglot_set_lang_attr($lang) {
             if (is_admin())
                return $lang;
            return 'lang="'.autoglot_utils::get_language_locale($this->langURL).'"'.(in_array($this->langURL, autoglot_consts::RTL_LANGUAGES)?' dir="rtl"':"");
        }

        /**
         * Add lang ID to comment form
         */

        function autoglot_comment_language($post_id) {
            echo '<input type="hidden" name="autoglot_comment_language" value='.esc_attr($this->langURL).' />';
        }
        
        /**
         * Add language to comment meta
         */
        function autoglot_add_comment_meta_language($post_id) {
            if (isset($_POST['autoglot_comment_language']) && strlen($_POST['autoglot_comment_language']) && $this->language_active($_POST['autoglot_comment_language'])) {
                add_comment_meta($post_id, 'autoglot_comment_language', $_POST['autoglot_comment_language'], true);
            } else {
                $trylang = autoglot_utils::get_language_from_url($_SERVER['HTTP_REFERER'], $this->homeURL);
                if (strlen($trylang) && $this->language_active($trylang)) {
                    add_comment_meta($post_id, 'autoglot_comment_language', $trylang, true);
                }
            }
        }

        /**
         * Redirect user to correct language after adding comment
         */
        function autoglot_comment_redirect($url) {
            if (isset($_POST['autoglot_comment_language']) && strlen($_POST['autoglot_comment_language']) && $this->language_active($_POST['autoglot_comment_language'])) {
                $trylang = $_POST['autoglot_comment_language'];
                if($this->options->translate_urls) $url = $this->translate_url($url, $trylang); 
                $url = autoglot_utils::add_language_to_url($url, $this->homeURL, $trylang);
            } else {
                $trylang = autoglot_utils::get_language_from_url($_SERVER['HTTP_REFERER'], $this->homeURL);
                if (strlen($trylang) && $this->language_active($trylang)) {
                    if($this->options->translate_urls) $url = $this->translate_url($url, $trylang); 
                    $url = autoglot_utils::add_language_to_url($url, $this->homeURL, $trylang);
                }
            }
            return $url;
        }

        /**
         * URL Translation Stuff
         */
       
        function translate_url($ourl, $langto) {
            $url = $ourl;
            $querypart = '';
            $anchorpart = '';
            $trurl = '';
            $homeurl = '';

            if (strpos($url, $this->homeURL) !== false) {
                $homeurl = $this->homeURL;
                $url = str_replace($this->homeURL, "", $url);
            }
            if (strpos($url, '#') !== false) {
                list ($url, $anchorpart) = explode('#', $url);
                $anchorpart = '#' . $anchorpart;
            }
            if (strpos($url, '?') !== false) {
                list ($url, $querypart) = explode('?', $url);
                $querypart = '?' . $querypart;
            }
            
            $urlparts = explode('/', $url);
            foreach ($urlparts as $part) {
                $trpart = "";

                if (!$part)
                    continue;

                if(preg_match_all("/[[:alpha:]]/u",trim(strip_tags($part))) > 0 ){ //if there is reasonable text here
                    $opart = str_replace('-', ' ', $part);
                    $trpart = $this->get_translation($opart, $langto,hash("md5", $opart),"url",0);
                    if(!$trpart) {//failed to translate at least one part, cancel all translation and return original URL
                        return $ourl;
                    }
                    //$trpart = str_replace(array('?','!','.',',',';','\'','"'), '', $trpart);
                    //$trpart = preg_replace('/--+/', '-', $trpart);
                    $trpart = str_replace('-', '--', $trpart);
                    $trpart = str_replace(' ', '-', $trpart);
                    $trpart = mb_strtolower($trpart, 'UTF-8');
                }
                else {
                    $trpart = $part;    
                }

                $trurl .= '/'.$trpart;

            }
            //$url = str_replace($this->homeURL,"",$url);
            return trailingslashit($homeurl.$trurl).$querypart.$anchorpart;
        }
       
        function translate_urls($urls, $langto) {
            $allparts = array();
            //first, grab all parts from all urls
            foreach($urls as $url){
                if (strpos($url, '#') !== false) {
                    $url = substr($url,0,strpos($url, '#'));
                }
                if (strpos($url, '?') !== false) {
                    $url = substr($url,0,strpos($url, '?'));
                }
                $urlparts = explode('/', str_replace($this->homeURL, "", $url));
                foreach ($urlparts as $part) {
                    if (!$part)
                        continue;
                    if(preg_match_all("/[[:alpha:]]/u",trim(strip_tags($part))) > 0 ){ //if there is reasonable text here
                        $allparts[$part] = $part;
                    }
                }
            }

            $trparts = array();
            //next, translate them
            foreach($allparts as $part){
                $opart = str_replace('-', ' ', $part);
                $trpart = $this->get_translation($opart, $langto,hash("md5", $opart),"url",0);
                if($trpart) {//not failed to translate this part
                    $trparts[$part] = $trpart;
                }
            }
            
            $trurls = array();
            //finally, update URLs
            foreach($urls as $ourl){
                $querypart = '';
                $anchorpart = '';
                $trurl = '';
                $homeurl = '';
                
                if (strpos($ourl, $this->homeURL) !== false) {
                    $homeurl = $this->homeURL;
                    $url = str_replace($this->homeURL, "", $ourl);
                } else $url = $ourl;
                if (strpos($url, '#') !== false) {
                    list ($url, $anchorpart) = explode('#', $url);
                    $anchorpart = '#' . $anchorpart;
                }
                if (strpos($url, '?') !== false) {
                    list ($url, $querypart) = explode('?', $url);
                    $querypart = '?' . $querypart;
                }
                
                $urlparts = explode('/', $url);
                foreach ($urlparts as $part) {
                    $trpart = "";
    
                    if (!$part)
                        continue;
    
                    if(preg_match_all("/[[:alpha:]]/u",trim(strip_tags($part))) > 0 ){ //if there is reasonable text here
                        if(isset($trparts[$part]) && strlen($trparts[$part])){
                            $trpart = $trparts[$part];
                        } else {
                            $trurl = false;
                            break; //stop and skip current URL 
                        }
                        
                        $trpart = str_replace('-', '--', $trpart);
                        $trpart = str_replace(' ', '-', $trpart);
                        $trpart = mb_strtolower($trpart, 'UTF-8');
                    }
                    else {
                        $trpart = $part;    
                    }
    
                    $trurl .= '/'.$trpart;
    
                }
                //$url = str_replace($this->homeURL,"",$url);
                
                if($trurl) $trurls[hash("md5", $homeurl.$url.$querypart.$anchorpart)] = trailingslashit(autoglot_utils::add_language_to_url($homeurl.$trurl, $homeurl, $langto)).$querypart.$anchorpart;
            }

            return $trurls;
        }
        
        
        /**
         * Content Translation Stuff
         */

        function translate_content($content, $langto, $type, $postid = 0) {
            if(is_array($content)) {
                foreach($content as $h=>$c){
                    if(preg_match_all("/[[:alpha:]]/u",trim(strip_tags($c))) > 0 ){ //if there is reasonable text here
                        $trcontent[$h] = $this->get_translation($c,$langto,$h,$type,$postid);
                    } 
                    else {
                        $trcontent[$h] = $c;
                    }
                }
            }
            else{//todo: check lengths
                $trcontent = $this->get_translation($content,$langto,hash("md5", $content),$type,$postid);
            } 
            
            return $trcontent;
        }

        /**
         * Checks if translation exists in DB. If not, translate and save it; if yes - retrieve translation from DB. 
         */

        function get_translation($string, $langto, $hash, $type, $postid) {
            if (!($this->language_active($langto))) {
                return;
            }
            $translated = null;

//            $query = $GLOBALS['wpdb']->prepare("SELECT translated FROM {$this->autoglot_database->get_translation_table()} WHERE texthash = %s AND lang = %s AND type = %s", [$hash, $langto, $type]);
            $query = $GLOBALS['wpdb']->prepare("SELECT translated FROM {$this->autoglot_database->get_translation_table()} WHERE texthash = %s AND lang = %s", [$hash, $langto]);
            $row = $GLOBALS['wpdb']->get_row($query);

            if ($row !== null) { //translation found
                $translated = stripslashes($row->translated);
                if($translated == AUTOGLOT_TRANSLATION_INPROGRESS){
                    if($type != "url") $translated = $string;//translation already in progress, use original for now....
                    else return false;    
                }
            }
            elseif(!$this->options->translation_enable) {//new translation is not enabled
                if($type != "url") $translated = $string;
                else return false;    
            }
            elseif($this->curl->getBalance() > 0){
//                $addquery = $GLOBALS['wpdb']->prepare("INSERT INTO {$this->autoglot_database->get_translation_table()} (texthash, lang, original, translated, type, timestamp, postid) VALUES (%s, %s, %s, %s, %s, NOW(), %d)", array($hash, $langto, $string, AUTOGLOT_TRANSLATION_INPROGRESS, $type, $postid)); //TODO!!         
//                $addresult = $GLOBALS['wpdb']->query($addquery);
                $insertarray = array("texthash" => $hash, "lang" => $langto, "original" => $string, "translated" => AUTOGLOT_TRANSLATION_INPROGRESS, "timestamp" => current_time("mysql"), "type" => $type, "postid" => $postid);
                $formatarray = array("%s", "%s", "%s", "%s", "%s", "%s", "%d");
                $insertquery = $GLOBALS['wpdb']->insert($this->autoglot_database->get_translation_table(), $insertarray, $formatarray);
                if($insertquery === false){return $string;}//DB failure?
                
                $insertID = $GLOBALS['wpdb']->insert_id;
                
                $translated = $this->curl->getTranslation($string, $langto);//where magic happens!
                
                //TODO: transliterate URLs here
                if($type == "url") {
                    $translated = html_entity_decode($translated, ENT_QUOTES);
                    if($this->options->translate_urls == AUTOGLOT_TRANSLITERATE_URLS){
                        $translated = autoglot_utils::transliterate_url($translated);
                    }
                    $translated = str_replace(array('?','!','.',',',';','\'','"'), '', $translated);
                }
                
                if($translated != false){
                    $addquery = $GLOBALS['wpdb']->prepare("UPDATE {$this->autoglot_database->get_translation_table()} SET translated = %s WHERE texthash = %s AND lang = %s AND original = %s AND type = %s AND id = %d", array($translated, $hash, $langto, $string, $type, $insertID)); //TODO!!         
                    $addresult = $GLOBALS['wpdb']->query($addquery);
                    //$translated .= $GLOBALS['wpdb']->last_error;
    
                    if ($addresult === FALSE) {
                        //notify admin
                    }
                }
                else {
                    $deletearray["id"] = $insertID;
                    $formatarray = array("%d");
                    $GLOBALS['wpdb']->delete($this->autoglot_database->get_translation_table(), $deletearray, $formatarray);
                    if($type != "url") $translated = $string;
                    else return false;    
                }
            }
            else {
                $this->curl->sendBalanceNotification();
                if($type != "url") $translated = $string;
                else return false;    
            }
            
            switch($type){
                case "html":
                case "content":
                case "default":
                case "manual":
                    $translated = wp_kses($translated, $this->allowed_html);
                    $translated = autoglot_utils::format_HTML_translation($translated, in_array($langto, autoglot_consts::ALLOW_PUNCTUATION_SPACING));
                    break;
                case "widget_tit":
                case "title":
                case "tags":
                case "categories":
                    $translated = wp_strip_all_tags($translated);
                    break;
                case "text":
                    $translated = esc_html($translated);
                    break;
                default:
                    break;
            }
            return $translated;
        }
        
       
        /**
         * Custom search function - search in translated DB for translated phrases
         */
        function autoglot_search( $search, $wp_query) {
            global $wpdb;
            if(empty($search)) {
                return $search; // skip processing - no search term in query
            }
            $q = $wp_query->query_vars;
            $n = !empty($q['exact']) ? '' : '%';
            $search =
            $searchand = '';
            foreach ((array)$q['search_terms'] as $term) {
                $term = esc_sql($wpdb->esc_like($term));
                $search .= "{$searchand}($wpdb->posts.ID IN (SELECT ".$GLOBALS['wpdb']->prefix.AUTOGLOT_TABLE.".postid FROM ".$GLOBALS['wpdb']->prefix.AUTOGLOT_TABLE." WHERE ".$GLOBALS['wpdb']->prefix.AUTOGLOT_TABLE.".translated LIKE '{$n}{$term}{$n}' AND ".$GLOBALS['wpdb']->prefix.AUTOGLOT_TABLE.".lang = '{$this->langURL}' AND ".$GLOBALS['wpdb']->prefix.AUTOGLOT_TABLE.".type = 'content'))";
                $searchand = ' AND ';
            }
            if (!empty($search)) {
                $search = " AND ({$search}) ";
                if (!is_user_logged_in())
                    $search .= " AND ($wpdb->posts.post_password = '') ";
            }
            return $search;       
        }

        /**
         * Determine if the given language in on the list of active languages
         * @return boolean Is this language viewable?
         */
        function language_active($language) {
            return in_array($language, $this->options->active_languages, true);
        }

    }

    $new_autoglot = new Autoglot();

}

?>