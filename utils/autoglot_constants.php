<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: Autoglot constants class
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

class autoglot_consts {

    /**
     * array of languages, flags and names
    */
    const LANGUAGES = array(
        'ar' => array("name" => "Arabic", "oname" => "العربية", "flag" => ["ara","glb","sa","ae","bh","dz","eg","jo","ma","om","tn","ye"], "locale" => ""),
        'hy' => array("name" => "Armenian", "oname" => "Հայերեն", "flag" => "am", "locale" => ""),
        'az' => array("name" => "Azerbaijani", "oname" => "azərbaycan dili", "flag" => "az", "locale" => ""),
        'be' => array("name" => "Belarusian", "oname" => "Беларуская", "flag" => "by", "locale" => ""),
        'bs' => array("name" => "Bosnian", "oname" => "bosanski jezik", "flag" => "ba", "locale" => "bs_BA"),
        'bg' => array("name" => "Bulgarian", "oname" => "Български", "flag" => "bg", "locale" => "bg_BG"),
        'zh' => array("name" => "Chinese Smpl", "oname" => "中文(简体)", "flag" => "cn", "locale" => "zh_CN"),
        'zh-tw' => array("name" => "Chinese Trad", "oname" => "中文(漢字)", "flag" => ["zho","glb","tw", "hk"], "locale" => "zh_TW"),
        'hr' => array("name" => "Croatian", "oname" => "Hrvatski", "flag" => "hr", "locale" => ""),
        'cs' => array("name" => "Czech", "oname" => "Čeština", "flag" => "cz", "locale" => "cs_CZ"),
        'da' => array("name" => "Danish", "oname" => "Dansk", "flag" => "dk", "locale" => "da_DK"),
        'nl' => array("name" => "Dutch", "oname" => "Nederlands", "flag" => ["nl","glb","sr"], "locale" => "nl_NL"),
        'en' => array("name" => "English", "oname" => "English", "flag" => ["usgb","us","gb","eng","glb","ag","ai","au","bb","bm","bs","bz","cx","dm","fj","gd","gg","gh","gi","gm", "im","io","je","jm","ki","kn","ky","lr","ms","ng","sb","sl","to","tt","tv","zm"], "locale" => "en_US"),
        'et' => array("name" => "Estonian", "oname" => "Eesti keel", "flag" => "ee", "locale" => ""),
        'fil' => array("name" => "Filipino", "oname" => "Wikang Filipino", "flag" => "ph", "locale" => ""),
        'fi' => array("name" => "Finnish", "oname" => "Suomi", "flag" => "fi", "locale" => ""),
        'fr' => array("name" => "French", "oname" => "Français", "flag" => ["fr","fra","glb","bf","bi","bj","cf","ga","gn","mc","ml","mq","ne","pf","sn","tg"], "locale" => "fr_FR"),
        'ka' => array("name" => "Georgian", "oname" => "ქართული", "flag" => "ge", "locale" => "ka_GE"),
        'de' => array("name" => "German", "oname" => "Deutsch", "flag" => ["de","deu","glb","at"], "locale" => "de_DE"),
        'el' => array("name" => "Greek", "oname" => "Ελληνικά", "flag" => "gr", "locale" => ""),
        'he' => array("name" => "Hebrew", "oname" => "עברית", "flag" => "il", "locale" => "he_IL"),
        'hi' => array("name" => "Hindi", "oname" => "हिन्दी; हिंदी", "flag" => "in", "locale" => "hi_IN"),
        'hu' => array("name" => "Hungarian", "oname" => "Magyar", "flag" => "hu", "locale" => "hu_HU"),
        'id' => array("name" => "Indonesian", "oname" => "Bahasa Indonesia", "flag" => "id", "locale" => "id_ID"),
        'it' => array("name" => "Italian", "oname" => "Italiano", "flag" => ["it","glb","sm"], "locale" => "it_IT"),
        'ja' => array("name" => "Japanese", "oname" => "日本語", "flag" => "jp", "locale" => ""),
        'kk' => array("name" => "Kazakh", "oname" => "Қазақ тілі", "flag" => "kz", "locale" => ""),
        'ko' => array("name" => "Korean", "oname" => "한국어", "flag" => "kr", "locale" => "ko_KR"),
        'lv' => array("name" => "Latvian", "oname" => "Latviešu valoda", "flag" => "lv", "locale" => ""),
        'lt' => array("name" => "Lithuanian", "oname" => "Lietuvių kalba", "flag" => "lt", "locale" => ""),
        'ms' => array("name" => "Malay", "oname" => "Bahasa Melayu", "flag" => "my", "locale" => "ms_MY"),
        'mt' => array("name" => "Maltese", "oname" => "Malti", "flag" => "mt", "locale" => ""),
        'no' => array("name" => "Norwegian", "oname" => "Norsk", "flag" => "no", "locale" => "nb_NO"),
        'fa' => array("name" => "Persian", "oname" => "پارسی", "flag" => "ir", "locale" => "fa_IR"),
        'pl' => array("name" => "Polish", "oname" => "Polski", "flag" => "pl", "locale" => "pl_PL"),
        'pt' => array("name" => "Portuguese", "oname" => "Português", "flag" => ["pt","br","por","glb","ao","cv","gw","mz"], "locale" => "pt_PT"),
        'ro' => array("name" => "Romanian", "oname" => "Română", "flag" => "ro", "locale" => "ro_RO"),
        'ru' => array("name" => "Russian", "oname" => "Русский", "flag" => "ru", "locale" => "ru_RU"),
        'sr' => array("name" => "Serbian", "oname" => "Cрпски језик", "flag" => "rs", "locale" => "sr_RS"),
        'sk' => array("name" => "Slovak", "oname" => "Slovenčina", "flag" => "sk", "locale" => "sk_SK"),
        'sl' => array("name" => "Slovene", "oname" => "Slovenščina", "flag" => "si", "locale" => "sl_SI"),
        'es' => array("name" => "Spanish", "oname" => "Español", "flag" => ["es","esp","glb","ar","cl","co","cr","cu","do","ec","gt","hn","mx","ni","pa","pe","sv","uy","ve"], "locale" => "es_ES"),
        'sv' => array("name" => "Swedish", "oname" => "Svenska", "flag" => ["se","glb","ax"], "locale" => "sv_SE"),
        'tg' => array("name" => "Tajik", "oname" => "Тоҷикӣ", "flag" => "tj", "locale" => ""),
        'th' => array("name" => "Thai", "oname" => "ภาษาไทย", "flag" => "th", "locale" => ""),
        'tr' => array("name" => "Turkish", "oname" => "Türkçe", "flag" => "tr", "locale" => "tr_TR"),
        'uk' => array("name" => "Ukrainian", "oname" => "Українська", "flag" => "ua", "locale" => ""),
        'uz' => array("name" => "Uzbek", "oname" => "Oʻzbek tili", "flag" => "uz", "locale" => "uz_UZ"),
        'vi' => array("name" => "Vietnamese", "oname" => "Tiếng Việt", "flag" => "vn", "locale" => "")    
/*        'ar' => array("name" => "Arabic", "oname" => "العربية", "flag" => "sa", "locale" => ""),
        'hy' => array("name" => "Armenian", "oname" => "Հայերեն", "flag" => "am", "locale" => ""),
        'az' => array("name" => "Azerbaijani", "oname" => "azərbaycan dili", "flag" => "az", "locale" => ""),
        'be' => array("name" => "Belarusian", "oname" => "Беларуская", "flag" => "by", "locale" => ""),
        'bs' => array("name" => "Bosnian", "oname" => "bosanski jezik", "flag" => "ba", "locale" => "bs_BA"),
        'bg' => array("name" => "Bulgarian", "oname" => "Български", "flag" => "bg", "locale" => "bg_BG"),
        'zh' => array("name" => "Chinese Smpl", "oname" => "中文(简体)", "flag" => "cn", "locale" => "zh_CN"),
        'zh-tw' => array("name" => "Chinese Trad", "oname" => "中文(漢字)", "flag" => "tw", "locale" => "zh_TW"),
        'hr' => array("name" => "Croatian", "oname" => "Hrvatski", "flag" => "hr", "locale" => ""),
        'cs' => array("name" => "Czech", "oname" => "Čeština", "flag" => "cz", "locale" => "cs_CZ"),
        'da' => array("name" => "Danish", "oname" => "Dansk", "flag" => "dk", "locale" => "da_DK"),
        'nl' => array("name" => "Dutch", "oname" => "Nederlands", "flag" => "nl", "locale" => "nl_NL"),
        'en' => array("name" => "English", "oname" => "English", "flag" => "us", "locale" => "en_US"),
        'et' => array("name" => "Estonian", "oname" => "Eesti keel", "flag" => "ee", "locale" => ""),
        'fil' => array("name" => "Filipino", "oname" => "Wikang Filipino", "flag" => "ph", "locale" => ""),
        'fi' => array("name" => "Finnish", "oname" => "Suomi", "flag" => "fi", "locale" => ""),
        'fr' => array("name" => "French", "oname" => "Français", "flag" => "fr", "locale" => "fr_FR"),
        'ka' => array("name" => "Georgian", "oname" => "ქართული", "flag" => "ge", "locale" => "ka_GE"),
        'de' => array("name" => "German", "oname" => "Deutsch", "flag" => "de", "locale" => "de_DE"),
        'el' => array("name" => "Greek", "oname" => "Ελληνικά", "flag" => "gr", "locale" => ""),
        'he' => array("name" => "Hebrew", "oname" => "עברית", "flag" => "il", "locale" => "he_IL"),
        'hi' => array("name" => "Hindi", "oname" => "हिन्दी; हिंदी", "flag" => "in", "locale" => "hi_IN"),
        'hu' => array("name" => "Hungarian", "oname" => "Magyar", "flag" => "hu", "locale" => "hu_HU"),
        'id' => array("name" => "Indonesian", "oname" => "Bahasa Indonesia", "flag" => "id", "locale" => "id_ID"),
        'it' => array("name" => "Italian", "oname" => "Italiano", "flag" => "it", "locale" => "it_IT"),
        'ja' => array("name" => "Japanese", "oname" => "日本語", "flag" => "jp", "locale" => ""),
        'kk' => array("name" => "Kazakh", "oname" => "Қазақ тілі", "flag" => "kz", "locale" => ""),
        'ko' => array("name" => "Korean", "oname" => "한국어", "flag" => "kr", "locale" => "ko_KR"),
        'lv' => array("name" => "Latvian", "oname" => "Latviešu valoda", "flag" => "lv", "locale" => ""),
        'lt' => array("name" => "Lithuanian", "oname" => "Lietuvių kalba", "flag" => "lt", "locale" => ""),
        'ms' => array("name" => "Malay", "oname" => "Bahasa Melayu", "flag" => "my", "locale" => "ms_MY"),
        'mt' => array("name" => "Maltese", "oname" => "Malti", "flag" => "mt", "locale" => ""),
        'no' => array("name" => "Norwegian", "oname" => "Norsk", "flag" => "no", "locale" => "nb_NO"),
        'fa' => array("name" => "Persian", "oname" => "پارسی", "flag" => "ir", "locale" => "fa_IR"),
        'pl' => array("name" => "Polish", "oname" => "Polski", "flag" => "pl", "locale" => "pl_PL"),
        'pt' => array("name" => "Portuguese", "oname" => "Português", "flag" => "pt", "locale" => "pt_PT"),
        'ro' => array("name" => "Romanian", "oname" => "Română", "flag" => "ro", "locale" => "ro_RO"),
        'ru' => array("name" => "Russian", "oname" => "Русский", "flag" => "ru", "locale" => "ru_RU"),
        'sr' => array("name" => "Serbian", "oname" => "Cрпски језик", "flag" => "rs", "locale" => "sr_RS"),
        'sk' => array("name" => "Slovak", "oname" => "Slovenčina", "flag" => "sk", "locale" => "sk_SK"),
        'sl' => array("name" => "Slovene", "oname" => "Slovenščina", "flag" => "si", "locale" => "sl_SI"),
        'es' => array("name" => "Spanish", "oname" => "Español", "flag" => "es", "locale" => "es_ES"),
        'sv' => array("name" => "Swedish", "oname" => "Svenska", "flag" => "se", "locale" => "sv_SE"),
        'tg' => array("name" => "Tajik", "oname" => "Тоҷикӣ", "flag" => "tj", "locale" => ""),
        'th' => array("name" => "Thai", "oname" => "ภาษาไทย", "flag" => "th", "locale" => ""),
        'tr' => array("name" => "Turkish", "oname" => "Türkçe", "flag" => "tr", "locale" => "tr_TR"),
        'uk' => array("name" => "Ukrainian", "oname" => "Українська", "flag" => "ua", "locale" => ""),
        'uz' => array("name" => "Uzbek", "oname" => "Oʻzbek tili", "flag" => "uz", "locale" => "uz_UZ"),
        'vi' => array("name" => "Vietnamese", "oname" => "Tiếng Việt", "flag" => "vn", "locale" => "")*/
    );
    // right to left (rtl) languages 
    const RTL_LANGUAGES = array('ar', 'he', 'fa', 'ur', 'yi');
    
