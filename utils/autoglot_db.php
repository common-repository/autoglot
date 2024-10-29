<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: Autoglot DB functions
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

class autoglot_database {

    /** @var autoglot_plugin father class */
    private $autoglot;

    /** @var array holds prefetched translations */
    private $translations;

    /** @var string translation table name */
    private $autoglot_table;

    /**
     * PHP5+ only
     */
    function __construct(&$autoglot) {
        $this->autoglot = &$autoglot;
        $this->autoglot_table = $GLOBALS['wpdb']->prefix . AUTOGLOT_TABLE;
    }

    /**
     * Setup the translation database.
     */

    function setup_db($force = false) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		$autoglot_table = $GLOBALS['wpdb']->prefix . AUTOGLOT_TABLE; //rewrite in case of multisite activation, prefixes will be different for each blog

        $installed_ver = get_option(AUTOGLOT_DB_VERSION_KEY);

        if ($installed_ver != AUTOGLOT_DB_VERSION || $force) {
            $timestamp = filter_var(get_option(AUTOGLOT_DB_SETUP_KEY, 0), FILTER_VALIDATE_INT);
            if (time() - 7200 > $timestamp || $force) { //two hours are more than enough
                delete_option(AUTOGLOT_DB_SETUP_KEY);
            } else {
                // we don't want to upgrade autoglot tables more than once
                return;
            }
            update_option(AUTOGLOT_DB_SETUP_KEY, time());

            // notice - keep every field on a new line or dbdelta fails
            /*if($GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '{$autoglot_table}'") == $autoglot_table) {
                $rows = $GLOBALS['wpdb']->get_results("SHOW INDEX FROM {$autoglot_table} WHERE key_name = 'PRIMARY'");
                if (count($rows)) {
                    $GLOBALS['wpdb']->query("ALTER TABLE {$autoglot_table} DROP PRIMARY KEY");
                }
            }*/
            $sql = "CREATE TABLE {$autoglot_table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
                    texthash VARCHAR(100) NOT NULL, 
                    lang CHAR(5) NOT NULL, 
                    original TEXT, 
                    translated TEXT, 
                    type VARCHAR(10), 
                    timestamp TIMESTAMP, 
                    postid BIGINT(20), 
                    PRIMARY KEY  (id),
                    KEY texthashlang (texthash,lang)
                    )";

