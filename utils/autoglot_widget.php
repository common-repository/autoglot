<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: Autoglot widget class and functions
 */
 
if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

class autoglot_widget extends WP_Widget {
    
    /** Widget active languages */
    public $widget_active_languages;

    /** @var autoglot_plugin father class */
    private $autoglot;

	// Main constructor
	public function __construct() {
 
        $this->autoglot = &$GLOBALS['new_autoglot'];
        $this->widget_active_languages = $this->autoglot->options->active_languages;

		parent::__construct(
			'autoglot_custom_widget',
			__( 'Autoglot WP Translation Widget', 'autoglot'),
			array(
				'customize_selective_refresh' => true,
			)
		);

	}

	/**
	 * The widget form (for the backend )
	 */
	public function form( $instance ) {
		// Set widget defaults
		$defaults = array(
			'title'    => '',
            'selectstyle'=> '',
/*			'text'     => '',
			'textarea' => '',
			'checkbox' => '',
			'select'   => '',*/
		);
		
		// Parse current settings with defaults
		extract( wp_parse_args( ( array ) $instance, $defaults ) ); ?>

		<?php // Widget Title ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Widget Title', 'autoglot'); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<?php // Selector Type ?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id( 'selectstyle' )); ?>"><?php _e( 'Selector Style', 'autoglot'); ?></label>
			<select name="<?php echo esc_attr($this->get_field_name( 'selectstyle' )); ?>" id="<?php echo esc_attr($this->get_field_id( 'selectstyle' )); ?>" class="widefat">
			<?php
			// Your options array
			$options = array(
				'languagelist' => __( 'List of Languages', 'autoglot'),
				'languageflagslist' => __( 'List of Languages and Flags', 'autoglot'),
				'flagslist' => __( 'Box with Flags', 'autoglot'),
				'smallflagslist' => __( 'Box with Small Flags', 'autoglot'),
				//'flagsselect' => __( 'Flags Selector', 'autoglot'),
			);
			// Loop through options and add each one to the select dropdown
			foreach ( $options as $key => $name ) {
				echo '<option value="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" '. selected( $selectstyle, $key, false ) . '>'. $name . '</option>';
			} ?>
			</select>
		</p>

		<?php /* // Text Field ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>"><?php _e( 'Text:', 'autoglot'); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>" type="text" value="<?php echo esc_attr( $text ); ?>" />
		</p>

		<?php // Textarea Field ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'textarea' ) ); ?>"><?php _e( 'Textarea:', 'autoglot'); ?></label>
			<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'textarea' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'textarea' ) ); ?>"><?php echo wp_kses_post( $textarea ); ?></textarea>
		</p>

		<?php // Checkbox ?>
		<p>
			<input id="<?php echo esc_attr( $this->get_field_id( 'checkbox' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'checkbox' ) ); ?>" type="checkbox" value="1" <?php checked( '1', $checkbox ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'checkbox' ) ); ?>"><?php _e( 'Checkbox', 'autoglot'); ?></label>
		</p>

		<?php // Dropdown ?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id( 'select' )); ?>"><?php _e( 'Select', 'autoglot'); ?></label>
			<select name="<?php echo esc_attr($this->get_field_name( 'select' )); ?>" id="<?php echo esc_attr($this->get_field_id( 'select' )); ?>" class="widefat">
			<?php
			// Your options array
			$options = array(
				''        => __( 'Select', 'autoglot'),
				'option_1' => __( 'Option 1', 'autoglot'),
				'option_2' => __( 'Option 2', 'autoglot'),
				'option_3' => __( 'Option 3', 'autoglot'),
			);
			// Loop through options and add each one to the select dropdown
			foreach ( $options as $key => $name ) {
				echo '<option value="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" '. selected( $select, $key, false ) . '>'. $name . '</option>';
			} ?>
			</select>
		</p>

	<?php */ }