    // allow space before ?!;:
    const ALLOW_PUNCTUATION_SPACING = array('fr');
	
	const ADDITIONAL_TAGS_FOR_FORM = array(
			'form' => array(
				'action' => true,
				'name' => true,
				'type' => true,
				'value' => true,
			),
			'button' => array(
				'disabled' => true,
				'name' => true,
				'type' => true,
				'value' => true,
			),
			'datalist' => array(),
			'fieldset' => array(
				'disabled' => true,
				'name' => true,
			),
			'input' => array(
				'accept' => true,
				'alt' => true,
				'capture' => true,
				'checked' => true,
				'disabled' => true,
				'list' => true,
				'max' => true,
				'maxlength' => true,
				'min' => true,
				'minlength' => true,
				'multiple' => true,
				'name' => true,
				'placeholder' => true,
				'readonly' => true,
				'size' => true,
				'step' => true,
				'type' => true,
				'value' => true,
			),
			'label' => array(
				'for' => true,
			),
			'legend' => array(),
			'meter' => array(
				'value' => true,
				'min' => true,
				'max' => true,
				'low' => true,
				'high' => true,
				'optimum' => true,
			),
			'optgroup' => array(
				'disabled' => true,
				'label' => true,
			),
			'option' => array(
				'disabled' => true,
				'label' => true,
				'selected' => true,
				'value' => true,
			),
			'output' => array(
				'for' => true,
				'name' => true,
			),
			'progress' => array(
				'max' => true,
				'value' => true,
			),
			'select' => array(
				'disabled' => true,
				'multiple' => true,
				'name' => true,
				'size' => true,
			),
			'textarea' => array(
				'cols' => true,
				'disabled' => true,
				'maxlength' => true,
				'minlength' => true,
				'name' => true,
				'placeholder' => true,
				'readonly' => true,
				'rows' => true,
				'spellcheck' => true,
				'wrap' => true,
			),
		);
    
