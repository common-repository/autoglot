<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: Autoglot options class
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

define('AUTOGLOT_DEFAULT_STRINGS', array(AUTOGLOT_SEARCH_TITLE, AUTOGLOT_ARCHIVE_TITLE));

define('AUTOGLOT_MORE_LANGCODES', array('zh' => 'zh-chs', 'zh-tw' => 'zh-cht', 'mw' => '', 'ka' => '', 'be' => ''));

/**
 * Get options from admin
 * 
 */

class autoglot_options {

    /** @var autoglot_plugin father class */
    private $autoglot;

    public $translation_enable;
    public $translation_adminonly;
    public $show_hreflangs;
    public $custom_titles;
    public $skip_caching;
    public $widget_signature;
    public $reduce_sitemap_priority;
    public $repeat_balance_notifications;
    public $default_language;
    public $active_languages;
    public $language_flags;
    public $translation_API_key;
    public $manual_strings;
    public $translate_urls;
    public $add_lngcode;
    public $replace_text;
    public $floatbox_enable;
    public $popup_switcher;
    public $language_names;

    private function validate_checkbox($option, $default1 = 1, $max_value = 1){
        if(in_array($option, range(0, $max_value))) return $option;
        else return $default1;
/*        if($default1)
            return ($option=="0")?0:$default1;
        else
            return ($option=="1" || $option=="2")?$option:0;*/
    }

    function __construct(&$autoglot) {
        $this->autoglot = &$autoglot;
        
        $option = get_option('autoglot_translation_enable', array(""));
        $this->translation_enable = (is_array($option) && isset($option[0]) && $option[0]=="selected")?1:0;

        $option = get_option('autoglot_translation_adminonly', array(AUTOGLOT_DEFAULT_ADMINONLY));
        $this->translation_adminonly = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_ADMINONLY);
        
        $option = get_option('autoglot_translation_floatbox', array(AUTOGLOT_DEFAULT_FLOATBOX_SWITCHER));
        $this->floatbox_enable = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_FLOATBOX_SWITCHER);

        $option = get_option('autoglot_translation_popup_switcher', array(autoglot_consts::LANGUAGE_SWITCHER_TYPES[0]));
        $this->popup_switcher = in_array($option[0], autoglot_consts::LANGUAGE_SWITCHER_TYPES, true)?$option[0]: "";

        $option = get_option('autoglot_translation_language_names', array(autoglot_consts::LANGUAGE_NAME_TYPES[0]));
        $this->language_names = in_array($option[0], autoglot_consts::LANGUAGE_NAME_TYPES, true)?$option[0]: "";

        $option = get_option('autoglot_translation_hreflangs', array(AUTOGLOT_DEFAULT_SHOW_HREFLANGS));
        $this->show_hreflangs = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_SHOW_HREFLANGS);

        $option = get_option('autoglot_translation_custom_titles', array(AUTOGLOT_DEFAULT_CUSTOM_TITLES));
        $this->custom_titles = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_CUSTOM_TITLES);

        $option = get_option('autoglot_translation_skip_caching', array(AUTOGLOT_DEFAULT_SKIP_CACHING));
        $this->skip_caching = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_SKIP_CACHING);

        $option = get_option('autoglot_translation_widget_signature', array(AUTOGLOT_DEFAULT_WIDGET_SIGNATURE));
        $this->widget_signature = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_WIDGET_SIGNATURE);

        $option = get_option('autoglot_translation_translate_urls', array(AUTOGLOT_DEFAULT_TRANSLATE_URLS));
        $this->translate_urls = $this->validate_checkbox($option[0], AUTOGLOT_DEFAULT_TRANSLATE_URLS, 2);

        $option = get_option('autoglot_translation_sitemap_priority', array(AUTOGLOT_DEFAULT_SITEMAP_PRIORITY));
        $vldt = filter_var($option[0], FILTER_VALIDATE_FLOAT);
        $this->reduce_sitemap_priority = in_array($vldt, array_combine($r = range(0,0.5,0.1), $r), true)?$vldt: AUTOGLOT_DEFAULT_SITEMAP_PRIORITY;

        $option = get_option('autoglot_translation_repeat_balance', array(AUTOGLOT_DEFAULT_NOTIFY_BALANCE));
        $vldt = filter_var($option[0], FILTER_VALIDATE_INT);
        $this->repeat_balance_notifications = array_key_exists($vldt, autoglot_consts::REPEAT_BALANCE_NOTIFICATIONS)?$vldt: AUTOGLOT_DEFAULT_NOTIFY_BALANCE;

        $option = get_option('autoglot_translation_default_language', array(autoglot_utils::get_locale_code()));
        $this->default_language = in_array($option[0], array_keys(autoglot_utils::get_all_language_names()), true)?$option[0]: autoglot_utils::get_locale_code();

        $option = get_option('autoglot_translation_active_languages', array(autoglot_utils::get_locale_code()));
        $this->active_languages = array();
        foreach($option as $ln) {
            if(in_array($ln, array_keys(autoglot_utils::get_all_language_names()), true)){
                $this->active_languages[] = $ln;
            }
        }
        
        $allflags = autoglot_utils::get_all_language_flags();
        foreach($allflags as $lang => $options) {
            $option = get_option('autoglot_translation_language_flags_'.$lang, array($options["flags"][0]));
            if(is_array($option) && in_array($option[0], $options["flags"], true)){
                $this->language_flags[$lang] = $option[0];
            }
            else {
                $this->language_flags[$lang] = $options["flags"][0];
            }
        }
        
        $option = get_option('autoglot_translation_API_key');
        $this->translation_API_key = stripslashes($option);
        
        $option = get_option('autoglot_translation_manual_strings');
        $this->manual_strings = stripslashes($option);
        
        $option = get_option('autoglot_translation_add_lngcode');
        if(is_array($option)) {
            if(count($option) == 2) {
                $from = explode("\r\n", $option[0]);
                $to = explode("\r\n", $option[1]);
                if(is_array($from) && is_array($to) && count($from) == count ($to)) {
                    $this->add_lngcode[0] = $option[0];
                    $this->add_lngcode[1] = $option[1];
                } else 
                    $this->add_lngcode = NULL;
            } else 
                $this->add_lngcode = NULL;
        } else
            $this->add_lngcode = NULL;
        
        if(!is_admin()) {
            $replacement_posts = get_posts(array(
    			'numberposts' => -1,
    			'post_status'   => 'publish',
    			'post_type'   => AUTOGLOT_TEXTREPL_POSTTYPE,
    			'suppress_filters' => true,
    		));
    		foreach($replacement_posts as $post) {
                $meta_value = get_post_meta($post->ID, '_autoglot_textrepl_meta', true);
                if(@is_array($meta_value) && @array_key_exists('default', $meta_value) && @array_key_exists($this->autoglot->langURL, $meta_value) && @strlen(trim($meta_value['default'])) && @strlen(trim($meta_value[$this->autoglot->langURL]))){
                    $this->replace_text[$meta_value['default']] = wp_kses_post($meta_value[$this->autoglot->langURL]);
                }
            }
        }
    }

}

?>