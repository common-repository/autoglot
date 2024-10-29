<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: Autoglot functions for 3rd party plugins (SEO, etc.)
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

class autoglot_3rdparty {

    /** @var autoglot_plugin father class */
    private $autoglot;

    function __construct(&$autoglot) {
        $this->autoglot = &$autoglot;
        
        if (! is_admin()) {
        
            if(!$this->autoglot->options->translation_adminonly) {
                
//                add_action( 'sab_user_description', array(&$this, 'save_html'));
            
                add_action( 'sm_addurl', array(&$this, 'autoglot_sm_sitemappages'));
        
                add_filter( 'wp_sitemaps_posts_pre_url_list', array(&$this, 'wpsitemap_xml_posts_urls'), 10, 3);
                add_filter( 'wp_sitemaps_taxonomies_pre_url_list', array(&$this, 'wpsitemap_xml_terms_urls'), 10, 3);
                add_filter( 'wp_sitemaps_users_pre_url_list', array(&$this, 'wpsitemap_xml_users_urls'), 10, 2);

                add_filter( 'aiosp_sitemap_data', array(&$this, 'aiosp_xml_pages'), 10, 4);
                add_filter( 'aioseo_sitemap_posts', array(&$this, 'aioseo_sitemap_posts'), 10, 2);
        
                add_filter( 'wpseo_sitemap_url', array(&$this, 'wpseo_xml_pages'), 10, 1);
                add_filter( 'wpseo_schema_piece_language', array(&$this, 'wpseo_schema_piece_language'), 10, 1);
        
                add_filter( 'the_seo_framework_sitemap_endpoint_list', array(&$this, 'the_seo_framework_xml_pages'), 10, 1);
    
                add_filter( 'seopress_sitemaps_xml_single', array($this, 'seopress_xml_pages'), 10, 1);
                add_filter( 'seopress_sitemaps_xml_single_term', array($this, 'seopress_xml_pages'), 10, 1);

                add_filter( 'epc_exempt_uri_contains', array(&$this, 'epc_exclude_urls'), 10, 1);
                 
                add_filter( 'rank_math/sitemap/url', array(&$this, 'rm_xml_url'), 10, 2 );
//                add_filter( 'rank_math/sitemap/enable_caching', '__return_false');

                add_filter( 'jetpack_print_sitemap', array(&$this, 'jetpack_print_sitemap'), 10, 1 );

//                add_filter('woocommerce_get_checkout_url', array(&$this, 'woo_fix_url'));
//                add_filter('woocommerce_get_cart_url', array(&$this, 'woo_fix_url'));
            }
        }            
        //add_filter( 'the_seo_framework_sitemap_extend', 'tsf_term_sitemap_adjust_list' );
        
    }


	/**
	 * Add translated URLs to JetPack XML sitemap.
     * Unfortunately, this filter does nothing in their code now :(
	 *
	 * @param DOMDocument $doc Data tree for sitemap.
	 */

    function jetpack_print_sitemap($doc) {
        return $doc;
    }

	/**
	 * Add translated URLs to RankMath XML sitemap.
	 *
	 * @param string $output The output for the sitemap url tag.
	 * @param array  $url    The sitemap url array on which the output is based.
	 */