    const INLINE_TAGS = array("b", "big", "i", "small", "tt",
                "abbr", "acronym", "cite", "code", "dfn", "em", "kbd", "strong", "samp", "var",
                "a", "bdo", "br", "img", "map", "object", "q", "span", "sub", "sup",
                /*"button", "input", "label", "select", "textarea",*/ 
                /*"ul", "li", */"g",
                "style", "script"
                );

    const SKIP_TAGS = array("style", "script"
                );

    const REPEAT_BALANCE_NOTIFICATIONS = array(60=>"1m", 600=>"10m", 1800=>"30m", 3600=>"1h", 43200=>"12h", 86400=>"1d", 604800=>"1w", 2592000=>"1month");
    
    const LANGUAGE_SWITCHER_TYPES = array(
				'languagelist',//default
				'languageflagslist',
				'flagslist',
				'smallflagslist',
			);

    const LANGUAGE_NAME_TYPES = array(
				'nativeenglish',
				'native',//default
				'english',
				'englishnative',
				'iso',
				'nativeiso',
			);

    const INLINE_TAGS_EDITOR = array("b", "i", "small", "cite", "code", "em", "strong", "a", "br", "span", "sub", "sup", "h1", "h2", "h3", "h4", "h5", "h6");
}

//Define for autoglot plugin version
define('AUTOGLOT_PLUGIN_VER', '2.4.8');

