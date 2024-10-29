<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: The admin-specific functionality of the plugin.
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class autoglot_editor extends WP_List_Table
{
    
    private $filter = "";

    private $search = "";

    private $request_fl = NULL;
    private $request_ft = NULL;
    
    private $languages = array();

    private $types = array();
    
	/**
	 * Parent class
	 */
    private $autoglot;
    private $autoglot_admin;

    function __construct(&$autoglot, &$autoglot_admin) {

        $this->autoglot = &$autoglot;
        $this->autoglot_admin = &$autoglot_admin;

        $this->search = (!empty($_GET['s']) ) ? filter_input(INPUT_GET, 's', FILTER_SANITIZE_SPECIAL_CHARS) : NULL;
        
        if(!empty($_GET['fl']) ) {
            $this->request_fl =  filter_input(INPUT_GET, 'fl', FILTER_SANITIZE_SPECIAL_CHARS);            
        } elseif (!empty($_POST['fl']) ) {
            $this->request_fl =  filter_input(INPUT_POST, 'fl', FILTER_SANITIZE_SPECIAL_CHARS);        
        } else $this->request_fl = NULL;
        
        if(!empty($_GET['ft']) ) {
            $this->request_ft  =  filter_input(INPUT_GET, 'ft', FILTER_SANITIZE_SPECIAL_CHARS);            
        } elseif (!empty($_POST['ft']) ) {
            $this->request_ft  =  filter_input(INPUT_POST, 'ft', FILTER_SANITIZE_SPECIAL_CHARS);        
        } else $this->request_ft  = NULL;

        global $status, $page;
        parent::__construct(array(
            'singular' => __('translation', 'autoglot'), //singular name of the listed records
            'plural' => __('translations', 'autoglot'), //plural name of the listed records
            'ajax' => true //does this table support ajax?
        ));
    }

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $filters = array();
        
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable, "translated");
        
        $orderby = (!empty($_GET['orderby']) ) ? filter_input(INPUT_GET, 'orderby', FILTER_SANITIZE_SPECIAL_CHARS) : 'timestamp';
        $order = (!empty($_GET['order']) ) ? filter_input(INPUT_GET, 'order', FILTER_SANITIZE_SPECIAL_CHARS) : 'desc';
        
        if ($this->request_fl) {
            $filters[] = "(lang = '" . esc_sql($this->request_fl) . "')";
        }
        if ($this->request_ft) {
            $filters[] = "(type = '" . esc_sql($this->request_ft) . "')";
        }

        if($this->search){
            $filters[] = '(original LIKE "%' . esc_sql($this->search) . '%" OR translated LIKE "%' . esc_sql($this->search) . '%")';
        }
        
        $this->filter = implode(" AND ", $filters);

        //$per_page = 5;
        $user = get_current_user_id();
        $screen = get_current_screen();
        $option = $screen->get_option('per_page', 'option');

        $per_page = get_user_meta($user, $option, true);

        if (empty($per_page) || $per_page < 1) {

            $per_page = $screen->get_option('per_page', 'default');
        }    

        $total_items = $this->autoglot->autoglot_database->get_translations_count('null', $this->filter);
        $current_page = $total_items > $per_page ? min($total_items/$per_page, $this->get_pagenum()) : 1;
        $limit = ($current_page - 1) * $per_page;
        $this->set_pagination_args(array(
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page //WE have to determine how many items to show on a page
        ));

        $this->languages = $this->autoglot->autoglot_database->get_translation_distinct_field("lang");
        $this->types = $this->autoglot->autoglot_database->get_translation_distinct_field("type");

        $this->items = $this->autoglot->autoglot_database->get_translations('null', "$limit, $per_page", $orderby, $order, $this->filter);
    }


    function render_table() {
        echo '</pre><div class="wrap">';
        $this->prepare_items();
        echo '
        <form method="get" action="'.admin_url("admin.php?".$_SERVER["QUERY_STRING"]).'">
            <input type="hidden" name="page" value="autoglot_translation_editor">';
        //if ($this->request_fl) {
        //    echo '<input type="text" name="fl" value="'.$this->request_fl.'">';    
        //}
        if ($this->filter) {
            echo "<a href='".admin_url("admin.php?page=autoglot_translation_editor")."' class='button'><i class='dashicons dashicons-remove'></i>&nbsp;".__('Remove all filters', 'autoglot')."</a>&nbsp;&nbsp;";
        }
        if(is_array($this->languages) && count($this->languages) > 1){
    		$options_markup = sprintf( '<option value="%s" %s>%s</option>', NULL, selected(NULL, $this->request_fl, false ), __('Choose language', 'autoglot'));
    		foreach( $this->languages as $alang ){
    			$options_markup .= sprintf( '<option value="%s" %s>%s</option>', $alang["lang"], selected($alang["lang"], $this->request_fl, false ), autoglot_utils::get_language_name($alang['lang']) );
    		}
			printf( '<select name="%1$s" id="%3$s" class="%4$s">%2$s</select>', "fl", $options_markup, "autoglot_translation_editor_select_language" , "autoglot_form_select_submit");
            echo "&nbsp;&nbsp;";
        } elseif($this->request_fl) {
            echo '<input type="hidden" name="fl" value="'.esc_attr($this->request_fl).'">';
        }    

        if(is_array($this->types) && count($this->types) > 1){
    		$options_markup = sprintf( '<option value="%s" %s>%s</option>', NULL, selected(NULL, $this->request_ft, false ), __('Choose type', 'autoglot'));
    		foreach( $this->types as $atype ){
    			$options_markup .= sprintf( '<option value="%s" %s>%s</option>', $atype["type"], selected($atype["type"], $this->request_ft, false ), ($atype['type']) );
    		}
			printf( '<select name="%1$s" id="%3$s" class="%4$s">%2$s</select>', "ft", $options_markup, "autoglot_translation_editor_select_type" , "autoglot_form_select_submit");
        } elseif($this->request_ft) {
            echo '<input type="hidden" name="ft" value="'.esc_attr($this->request_ft).'">';
        }

        
        $this->search_box(__('search', 'autoglot'), 'search_id');
        $this->display();
        echo '</form></div>';
    }
    
    
    /**
     * 
     */
    function perform_actions() {

        if ($this->current_action() === 'edit') {
            $nonce = $_REQUEST['_wpnonce'];
            if(isset($_REQUEST['key']) && strlen($_REQUEST['translated'])){ 
                if(wp_verify_nonce($nonce, 'edit') == 1) {
                    $id = $_REQUEST['key'];//validating/sanitizing in DB prepare
                    $translated = urldecode(wp_unslash($_REQUEST['translated']));
                    $translated = wp_kses($translated, $this->autoglot->allowed_html);
                    $return = $this->autoglot->autoglot_database->update_translation($id, $translated);
                    echo json_encode($return);
                    exit();
                } else {
                    echo json_encode(false);
                    exit();
                }
            }
        }
        elseif ($this->current_action() === 'delete') {
            $nonce = $_REQUEST['_wpnonce'];
            if(isset($_GET['key'])){
                if(wp_verify_nonce($nonce, 'delete') == 1) {
                    $id = $_GET['key'];//validating/sanitizing in DB prepare
                    $return = $this->autoglot->autoglot_database->del_translation($id);
                    echo json_encode($return);
                    exit();
                } else {
                    echo json_encode(false);
                    exit();
                }
            }
            $deleted = 0; 
            if (isset($_REQUEST['keys']) && wp_verify_nonce($nonce, 'bulk-translations') == 1) {
                foreach ($_REQUEST['keys'] as $id) {//validating/sanitizing in DB prepare
                    $return = $this->autoglot->autoglot_database->del_translation($id);
                    if($return) $deleted++;
                }
                update_option("autoglot_admin_notice", array(__( 'Rows deleted: ', 'autoglot' ).' '.$deleted, "success"));
                header("Location: ".admin_url(sanitize_url(sprintf('admin.php?page=%s%s%s%s', $_REQUEST['page'], ($this->search?"&s=".$this->search:""), ($this->request_fl?"&fl=".$this->request_fl:""), ($_REQUEST['paged']?"&paged=".$_REQUEST['paged']:"")))));
                exit();
            }
        }
    }
    
    function add_screen_options() {
        $option = 'per_page';
        $args = array(
            'label' => __('Translations', 'autoglot'),
            'default' => 100,
            'option' => 'translations_per_page'
        );
        add_screen_option($option, $args);
    }

    function no_items() {
        _e('No translations found.', 'autoglot');
    }
        
    function item_key($item) {
        //return base64_encode($item['timestamp'] . ',' . $item['lang'] . ',' . $item['original']);
        //return base64_encode($item['id']);
        return $item['id'];
    }

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'original':
            case 'lang':
            case 'translated':
            case 'timestamp':
            case 'type':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'translated' => __('Translated string', 'autoglot'),
            'original' => __('Original string', 'autoglot'),
            'lang' => __('Language', 'autoglot'),
            'timestamp' => __('Date', 'autoglot'),
            'type' => __('Type', 'autoglot'),
        );
        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'original' => array('original', false),
            'lang' => array('lang', false),
            'translated' => array('translated', false),
            'timestamp' => array('timestamp', false)
        );
        return $sortable_columns;
    }

    function column_cb($item) {
        return sprintf(
                '<input type="checkbox" name="keys[]" value="%s" />', $this->item_key($item)
        );
    }

    function column_lang($item) {
        $actions = array(
            // 'edit' => sprintf('<a href="?page=%s&action=%s&book=%s">Edit</a>', $_REQUEST['page'], 'edit', 1/*$item['ID']*/),
            'filter' => '<a href="' . admin_url(sprintf('admin.php?page=%s&fl=%s%s">', $_REQUEST['page'], $item['lang'], ($this->search?"&s=".$this->search:""))) . __('Filter', 'autoglot') . "</a>",
        );
        return sprintf('%1$s %2$s', autoglot_utils::get_language_name($item['lang']), $this->row_actions($actions));
    }

    function column_original($item) {
        $actions = array(
            // 'edit' => sprintf('<a href="?page=%s&action=%s&book=%s">Edit</a>', $_REQUEST['page'], 'edit', 1/*$item['ID']*/),
            'delete' => '<a href="' . add_query_arg( '_wpnonce', wp_create_nonce( 'delete' ), admin_url(sprintf('admin.php?page=%s&action=%s&key=%s', $_REQUEST['page'], 'delete', $this->item_key($item)))) . '">' . __('Delete', 'autoglot') . '</a>',
        );
        return sprintf('<span>%1$s</span> %2$s', autoglot_utils::format_HTML_translation(strip_tags($item['original'], autoglot_consts::INLINE_TAGS_EDITOR)), $this->row_actions($actions));
    }

    function column_translated($item) {
        $actions = array(
            // 'edit' => sprintf('<a href="?page=%s&action=%s&book=%s">Edit</a>', $_REQUEST['page'], 'edit', 1/*$item['ID']*/),
            'edit' => '<a href="' . add_query_arg( '_wpnonce', wp_create_nonce( 'edit' ), admin_url(sprintf('admin.php?page=%s&action=%s&key=%s', $_REQUEST['page'], 'edit', html_entity_decode($this->item_key($item))))) . '" class="toggle-editor" data-id="'.$this->item_key($item).'">' . __('Quick Edit', 'autoglot') . '</a>',
            //'edit' => '<a href="' . add_query_arg( '_wpnonce', wp_create_nonce( 'edit' ), admin_url(sprintf('admin.php?page=%s&action=%s&key=%s', $_REQUEST['page'], 'edit', $this->item_key($item)))) . '">' . __('Quick Edit', 'autoglot') . '</a>',
        );
        $translated = autoglot_utils::format_HTML_translation(wp_kses($item['translated'], $this->autoglot->allowed_html), in_array($item['lang'], autoglot_consts::ALLOW_PUNCTUATION_SPACING));
        return sprintf('<span%1$s id="span_%4$s">%2$s</span><textarea%1$s id="edit_%4$s" class="text-editor">%5$s</textarea> %3$s', (in_array($item['lang'], autoglot_consts::RTL_LANGUAGES)? ' dir="rtl" style="float:right"':""), strip_tags($translated, autoglot_consts::INLINE_TAGS_EDITOR), $this->row_actions($actions), html_entity_decode($this->item_key($item)), htmlspecialchars($translated, ENT_NOQUOTES, "UTF-8"));
    }
    
    function get_bulk_actions() {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby = 'title';
        $order = 'asc';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }


        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'asc')
        {
            return $result;
        }

        return -$result;
    }
}
?>