	/**
	 * Update widget settings
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title']    = isset( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['selectstyle']   = isset( $new_instance['selectstyle'] ) ? wp_strip_all_tags( $new_instance['selectstyle'] ) : '';
/*		$instance['text']     = isset( $new_instance['text'] ) ? wp_strip_all_tags( $new_instance['text'] ) : '';
		$instance['textarea'] = isset( $new_instance['textarea'] ) ? wp_kses_post( $new_instance['textarea'] ) : '';
		$instance['checkbox'] = isset( $new_instance['checkbox'] ) ? 1 : false;
		$instance['select']   = isset( $new_instance['select'] ) ? wp_strip_all_tags( $new_instance['select'] ) : '';*/
		return $instance;
	}

	/**
	 * Display the widget
	 */
	public function widget( $args, $instance ) {
		extract( $args );
        global $wp;
        
		// Check the widget options
		$title    = isset( $instance['title'] ) && strlen( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : AUTOGLOT_WIDGET_TITLE;
		$selectstyle = isset( $instance['selectstyle'] ) ? $instance['selectstyle'] : '';
/*		$text     = isset( $instance['text'] ) ? $instance['text'] : '';
		$textarea = isset( $instance['textarea'] ) ?$instance['textarea'] : '';
		$select   = isset( $instance['select'] ) ? $instance['select'] : '';
		$checkbox = ! empty( $instance['checkbox'] ) ? $instance['checkbox'] : false;*/
		// WordPress core before_widget hook (always include )
		echo wp_kses_post($before_widget."<div class='".AUTOGLOT_NOTRANSLATE_LANGUAGESWITCHER."'>");
		// Display the widget
		//echo '<div class="widget-text wp_widget_plugin_box">';
			// Display widget title if defined
			if ( $title ) {
				echo $before_title . esc_html($title) . $after_title;
			}
            echo "<div style='clear:both'></div>";
            if(count($this->widget_active_languages)){
                $addsmallcss = "";
                $addflag = 0;
                $flagimage = "";

                $current_url = is_404()?$this->autoglot->homeURL:$this->autoglot->get_original_url(home_url( add_query_arg( array(), $wp->request ) ),$this->autoglot->homeURL,$this->autoglot->langURL, 0);
				$current_link = $current_url;//str_replace($this->autoglot->homeURL, "", $current_url);
                $widget_translate_urls = $this->autoglot->options->translate_urls;
                if(is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) $widget_translate_urls = 0;//in admin widget area

                switch($selectstyle){
                    case "smallflagslist":
                        $addsmallcss = "_small";
                    case "flagslist":
                        echo '<div class="flaglist">';
                        /*if(strlen($this->autoglot->langURL)){
                            foreach($this->widget_active_languages as $lang){
                                $lang_flag = isset($this->autoglot->options->language_flags[$lang])?$this->autoglot->options->language_flags[$lang]:autoglot_utils::get_language_flag($lang);
                                $lang_url = ($lang==$this->autoglot->options->default_language?"":"/".$lang);
                                echo '<a href="'.esc_url(AUTOGLOT_FAKE_URL.$lang_url.$current_link).'" id="lang_'.esc_attr($lang).'"><span class="cssflag'.esc_attr($addsmallcss).' cssflag-'.$lang_flag.esc_attr($addsmallcss).'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names)).'"></span></a>';
                            } 
                        }
                        else { // we are in default language
                        }*/
                        foreach($this->widget_active_languages as $lang){
                            $lang_flag = isset($this->autoglot->options->language_flags[$lang])?$this->autoglot->options->language_flags[$lang]:autoglot_utils::get_language_flag($lang);
                            $lang_url = '';
                            if($lang == $this->autoglot->options->default_language) {
                                $lang_url = $current_link;
                            } elseif($widget_translate_urls) {
                                $lang_url = autoglot_utils::add_language_to_url($this->autoglot->translate_url($current_link, $lang), $this->autoglot->homeURL, $lang);
                            } else {
                                $lang_url = autoglot_utils::add_language_to_url($current_link, $this->autoglot->homeURL, $lang);
                            }
                            echo '<a href="'.trailingslashit(esc_url($lang_url)).'" id="lang_'.esc_attr($lang).'" data-type="languageswitcher"><span class="cssflag'.esc_attr($addsmallcss).' cssflag-'.$lang_flag.esc_attr($addsmallcss).'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names)).'"></span></a>';
                        } 

                        echo '</div>';
                    break;
                    case "flagsselect":
                    break;
                    case "languageflagslist":
                        $addflag = 1;
                    case "languagelist":
                    default:
                        echo '<ul class="menu languagelist">';
                        foreach($this->widget_active_languages as $lang){
                            $lang_flag = isset($this->autoglot->options->language_flags[$lang])?$this->autoglot->options->language_flags[$lang]:autoglot_utils::get_language_flag($lang);
                            $lang_url = '';
                            if($addflag) $flagimage = '<span class="cssflag cssflag-'.$lang_flag.'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names)).'"></span>';
                            if($lang == $this->autoglot->langURL || ($lang == $this->autoglot->options->default_language && !$this->autoglot->langURL)){ // current language
                                echo '<li>'.$flagimage.'<strong>'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</strong></li>";
                            }
                            else {
                                
                                if($lang == $this->autoglot->options->default_language){ // default language (don't need language ID)
                                    $lang_url = $current_link;
                                }
                                elseif($widget_translate_urls) {
                                    $lang_url = autoglot_utils::add_language_to_url($this->autoglot->translate_url($current_link, $lang), $this->autoglot->homeURL, $lang);
                                }
                                else {
                                    $lang_url = autoglot_utils::add_language_to_url($current_link, $this->autoglot->homeURL, $lang);
                                }
                                echo '<li>'.$flagimage.'<a href="'.trailingslashit(esc_url($lang_url)).'" id="lang_'.esc_attr($lang).'" data-type="languageswitcher">'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</a></li>";
                            }
                        } 
                        /*if(strlen($this->autoglot->langURL)){
                            foreach($this->widget_active_languages as $lang){
                                $lang_flag = isset($this->autoglot->options->language_flags[$lang])?$this->autoglot->options->language_flags[$lang]:autoglot_utils::get_language_flag($lang);
                                if($addflag) $flagimage = '<span class="cssflag cssflag-'.$lang_flag.'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names)).'"></span>';
                                if($lang == $this->autoglot->langURL){ // current language
                                    echo '<li>'.$flagimage.'<strong>'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</strong></li>";
                                }
                                elseif($lang == $this->autoglot->options->default_language){ // default language (nop need language ID)
                                    echo '<li>'.$flagimage.'<a href="'.esc_url(AUTOGLOT_FAKE_URL.$current_link).'" id="lang_'.esc_attr($lang).'">'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</a></li>";
                                }
                                else { // other languages
                                    echo '<li>'.$flagimage.'<a href="'.esc_url(AUTOGLOT_FAKE_URL.'/'.$lang.$current_link).'" id="lang_'.esc_attr($lang).'">'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</a></li>";
                                }
                            } 
                        }
                        else { // we are in default language
                            foreach($this->widget_active_languages as $lang){
                                $lang_flag = isset($this->autoglot->options->language_flags[$lang])?$this->autoglot->options->language_flags[$lang]:autoglot_utils::get_language_flag($lang);
                                if($addflag) $flagimage = '<span class="cssflag cssflag-'.$lang_flag.'" title="'.esc_attr(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names)).'"></span>';
                                if($lang == $this->autoglot->options->default_language) {
                                    echo '<li>'.$flagimage.'<strong>'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</strong></li>";
                                }
                                else {
                                    echo '<li>'.$flagimage.'<a href="'.esc_url($this->autoglot->homeURL.'/'.$lang.$current_link).'" id="lang_'.esc_attr($lang).'">'.esc_html(autoglot_utils::get_full_name($lang,$this->autoglot->options->language_names))."</a></li>";
                                }
                            } 
                        }*/
                        echo '</ul>';
                }
            }
            
			// Display text field
            /*
			if ( $text ) {
				echo '<p>' . $text . '</p>';
			}
			// Display textarea field
			if ( $textarea ) {
				echo '<p>' . $textarea . '</p>';
			}
			// Display select field
			if ( $select ) {
				echo '<p>' . $select . '</p>';
			}
			// Display something if checkbox is true
			if ( $checkbox ) {
				echo '<p>Something awesome</p>';
			}*/
        echo "<div style='clear:both'></div>";
        if($this->autoglot->options->widget_signature)echo "<div style='font-size:smaller' class='".AUTOGLOT_NOTRANSLATE_CLASS."'>".wp_kses_post(AUTOGLOT_WIDGET_SIGNATURE)."</div>";
		//echo '</div>';
		// WordPress core after_widget hook (always include )
		echo "</div>".$after_widget;
    }
    
}

  