//Define for autoglot plugin name
define('AUTOGLOT_PLUGIN_NAME', 'Autoglot Plugin');

//Current jQuery UI
define('AUTOGLOT_JQUERYUI_VER', '1.10.4');

//To keep links to other languages without changes, will be replaced with home URL later.
//define('AUTOGLOT_FAKE_URL', 'http://autoglot1fake2home3address');//Canceled 2.4

//Replace all attributes when translating.
define('AUTOGLOT_REPLACE_ATTRIBUTES', "agtr");

//Default Widget Title
define('AUTOGLOT_WIDGET_TITLE', 'Translation');

//Default Widget Signature
define('AUTOGLOT_WIDGET_SIGNATURE', 'Translation powered by<br /><a href="https://autoglot.com" target="_blank">Autoglot Wordpress Translation</a>');

//Low Balance Warning
define('AUTOGLOT_LOW_BALANCE', 1000);

//Attribute that means element will not be translated
define('AUTOGLOT_EXCLUDE_TRANSLATION', "ag_exclude_translation");

//Class marking a language switcher not be translated.
define('AUTOGLOT_NOTRANSLATE_LANGUAGESWITCHER', "ag_notranslateswitcher");

//Class marking a section not be translated.
define('AUTOGLOT_NOTRANSLATE_CLASS', 'notranslate');