            dbDelta($sql);
            if ($GLOBALS['wpdb']->charset === 'utf8mb4') {
                $GLOBALS['wpdb']->query("ALTER TABLE {$autoglot_table} CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                $GLOBALS['wpdb']->query("ALTER TABLE {$autoglot_table} CONVERT TO CHARSET utf8 COLLATE utf8_general_ci");
            }

            // do the cleanups too
            update_option(AUTOGLOT_DB_VERSION_KEY, AUTOGLOT_DB_VERSION);
            delete_option(AUTOGLOT_DB_SETUP_KEY);
        }
    }

    /**
     * Provides some stats about our database
     */
    function db_exists() {
        if($GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '{$this->autoglot_table}'") == $this->autoglot_table) return true;
        else return false;
    }

    /**
     * Provides some stats about our database
     */
    function db_stats() {

        $return = array();
        $return['wpcount'] = $return['countall'] = $return['countunique'] = $return['countlang'] = $return['size'] = $return['countwords'] = $return['recent_d'] = 0;
        $return['countactivewords'] = array_fill_keys($this->autoglot->options->active_languages, 0);
        $return['recent_l'] = "n/a";
         
		$posts = get_posts( array(
			'numberposts' => -1,
			'post_type' => array('post', 'page')
		));
		foreach( $posts as $post ) {
			$return['wpcount'] += str_word_count( strip_tags( get_post_field( 'post_content', $post->ID )));
		}

        //TODO: prepare table name in WP 6.2 
        $query = $GLOBALS['wpdb']->prepare("SELECT count(*) as countall, count(DISTINCT `texthash`) as countunique, count(DISTINCT `lang`) as countlang FROM `{$this->autoglot_table}` WHERE %d;", 1);
        $row = $GLOBALS['wpdb']->get_row($query);
        if ($row->countall){
            $return['countall'] = $row->countall; 
        }
        if ($row->countunique){
            $return['countunique'] = $row->countunique; 
        }
        if ($row->countlang){
            $return['countlang'] = $row->countlang; 
        }

/*      See you in mysql 8.0
        $query = $GLOBALS['wpdb']->prepare("SELECT sum(LENGTH(translated) - LENGTH(REPLACE(translated, ' ', '')) + 1) as countwords FROM (SELECT REGEXP_REPLACE(translated, '<[^>]*>+', '') AS clean_translated FROM `{$this->autoglot_table}`) as tp;");
        $row = $GLOBALS['wpdb']->get_row($query);
        if ($row->countwords){
            $return['countwords'] = $row->countwords; 
        }*/

        $query = $GLOBALS['wpdb']->prepare("SELECT original, translated, lang, postid FROM `{$this->autoglot_table}` WHERE %d;",1);
//        $query = "SELECT translated FROM `{$this->autoglot_table}`";
        $rows = $GLOBALS['wpdb']->get_results($query);$i=0;
        foreach ($rows as $row) {
            $word_count = 0;
            $string = strip_tags($row->translated);
            $word_count = autoglot_utils::str_word_count_utf8($string);
            $return['countwords'] += $word_count;
            if(in_array($row->lang,$this->autoglot->options->active_languages)) {
				$string_o = strip_tags($row->original);
				$word_count_o = autoglot_utils::str_word_count_utf8($string_o);
                $return['countactivewords'][$row->lang] += $word_count_o;
            }
        }

        $query = $GLOBALS['wpdb']->prepare("SELECT * FROM `{$this->autoglot_table}` WHERE %d ORDER BY `timestamp` DESC LIMIT 1", 1);
//        $query = "SELECT * FROM `{$this->autoglot_table}` ORDER BY `timestamp` DESC LIMIT 1";
        $row = $GLOBALS['wpdb']->get_row($query);
        if($row) {
            $return['recent_d'] = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row->timestamp);
            $return['recent_l'] = autoglot_utils::get_language_name($row->lang);
        }

        $query = $GLOBALS['wpdb']->prepare("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH)) AS size FROM information_schema.TABLES WHERE TABLE_NAME LIKE %s;", $this->autoglot_table); 
//        $query = "SELECT ROUND((DATA_LENGTH + INDEX_LENGTH)) AS size FROM information_schema.TABLES WHERE TABLE_NAME = '{$this->autoglot_table}'"; 
        $row = $GLOBALS['wpdb']->get_row($query);
        if($row)$return['size'] = $row->size;
        
        return($return);
    }


    /**
     * Check DB for utilities admin page
     */
    function db_utilities_check() {

        $return = array();

        $query = $GLOBALS['wpdb']->prepare("SELECT count(*) as countempty FROM `{$this->autoglot_table}` WHERE translated LIKE '' OR translated LIKE %s;", AUTOGLOT_TRANSLATION_INPROGRESS);
        $row = $GLOBALS['wpdb']->get_row($query);
        if ($row->countempty){
            $return['countempty'] = $row->countempty; 
        }else $return['countempty'] = 0;

        $query = $GLOBALS['wpdb']->prepare("SELECT sum(cnt) AS countduplicate FROM (SELECT count(*) as cnt FROM `{$this->autoglot_table}` GROUP BY texthash, lang HAVING cnt > %d) as tp;", 1);
        $row = $GLOBALS['wpdb']->get_row($query);
        if ($row->countduplicate){
            $return['countduplicate'] = $row->countduplicate; 
        }else $return['countduplicate'] = 0;

        return($return);
    }


    /**
     * return translation table name
     */
    function get_translation_table() {
        return $this->autoglot_table;
    }

    /**
     * 
     * @param type $source
     * @param type $date
     * @param type $limit
     * @param type $orderby
     * @param type $order
     * @param type $filter
     * @return type
     */
    function get_translation_distinct_field($field) {
        $where = "";

        $query = "SELECT DISTINCT $field " . 
                "FROM {$this->autoglot_table} " .
                "$where " .
                "ORDER BY $field";

        $rows = $GLOBALS['wpdb']->get_results($query, ARRAY_A);
        return $rows;
    }

    /**
     * 
     * @param type $source
     * @param type $date
     * @param type $limit
     * @param type $orderby
     * @param type $order
     * @param type $filter
     * @return type
     */
    function get_translations($date = 'null', $limit = '', $orderby = 'timestamp', $order = 'DESC', $filter = '') {
        $limitterm = '';
        $dateterm = '';
        $where = "";

        if ($date != "null") {
            $dateterm = "";
            $dateterm .= "UNIX_TIMESTAMP(timestamp) > $date";
        }
        if ($dateterm && $filter) {
            $filter = "AND " . $filter;
        }
        if ($dateterm || $filter) {
            $where = "WHERE " . $dateterm . $filter;
        }
        if ($limit)
            $limitterm = "LIMIT $limit";
        $query = "SELECT * " . 
                "FROM {$this->autoglot_table} " .
                "$where " .
                "ORDER BY $orderby $order $limitterm";

        $rows = $GLOBALS['wpdb']->get_results($query, ARRAY_A);
        return $rows;
    }
    
    /**
     * 
     * @param type $source
     * @param type $date
     * @param type $limit
     * @param type $by
     * @param type $order
     * @param type $filter
     * @return type
     */
    function get_translations_count($date = 'null', $filter = '') {
        $dateterm = '';
        $where = "";

        if ($date != "null") {
            $dateterm = "";
            $dateterm .= "UNIX_TIMESTAMP(timestamp) > $date";
        }
        if (($dateterm) && $filter) {
            $filter = "AND " . $filter;
        }
        if ($dateterm || $filter) {
            $where = "WHERE " . $dateterm . $filter;
        }
        $query = "SELECT count(*) " . //original, lang, translated, translated_by, UNIX_TIMESTAMP(timestamp) as timestamp " .
                "FROM {$this->autoglot_table} " .
                "$where;";

        $count = $GLOBALS['wpdb']->get_var($query);
        return $count;
    }

    /**
     * Delete a specific translation history from the logs
     * @param string $token
     * @param string $lang
     * @param string $timestamp
     */

    function del_translation($ida) {
        $id = (int)$ida;
        $recordsfound = false;
        $query = $GLOBALS['wpdb']->prepare("SELECT translated, timestamp " .
                "FROM {$this->autoglot_table} " .
                "WHERE id=%d " .
                "ORDER BY timestamp DESC", [$id]);
        $rows = $GLOBALS['wpdb']->get_results($query);
        if (!empty($rows)) {
            $recordsfound = true;
        }

        // We only delete if we found something to delete and it is allowed to delete it (user either did that - by ip, has the translator role or is an admin)
        if (($recordsfound) && ((is_user_logged_in() && current_user_can('manage_options')))) {
                // delete from main table
                $query = $GLOBALS['wpdb']->prepare("DELETE " .
                        "FROM {$this->autoglot_table} " .
                        "WHERE id=%d ", [$id]);
                $return = $GLOBALS['wpdb']->query($query);

            return $return;
        } else {
            return false;
        }
    }
    

    /**
     * Update a specific translation history from the logs
     * @param string $token
     * @param string $lang
     * @param string $timestamp
     */

    function update_translation($ida, $content) {
        $id = (int)$ida;
        $recordsfound = false;
        $query = $GLOBALS['wpdb']->prepare("SELECT translated, timestamp " .
                "FROM {$this->autoglot_table} " .
                "WHERE id=%d " .
                "ORDER BY timestamp DESC", [$id]);
        $rows = $GLOBALS['wpdb']->get_results($query);
        if (!empty($rows)) {
            $recordsfound = true;
        }

        // We only update if we found something to update and it is allowed to update it
        if (($recordsfound) && ((is_user_logged_in() && current_user_can('manage_options')))) {
                // delete from main table
                $query = $GLOBALS['wpdb']->prepare("UPDATE {$this->autoglot_table} " .
                        "SET translated=%s " .
                        "WHERE id=%d", [$content, $id]);
                $return = $GLOBALS['wpdb']->query($query);

            return $return;
        } else {
            return false;
        }
    }
    

    /**
     * Try to restore original URL from translated
     * @param string $token
     * @param string $lang
     * @param string $timestamp
     */

    function restore_url($url, $lang) {
        $query = $GLOBALS['wpdb']->prepare("SELECT original " .
                "FROM {$this->autoglot_table} " .
                "WHERE original IS NOT NULL AND LOWER(translated) = %s AND lang = %s ", [$url, $lang]);
        $rows = $GLOBALS['wpdb']->get_row($query);

        if (!empty($rows->original)) {
            return $rows->original;
        } else {
            return false;
        }
    }    
}