    function rm_xml_url( $output, $url ) {

        $translation_enable_o = $this->autoglot->options->translation_enable;
        $this->autoglot->options->translation_enable = 0;
        
        $xml = new SimpleXMLElement($output);

        $urlset_langs = "";
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
                if (isset($xml->loc)) {
                    // Get the original URL from the <loc> tag
                    $thisurl = (string)$xml->loc;
                    $ourl = (string)$xml->loc;
        
                    if($this->autoglot->options->translate_urls) $thisurl = esc_url($this->autoglot->translate_url($thisurl, $lang));
                    $thisurl = autoglot_utils::add_language_to_url($thisurl, $this->autoglot->homeURL, $lang);
        
                    // Update the <loc> tag with the modified URL
                    $xml->loc = $thisurl;
                    $urlset_langs .= preg_replace( "/<\?xml.+?\?>/", "", $xml->asXML());
                    $xml->loc = $ourl;
                }
            }
        }

        $this->autoglot->options->translation_enable = $translation_enable_o;

        return $output.$urlset_langs;
    }
    /**
     * Adds URL to google sitemap generator
     */

    function autoglot_sm_sitemappages($GoogleSitemapGeneratorPage) {
        $translation_enable_o = $this->autoglot->options->translation_enable;
        $this->autoglot->options->translation_enable = 0;

        $GoogleSitemapGeneratorPage = clone $GoogleSitemapGeneratorPage;
        // we need the generator object (we know it must exist...)
        $generatorObject = &GoogleSitemapGenerator::get_instance();
        $GoogleSitemapGeneratorPage->set_priority(max($GoogleSitemapGeneratorPage->get_priority() - $this->autoglot->options->reduce_sitemap_priority, 0));

        $orig_url = $GoogleSitemapGeneratorPage->get_url();
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
                if($this->autoglot->options->translate_urls) $newloc = esc_url($this->autoglot->translate_url($orig_url, $lang));
                else $newloc = $orig_url;   
                $newloc = autoglot_utils::add_language_to_url($newloc, $this->autoglot->homeURL, $lang); 
                $GoogleSitemapGeneratorPage->set_url($newloc);
                $generatorObject->add_element($GoogleSitemapGeneratorPage);
            }
        }
        $this->autoglot->options->translation_enable = $translation_enable_o;
    }
    
    /**
     * Universal function to save HTML content of widgets, boxes, etc. 
     */
    
    function save_html ( $content){    
        return $this->autoglot->get_html($content);
    }

    /**
     * Process All in One SEO Pack social meta
     */
    
    function aiosp_opengraph_meta ( $value, $type, $field, $v, $extra_params ){    
        switch($field){
            case "title":
                $this->autoglot->get_titles($value);
                break;
            case "tag":
            case "description":
                $this->autoglot->get_text($value);
                break;
            default:
                break;
        }
        return $value;
    }


    /**
     * Add URLs to core WP posts XML Sitemap
     * Have to copy all functionality from core XML Sitemap generation until they provide hooks to filter XML output or list of URLs
     */
    
    function wpsitemap_xml_posts_urls ( $z, $post_type, $page_num){

		$args = apply_filters(
			'wp_sitemaps_posts_query_args',
			array(
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'post_type'              => $post_type,
				'posts_per_page'         => wp_sitemaps_get_max_urls("post"),
				'post_status'            => array( 'publish' ),
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			),
			$post_type
		);

		$args['paged'] = $page_num;
		$query = new WP_Query( $args );

		$url_list = array();

        foreach ($this->autoglot->options->active_languages as $lang) {
    		if ( 'page' === $post_type && 1 === $page_num && 'posts' === get_option( 'show_on_front' ) ) {
    			$sitemap_entry = array(
    				'loc' => ($lang != $this->autoglot->options->default_language) ? autoglot_utils::add_language_to_url(home_url( '/' ), $this->autoglot->homeURL, $lang) : home_url( '/' ),
    			);
    
    			$sitemap_entry = apply_filters( 'wp_sitemaps_posts_show_on_front_entry', $sitemap_entry );
    			$url_list[]    = $sitemap_entry;
    		}
    		foreach ( $query->posts as $post ) {
    			$sitemap_entry = array(
    				'loc' => ($lang != $this->autoglot->options->default_language) ? autoglot_utils::add_language_to_url( ($this->autoglot->options->translate_urls ? esc_url($this->autoglot->translate_url(get_permalink( $post ), $lang)) : get_permalink( $post )), $this->autoglot->homeURL, $lang) : get_permalink( $post ),
    			);
    
    			$sitemap_entry = apply_filters( 'wp_sitemaps_posts_entry', $sitemap_entry, $post, $post_type );
    			$url_list[]    = $sitemap_entry;
    		}        
        }
        return $url_list;
    }
    
    function wpsitemap_xml_terms_urls ( $z, $taxonomy, $page_num){

		$offset = ( $page_num - 1 ) * wp_sitemaps_get_max_urls( "term" );
		$args = apply_filters(
			'wp_sitemaps_taxonomies_query_args',
			array(
				'fields'                 => 'ids',
				'taxonomy'               => $taxonomy,
				'orderby'                => 'term_order',
				'number'                 => wp_sitemaps_get_max_urls("term"),
				'hide_empty'             => true,
				'hierarchical'           => false,
				'update_term_meta_cache' => false,
			),
			$taxonomy
		);

		$args['offset'] = $offset;
		$taxonomy_terms = new WP_Term_Query( $args );

		$url_list = array();

		if ( ! empty( $taxonomy_terms->terms ) ) {
            foreach ($this->autoglot->options->active_languages as $lang) {
                foreach ( $taxonomy_terms->terms as $term ) {
    				$sitemap_entry = array(
    					'loc' => ($lang != $this->autoglot->options->default_language) ? autoglot_utils::add_language_to_url( ($this->autoglot->options->translate_urls ? esc_url($this->autoglot->translate_url(get_term_link( $term ), $lang)) : get_term_link( $term )), $this->autoglot->homeURL, $lang) : get_term_link( $term ),
    				);
    
    				$sitemap_entry = apply_filters( 'wp_sitemaps_taxonomies_entry', $sitemap_entry, $term, $taxonomy );
    				$url_list[]    = $sitemap_entry;
    			}
            }
        }
        return $url_list;
    }
    
    function wpsitemap_xml_users_urls ( $z, $page_num){

   		$public_post_types = get_post_types(
			array(
				'public' => true,
			)
		);

        $args = apply_filters(
			'wp_sitemaps_users_query_args',
			array(
				'has_published_posts' => array_keys( $public_post_types ),
				'number'              => wp_sitemaps_get_max_urls( "user" ),
			)
		);
		$args['paged'] = $page_num;

		$query    = new WP_User_Query( $args );
		$users    = $query->get_results();

		$url_list = array();

        foreach ($this->autoglot->options->active_languages as $lang) {
            foreach ( $users as $user ) {
    			$sitemap_entry = array(
    				'loc' => ($lang != $this->autoglot->options->default_language) ? autoglot_utils::add_language_to_url( ($this->autoglot->options->translate_urls ? esc_url($this->autoglot->translate_url(get_author_posts_url( $user->ID )), $lang) : get_author_posts_url( $user->ID )), $this->autoglot->homeURL, $lang) : get_author_posts_url( $user->ID ),
    			);

    			$sitemap_entry = apply_filters( 'wp_sitemaps_users_entry', $sitemap_entry, $user );
    			$url_list[]    = $sitemap_entry;
    		}
            
        }
        return $url_list;
    }
    
    
    /**
     * Add URLs to All in One SEO Pack XML Sitemap
     */
    
    //(old version)
    function aiosp_xml_pages ( $sitemap_data,  $sitemap_type,  $page,  $this_options ){
        if($sitemap_type == "root" && $this_options["aiosp_sitemap_indexes"] == "on") return $sitemap_data; 

        $sitemap_data_o = $sitemap_data;
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
                foreach($sitemap_data_o as $pagemap){
                    $pagemap["loc"] = autoglot_utils::add_language_to_url($pagemap["loc"], $this->autoglot->homeURL, $lang);
                    $pagemap["priority"] = max($pagemap["priority"] - $this->autoglot->options->reduce_sitemap_priority, 0);
                    if(strlen(trim($pagemap["rss"]["title"])))$pagemap["rss"]["title"] .= " ".$lang;
                    if(strlen(trim($pagemap["rss"]["description"])))$pagemap["rss"]["description"] .= " ".$lang;
                    $sitemap_data[] = $pagemap;
                }
            }
        }

        return $sitemap_data;
    }
    
    //(new version)
    function aioseo_sitemap_posts ( $entries, $postType ){

        $entries_o = $entries;
        $translation_enable_o = $this->autoglot->options->translation_enable;
        $this->autoglot->options->translation_enable = 0;
        
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
                foreach($entries_o as $pagemap){
                    if($this->autoglot->options->translate_urls) $pagemap["loc"] = esc_url($this->autoglot->translate_url($pagemap["loc"], $lang));
                    $pagemap["loc"] = autoglot_utils::add_language_to_url($pagemap["loc"], $this->autoglot->homeURL, $lang);
                    $pagemap["priority"] = max($pagemap["priority"] - $this->autoglot->options->reduce_sitemap_priority, 0);
                    if(strlen(trim($pagemap["rss"]["title"])))$pagemap["rss"]["title"] .= " ".$lang;
                    if(strlen(trim($pagemap["rss"]["description"])))$pagemap["rss"]["description"] .= " ".$lang;
                    $entries[] = $pagemap;
                }
            }
        }

        $this->autoglot->options->translation_enable = $translation_enable_o;
        return $entries;
    }
    
    
    /**
     * Replace language for WPSEO Yoast
     */
    
    function wpseo_schema_piece_language( $lang ){
        if(strlen($this->autoglot->langURL)) return str_replace("_","-",autoglot_utils::get_language_locale($this->autoglot->langURL));
        else return $lang;
    }
    
    /**
     * Add URLs to WPSEO Yoast XML Sitemap
     */
    
    function wpseo_xml_pages( $output ){
        $translation_enable_o = $this->autoglot->options->translation_enable;
        $this->autoglot->options->translation_enable = 0;
        
        $xml = new SimpleXMLElement($output);

        $urlset_langs = "";
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
                if (isset($xml->loc)) {
                    // Get the original URL from the <loc> tag
                    $thisurl = (string)$xml->loc;
                    $ourl = (string)$xml->loc;
        
                    if($this->autoglot->options->translate_urls) $thisurl = esc_url($this->autoglot->translate_url($thisurl, $lang));
                    $thisurl = autoglot_utils::add_language_to_url($thisurl, $this->autoglot->homeURL, $lang);
        
                    // Update the <loc> tag with the modified URL
                    $xml->loc = $thisurl;
                    $urlset_langs .= preg_replace( "/<\?xml.+?\?>/", "", $xml->asXML());
                    $xml->loc = $ourl;
                }
            }
        }

        $this->autoglot->options->translation_enable = $translation_enable_o;

        return $output.$urlset_langs;
    }
    
    /**
     * Add URLs to The SEO Framework XML Sitemap
     */
    
    function the_seo_framework_xml_pages( $list ){
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
        		$list[ $lang ] = [
        			'endpoint' => "sitemap-$lang.xml",
        			'regex'    => "/^sitemap\-{$lang}\.xml/", // Don't add a $ at the end, for translation-plugin support.
        			'callback' => array(&$this, 'tsf_lang_sitemap_output'),
        			'robots'   => true,
        		];
            }
        }
        return $list;
    }
    
    function tsf_lang_sitemap_output( $lang ) {
        $translation_enable_o = $this->autoglot->options->translation_enable;
        $this->autoglot->options->translation_enable = 0;

        if ( ! headers_sent() ) {
        	\status_header( 200 );
        	header( 'Content-type: text/xml; charset=utf-8', true );
        }

        $sitemap_bridge = \The_SEO_Framework\Bridges\Sitemap::get_instance();
        $sitemap_bridge->output_sitemap_header();
        $sitemap_bridge->output_sitemap_urlset_open_tag();
        
        $sitemap_base = new \The_SEO_Framework\Sitemap\Optimized\Base;
        $output = "<urlset>".trim(preg_replace('/<!--(.|\s)*?-->/', '', $sitemap_base->generate_sitemap()))."</urlset>";

        $xml = new SimpleXMLElement($output);

        $urlset_langs = "";
        if ($lang != $this->autoglot->options->default_language) {
            foreach ($xml->url as $url) {
                if (isset($url->loc)) {
                    // Get the original URL from the <loc> tag
                    $thisurl = (string)$url->loc;
                    $ourl = (string)$url->loc;
        
                    if($this->autoglot->options->translate_urls) $thisurl = esc_url($this->autoglot->translate_url($thisurl, $lang));
                    $thisurl = autoglot_utils::add_language_to_url($thisurl, $this->autoglot->homeURL, $lang);
        
                    // Update the <loc> tag with the modified URL
                    $url->loc = $thisurl;
                    $urlset_langs .= preg_replace( "/<\?xml.+?\?>/", "", $url->asXML());
                    $url->loc = $ourl;
                }
            }
        }

        echo $urlset_langs."\n";

        $sitemap_bridge->output_sitemap_urlset_close_tag();

        $this->autoglot->options->translation_enable = $translation_enable_o;

        exit();
    }

    /**
     * Process SEOPress social meta (titles/descriptions)
     */
    
    function seopress_social_meta ( $metahtml ){  
        $preg = '/<meta[\s\'\"0-9a-zA-Z_=\-\:]+content=\"(.*?)\" \/>/i';
        preg_match_all($preg, $metahtml, $match);
        if(false !== strpos($metahtml, "title")){
            $this->autoglot->get_titles($match[1]);            
        }elseif(false !== strpos($metahtml, "description")){
            $this->autoglot->get_text($match[1]);            
        }
        return $metahtml;
    }
       
    /**
     * Add URLs to SEOPress XML Sitemap
     */
    
    function seopress_xml_pages( $output ){
        $translation_enable_o = $this->autoglot->options->translation_enable;
        $this->autoglot->options->translation_enable = 0;

        $xml = new SimpleXMLElement($output);

        $urlset_langs = "";
        foreach ($this->autoglot->options->active_languages as $lang) {
            if ($lang != $this->autoglot->options->default_language) {
                foreach ($xml->url as $url) {
                    if (isset($url->loc)) {
                        // Get the original URL from the <loc> tag
                        $thisurl = (string)$url->loc;
                        $ourl = (string)$url->loc;
            
                        if($this->autoglot->options->translate_urls) $thisurl = esc_url($this->autoglot->translate_url($thisurl, $lang));
                        $thisurl = autoglot_utils::add_language_to_url($thisurl, $this->autoglot->homeURL, $lang);
            
                        // Update the <loc> tag with the modified URL
                        $url->loc = $thisurl;
                        $urlset_langs .= preg_replace( "/<\?xml.+?\?>/", "", $url->asXML());
                        $url->loc = $ourl;
                    }
                }
            }
        }

        $this->autoglot->options->translation_enable = $translation_enable_o;

        return str_replace("</urlset>", $urlset_langs."</urlset>", $output);
    }

    
    /**
     * Exclude language URLs from EPC caching
     */
    
    function epc_exclude_urls( $urls ){
        $urls_all = $urls;
		
		if(strlen($this->autoglot->langURL))$urls_all[]="/".$this->autoglot->langURL."/";
        
        return $urls_all;
    }
    
    /**
     * Try to flush the cache from known caching plugins
     */
    
    function flush_caches(){
        
        // W3 Total Cache : w3tc
        if (function_exists('w3tc_flush_all')) { 
            @w3tc_flush_all(); 
        }
        
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) { 
            @wpfc_clear_all_cache(); 
        }
        
        // WP Super Cache : wp-super-cache
        if ( function_exists( 'wp_cache_clean_cache' ) ) {
            global $file_prefix;
            @wp_cache_clean_cache( $file_prefix, true );
        }
        
        // WP Rocket
        if(function_exists('rocket_clean_domain' ) ) {
            @rocket_clean_domain(); 
        }
        
        // SpeedyCache
        if(class_exists( '\SpeedyCache\Delete' ) && method_exists("\SpeedyCache\Delete", "run")){
            \SpeedyCache\Delete::run(array());
        }
        
        // WP_Optimize
        if(class_exists("WP_Optimize")){
            @WP_Optimize()->get_page_cache()->purge();
        }

        // LiteSpeed
        if (class_exists('\LiteSpeed\Purge')) {
          \LiteSpeed\Purge::purge_all();
        }
        
        // WPEngine
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' )  ) {
			WpeCommon::purge_memcached();
			WpeCommon::clear_maxcdn_cache();
			WpeCommon::purge_varnish_cache();
		}

		// SG Optimizer by Siteground
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

        // Cache Enabler
		if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_total_cache' ) ) {
			Cache_Enabler::clear_total_cache();
		}

		// Pagely
		if ( class_exists( 'PagelyCachePurge' ) && method_exists( 'PagelyCachePurge', 'purgeAll' ) ) {
			PagelyCachePurge::purgeAll();
		}

		// Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}

		// Comet cache
		if ( class_exists( 'comet_cache' ) && method_exists( 'comet_cache', 'clear' ) ) {
			comet_cache::clear();
		}

		// Hummingbird Cache
		if ( class_exists( '\Hummingbird\WP_Hummingbird' ) && method_exists( '\Hummingbird\WP_Hummingbird', 'flush_cache' ) ) {
			\Hummingbird\WP_Hummingbird::flush_cache();
		}

        return;
    }

/*    function woo_fix_url($url) {
        $lang = autoglot_utils::get_language_from_url($_SERVER['REQUEST_URI'], home_url());
        $newurl = autoglot_utils::add_language_to_url($url, home_url(), $lang); 
        return $newurl; 
    }*/

}