define('AUTOGLOT_PLUGIN_ICON', 'PHN2ZyBkYXRhLXYtNDIzYmY5YWU9IiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgOTAgOTAiIGNsYXNzPSJpY29uTGVmdCI+PGcgZGF0YS12LTQyM2JmOWFlPSIiIGlkPSI1ODllZTE0MC0wMmUyLTRmOGMtYjg0ZS03NThhMGFlZTMwYjciIHRyYW5zZm9ybT0ibWF0cml4KDEuMzA2NDkyODA1NDgwOTU3LDAsMCwxLjMwNjQ5MjgwNTQ4MDk1NywtMjEuMDAzMDM0NTkxNjc0ODA1LC0xOS44Mjk2NTY2MDA5NTIxNSkiIHN0cm9rZT0ibm9uZSIgZmlsbD0iI0VFRUVFRSI+PHBhdGggZD0iTTM4LjcgNTAuM2MtLjMtLjctLjEtMS44LjMtMi41bDUuMy02LjljLjUtLjYgMS41LTEuMSAyLjMtLjlsOC43IDEuMmMuOC4xIDEuNy44IDIgMS41bDMuMyA4LjFjLjMuNy4xIDEuOC0uMyAyLjVMNTUgNjAuMmMtLjUuNi0xLjUgMS4xLTIuMy45TDQ0IDU5LjljLS44LS4xLTEuNy0uOC0yLTEuNWwtMy4zLTguMXpNNTguMSA2My45Yy0uMy0uNy0uMS0xLjguMy0yLjVsNS40LTYuOWMuNS0uNiAxLjUtMS4xIDIuMy0uOWw4LjcgMS4yYy44LjEgMS43LjggMiAxLjVsMy4zIDguMWMuMy43LjEgMS44LS4zIDIuNWwtNS4zIDYuOWMtLjUuNi0xLjUgMS4xLTIuMy45bC04LjctMS4yYy0uOC0uMS0xLjctLjgtMi0xLjVsLTMuNC04LjF6TTE5LjYgMzQuNmMtLjMtLjctLjItMS44LjMtMi41bDUuMy02LjljLjUtLjYgMS41LTEuMSAyLjMtLjlsOC43IDEuMmMuOC4xIDEuNy44IDIgMS41bDMuMyA4LjFjLjMuNy4xIDEuOC0uMyAyLjVMMzYgNDQuM2MtLjUuNi0xLjUgMS4xLTIuMyAxTDI1IDQ0LjJjLS44LS4xLTEuNy0uOC0yLTEuNWwtMy40LTguMXpNNDIuMSAyNi4xYy0uMy0uNy0uMS0xLjguMy0yLjVsNS40LTYuOWMuNS0uNiAxLjUtMS4xIDIuMy0uOWw4LjcgMS4yYy44LjEgMS43LjggMiAxLjVsMy4zIDguMWMuMy43LjEgMS44LS4zIDIuNUw1OC40IDM2Yy0uNS42LTEuNSAxLjEtMi4zLjlsLTguNy0xLjJjLS44LS4xLTEuNy0uOC0yLTEuNWwtMy4zLTguMXpNNjEuNiA0MGMtLjMtLjctLjEtMS44LjMtMi41bDUuMy02LjljLjUtLjYgMS41LTEuMSAyLjMtLjlsOC43IDEuMmMuOC4xIDEuNy44IDIgMS41bDMuMyA4LjFjLjMuNy4xIDEuOC0uMyAyLjVsLTUuNCA2LjljLS41LjYtMS41IDEuMS0yLjMuOWwtOC43LTEuMmMtLjgtLjEtMS43LS44LTItMS41TDYxLjYgNDB6TTE2LjQgNTguNWMtLjMtLjctLjEtMS44LjMtMi41bDUuMy02LjljLjUtLjYgMS41LTEuMSAyLjMtLjlsOC43IDEuMmMuOC4xIDEuNy44IDIgMS41bDMuMyA4LjFjLjMuNy4xIDEuOC0uMyAyLjVsLTUuMyA2LjljLS41LjYtMS41IDEuMS0yLjMuOWwtOC43LTEuMmMtLjgtLjEtMS43LS44LTItMS41bC0zLjMtOC4xek0zNS4zIDczLjhjLS4zLS43LS4xLTEuOC4zLTIuNWw1LjQtNi45Yy41LS42IDEuNS0xLjEgMi4zLS45bDguNyAxLjJjLjguMSAxLjcuOCAyIDEuNWwzLjMgOC4xYy4zLjcuMSAxLjgtLjMgMi41bC01LjQgNi45Yy0uNS42LTEuNSAxLjEtMi4zLjlsLTguNy0xLjJjLS44LS4xLTEuNy0uOC0yLTEuNWwtMy4zLTguMXoiPjwvcGF0aD48L2c+PC9zdmc+');

define('AUTOGLOT_SEARCH_TITLE', "Search Results for");
define('AUTOGLOT_ARCHIVE_TITLE', "Archive");

define('AUTOGLOT_TRANSLATE_URLS', 1);

define('AUTOGLOT_TRANSLITERATE_URLS', 2);

/********************** DB settings ****************************/

//Table name in database for storing translations
define('AUTOGLOT_TABLE', 'autoglot_translations');

//Database version
define('AUTOGLOT_DB_VERSION', '0.8');

//Constant used as version key in options database
define('AUTOGLOT_DB_VERSION_KEY', "autoglot_db_version");

//Constant used as DB update key in options database
define('AUTOGLOT_DB_SETUP_KEY', "autoglot_db_setupinprogress");

//Constant used as last notification sent in options database
define('AUTOGLOT_DB_LAST_NOTIFICATION', "autoglot_db_lastnotify");

//Constant used as last notification sent in options database
define('AUTOGLOT_TRANSLATION_INPROGRESS', "autoglot_translation_inprogress");

/********************** Link replacement settings ****************************/

//Language replacement shortcode
define('AUTOGLOT_ADDLINKCODE_LNG', '[lng]');

//TLD replacement shortcode
define('AUTOGLOT_ADDLINKCODE_DMN', '[dmn]');

//Additional language replacement shortcode
define('AUTOGLOT_ADDLINKCODE_LNG2', '[lng2]');

/********************** Textreplacement settings ****************************/

//Text replacement custom post type
define('AUTOGLOT_TEXTREPL_POSTTYPE', 'autoglot_textrepl');

/********************** default settings ****************************/

//show for admins only
define('AUTOGLOT_DEFAULT_ADMINONLY', 1);

//show or hide hreflangs alternates
define('AUTOGLOT_DEFAULT_SHOW_HREFLANGS', 1); //0.5.0

//show custom (almost default)) titles on search results, taxonomies and archive pages
define('AUTOGLOT_DEFAULT_CUSTOM_TITLES', 1); //0.9.2

//add widget signature
define('AUTOGLOT_DEFAULT_WIDGET_SIGNATURE', 0); //0.5.0

//reduce sitemap priority 
define('AUTOGLOT_DEFAULT_SITEMAP_PRIORITY', 0.1); //0.5.0

//translate URLs
define('AUTOGLOT_DEFAULT_TRANSLATE_URLS', 0); //2.4.0

//send balance notifications  
define('AUTOGLOT_DEFAULT_NOTIFY_BALANCE', 3600); //0.8.1

//enable float box + language switcher popup
define('AUTOGLOT_DEFAULT_FLOATBOX_SWITCHER', 0);

//skip caching of translated pages
define('AUTOGLOT_DEFAULT_SKIP_CACHING', 0);//2.2.1

/*********************** folders settings *********************/

define('AUTOGLOT_FOLDER_CSS', 'front/css/');
define('AUTOGLOT_FOLDER_IMG', 'front/img/');
define('AUTOGLOT_FOLDER_JS', 'front/js/');

define('AUTOGLOT_FILE_LANGUAGES', plugin_dir_path( dirname( __FILE__ ) ).'options/languages.xml');

/*********************** URL settings *********************/

define('AUTOGLOT_API_URL', 'https://api.autoglot.com/');

define('AUTOGLOT_MAIN_URL', 'https://autoglot.com/');
define('AUTOGLOT_PRICING_URL', 'https://autoglot.com/pricing/');
define('AUTOGLOT_CONTACT_URL', 'https://autoglot.com/contact-support/');

define('AUTOGLOT_CP_URL', 'https://cp.autoglot.com/');
define('AUTOGLOT_CP_URL_ORDER', 'https://cp.autoglot.com/order');
define('AUTOGLOT_CP_SUPPORT', 'https://cp.autoglot.com/support');
define('AUTOGLOT_CP_SIGNUP', 'https://cp.autoglot.com/signup');

define('AUTOGLOT_WP_URL', 'https://wordpress.org/plugins/autoglot/');
define('AUTOGLOT_WP_SUPPORT', 'https://wordpress.org/support/plugin/autoglot/');
define('AUTOGLOT_WP_REVIEWS', 'https://wordpress.org/support/plugin/autoglot/reviews/#new-post');

define('AUTOGLOT_LI_URL', 'https://www.linkedin.com/company/autoglot/');
define('AUTOGLOT_FB_URL', 'https://www.facebook.com/autoglot');
define('AUTOGLOT_TW_URL', 'https://twitter.com/autoglot_wp');

