<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2024, Autoglot
 * Description: Autoglot DOM functions
 */

if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

/**
 * DOM related functions
 * 
 */

class autoglot_dom {

    /** @var autoglot_plugin father class */
    private $autoglot;

    /** @var DOM content */
    private $dom_content;

    /** @var DOM translated content */
    private $dom_translated;
    
    /** @var saved attributes */
    private $savenodes;
    
    /** @var saved content */
    private $savecontent;
    
    /** @var counter for saved attributes */
    private $blockcounter;
    
    private $array_translated;

    function __construct(&$autoglot) {
        $this->autoglot = &$autoglot;
        
        $this->dom_content = new DOMDocument('1.0', 'UTF-8');
        $this->dom_translated = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $this->savenodes = array();
        $this->savecontent = array();
        
        $this->blockcounter = 0;
    }
    
    function appendHTML(DOMNode $parent, $source) {
        $tmpDoc = new DOMDocument();
        $tmpDoc->loadHTML('<?xml encoding="UTF-8">' . $source);
        foreach ($tmpDoc->getElementsByTagName('ag_tag')->item(0)->childNodes as $node) {
            $node = $parent->ownerDocument->importNode($node, true);
            $parent->appendChild($node);
        }
}
    
    function loadHTML($source) {
        $this->dom_content->loadHTML($source);
        $this->dom_content->normalizeDocument();

    }
    
    function trimNode(DOMNode $node) {
        while($node->lastChild && $node->lastChild->nodeType == XML_TEXT_NODE && !strlen(trim($node->lastChild->nodeValue))){
            $node->removeChild($node->lastChild);
        }
        while($node->firstChild && $node->firstChild->nodeType == XML_TEXT_NODE && !strlen(trim($node->firstChild->nodeValue))){
            $node->removeChild($node->firstChild);
        }
    }
    
    function saveNodeAttributes($nodes, $blockcounter){
        // Save attributes and create template
        $nodecounter = 0;
        
        foreach ($nodes as $node) {
            if($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $this->savenodes[$blockcounter][$nodecounter][$attr->nodeName] = $attr;
                }
                foreach ($this->savenodes[$blockcounter][$nodecounter] as $attr) {
                    $node->removeAttribute($attr->nodeName);
                }
            }
            else {
                $this->savenodes[$blockcounter][$nodecounter] = false;
            }
/*            if(strcasecmp($node->tagName, "style") === 0 || strcasecmp($node->tagName, "script") === 0){
                $this->savecontent[$blockcounter][$nodecounter]["content"] = $node->nodeValue;
                //$node->parentNode->removeChild($node/*->firstChild);
                $node->nodeValue = "";
            }    */                                
            $node->setAttribute(AUTOGLOT_REPLACE_ATTRIBUTES, $nodecounter);
            $nodecounter++;
        }
    }


    /**
     * Create array of strings from HTML
     */

    function HTML2Array($sourceHTML){
        $metaarray = array();
        $array = array();
                
//file_put_contents(__DIR__."/debug".time().".txt", $sourceHTML);

        $this->loadHTML($sourceHTML);

//file_put_contents(__DIR__."/debug.txt", $this->dom_content->saveHTML());

        //mark blocks for exlusion
        $xpath = new DOMXPath($this->dom_content);
        $result=$xpath->query("//*[contains(concat(\" \", normalize-space(@class), \" \"), \" ".AUTOGLOT_NOTRANSLATE_LANGUAGESWITCHER." \") or
                                   contains(concat(\" \", normalize-space(@class), \" \"), \" ".AUTOGLOT_NOTRANSLATE_CLASS." \") or
                                   contains(concat(\" \", normalize-space(@class), \" \"), \" widget_meta \") or
                                   contains(concat(\" \", normalize-space(@id), \" \"), \" wpadminbar \")]");
        foreach($result as $node)
        {
            $node->setAttribute(AUTOGLOT_EXCLUDE_TRANSLATION, AUTOGLOT_NOTRANSLATE_CLASS);
        }
        
        //If custom title enabled & in proper page
        if($this->autoglot->options->custom_titles && (is_category() || is_tag() || is_tax() || is_author() || (is_archive() && !empty(get_query_var('year'))) || is_search())) {
            
            if ( is_category() || is_tag() || is_tax()) {
                $term = single_term_title( '', false );
                $metaarray[autoglot_utils::gettexthash($term)] = $term;
            }            
            elseif ( is_author() ) {
                $author = get_queried_object()->display_name;
                $metaarray[autoglot_utils::gettexthash($author)] = $author;
            }
            elseif ( is_archive() && !empty(get_query_var('year')) ) {
                //date-based title, nothing to translate
            }
            elseif ( is_search() ) {
                //search-based title, nothing to translate
            }
            
            $pagetitle = $xpath->query("//*[contains(concat(\" \", normalize-space(@class), \" \"), \" page-title \")]");
            if ($pagetitle->length) $pagetitle->item(0)->setAttribute(AUTOGLOT_EXCLUDE_TRANSLATION, AUTOGLOT_NOTRANSLATE_CLASS);
            
        } else {
            
            $titledoms = $xpath->query("//title");
            if ($titledoms->length) {
                $mvalue = $titledoms->item(0)->textContent;
                $metaarray[autoglot_utils::gettexthash($mvalue)] = $mvalue;
            } 
            $titledoms = $xpath->query("//meta[@property=\"og:title\" or @name=\"twitter:title\"]/@content");
            if ($titledoms->length) foreach($titledoms as $node)
            {
                $mvalue = $node->value;
                $metaarray[autoglot_utils::gettexthash($mvalue)] = $mvalue;
            }
        }
        
        if($this->autoglot->options->translate_urls) {//need to grab all URLs
            $permalinks = $xpath->query("//a");
            if ($permalinks->length) foreach($permalinks as $node)
            {
                $href = $node->getAttribute('href');
                $type = $node->getAttribute('data-type');
                $href = autoglot_utils::make_link_absolute($href, $this->autoglot->homeURL);
                // Check if the link contains the old domain and does not contain admin links 
                if (strpos($href, $this->autoglot->homeURL) !== FALSE && $type != "languageswitcher" && autoglot_utils::check_not_admin_link($href) ) {
                    $trylang = autoglot_utils::get_language_from_url($href, $this->autoglot->homeURL);
                    if($trylang && strlen($trylang)>1 && in_array($trylang, $this->autoglot->options->active_languages)) {
                        //language already here, nothing to do?
                    } else {
                    // Save URL for translation
                        $this->autoglot->page_links[] = $href;
                    }
                }
            }
            $permalinks = $xpath->query("//form");
            if ($permalinks->length) foreach($permalinks as $node)
            {
                $href = $node->getAttribute('action');
                $id = $node->getAttribute('id');
                $href = autoglot_utils::make_link_absolute($href, $this->autoglot->homeURL);
                // Check if the link contains the old domain and does not contain admin links 
                if (strpos($href, $this->autoglot->homeURL) !== FALSE && $id != "commentform" && autoglot_utils::check_not_admin_link($href) ) {
                    $trylang = autoglot_utils::get_language_from_url($href, $this->autoglot->homeURL);
                    if($trylang && strlen($trylang)>1 && in_array($trylang, $this->autoglot->options->active_languages)) {
                        //language already here, nothing to do?
                    } else {
                        // Save URL for translation
                        $this->autoglot->page_links[] = $href;
                    }
                }
            }
            $canonicals = $xpath->query("//link[@rel=\"canonical\"]/@href | //meta[@property=\"og:url\"]/@content");
            if ($canonicals->length) foreach($canonicals as $node)
            {
                $href = $node->value;
                // Check if the link contains the old domain and does not contain admin links 
                if (strpos($href, $this->autoglot->homeURL) !== FALSE && autoglot_utils::check_not_admin_link($href) ) {
                    $this->autoglot->page_links[] = $href;
                }
            }
        }
        
        $metadoms = $xpath->query("//meta[@property=\"og:description\" or @name=\"twitter:description\" or @name=\"description\" or @property=\"og:site_name\"]/@content");
        if ($metadoms->length) foreach($metadoms as $node)
        {
            $mvalue = $node->value;
            $metaarray[autoglot_utils::gettexthash($mvalue)] = $mvalue;
        }
        
        $array = $this->DOM2Array($this->dom_content->getElementsByTagName('body')->item(0));
        
        $return = array_merge($metaarray, $array);

        return $return;
    }

    
    /**
     * Create array of strings from DOM
     */

    function DOM2Array($root) {
        $array = array();
    
        if(in_array($root->nodeName,autoglot_consts::SKIP_TAGS)) {
            return;
        }
        //handle classic node
        if($root->nodeType == XML_ELEMENT_NODE) {
            if($root->hasChildNodes()) {
                $root->normalize();
                
                $attrexclude = "";
                if(($attrexclude = $root->getAttribute(AUTOGLOT_EXCLUDE_TRANSLATION)) && (strpos($attrexclude, AUTOGLOT_NOTRANSLATE_CLASS) !== false)) return; 

                $this->trimNode($root);
                
                $whileChecking = true;

                while($root->hasChildNodes() && $whileChecking){
                    
                    if($root->childNodes->length == 1 && $root->childNodes->item(0)->nodeType == XML_ELEMENT_NODE && trim($root->childNodes->item(0)->nodeValue) == trim($root->nodeValue)) {
                        $root = $root->childNodes->item(0);
                        $this->trimNode($root);
                        $attrexclude = "";
                        if(($attrexclude = $root->getAttribute(AUTOGLOT_EXCLUDE_TRANSLATION)) && (strpos($attrexclude, AUTOGLOT_NOTRANSLATE_CLASS) !== false)) return;
                        if(in_array($root->nodeName,autoglot_consts::SKIP_TAGS)) return; 
                    
                    }elseif($root->childNodes->length == 1 && ($root->childNodes->item(0)->nodeType == XML_TEXT_NODE || $root->childNodes->item(0)->nodeType == XML_CDATA_SECTION_NODE)) {
                        return $this->DOM2Array($root->childNodes->item(0)); 
                    
                    }else {

                        $iFirstChild = 0;
                        while(!is_null($root->childNodes->item($iFirstChild)) && (
                            $root->childNodes->item($iFirstChild)->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->childNodes->item($iFirstChild))))) || 
                            $root->childNodes->item($iFirstChild)->nodeType == XML_TEXT_NODE && !strlen(trim($root->childNodes->item($iFirstChild)->nodeValue)) ||
                            in_array($root->childNodes->item($iFirstChild)->nodeName,autoglot_consts::SKIP_TAGS)
                        )){
                            $iFirstChild++;
                        }

                        if($iFirstChild == $root->childNodes->length) return;//only empty tags/text here

                        $iLastChild = $root->childNodes->length-1;
                        while(!is_null($root->childNodes->item($iLastChild)) && (
                            $root->childNodes->item($iLastChild)->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->childNodes->item($iLastChild))))) || 
                            $root->childNodes->item($iLastChild)->nodeType == XML_TEXT_NODE && !strlen(trim($root->childNodes->item($iLastChild)->nodeValue)) ||
                            in_array($root->childNodes->item($iLastChild)->nodeName,autoglot_consts::SKIP_TAGS)
                        )){
                            $iLastChild--;
                        }

                        if($iFirstChild == $iLastChild){//only one reasonable tag here, go in
                            if(($root->childNodes->item($iFirstChild)->nodeType == XML_TEXT_NODE || $root->childNodes->item($iFirstChild)->nodeType == XML_CDATA_SECTION_NODE))
                                return $this->DOM2Array($root->childNodes->item($iFirstChild)); 
                            $root = $root->childNodes->item($iFirstChild);
                            $this->trimNode($root);
                            $attrexclude = "";
                            if($root->nodeType == XML_ELEMENT_NODE && ($attrexclude = $root->getAttribute(AUTOGLOT_EXCLUDE_TRANSLATION)) && (strpos($attrexclude, AUTOGLOT_NOTRANSLATE_CLASS) !== false)) return; 
                        } else {
                            $whileChecking = false;
                        } 
                        
                    }
                    
                }

                $innerHTML = '';
                if(@is_object($root->childNodes))foreach ($root->childNodes as $child) {
                    $innerHTML .= $this->dom_content->saveHTML($child);
                }
                if (trim(strip_tags($innerHTML)) == '') {
                //only tags - no text. skip
                }
                elseif(strlen($innerHTML) && $innerHTML == strip_tags($innerHTML, autoglot_consts::INLINE_TAGS)) {//text with "allowed" inline tags - translate
//file_put_contents(__DIR__."/debug.txt", $innerHTML."\r\n", FILE_APPEND);

                    $firstChilds = array();
                    $lastChilds = array();
                    //first, let's check for empty tags in first/last - often used in links, headers, etc. No need them in translation
                    while($root->firstChild && (
                        $root->firstChild->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->firstChild)))) ||
                        in_array($root->firstChild->nodeName,autoglot_consts::SKIP_TAGS)
                    )){
                        array_unshift($firstChilds, $root->firstChild);
                        $root->removeChild($root->firstChild);
                        $this->trimNode($root);
                    }
                    while($root->lastChild && (
                        $root->lastChild->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->lastChild)))) ||
                        in_array($root->lastChild->nodeName,autoglot_consts::SKIP_TAGS)
                    )){
                        array_unshift($lastChilds, $root->lastChild);
                        $root->removeChild($root->lastChild);
                        $this->trimNode($root);
                    }

                    $xpath = new DOMXPath($this->dom_content);
                    $xpathnodes = $xpath->query('.//*', $root);
                    $this->saveNodeAttributes($xpathnodes, $this->blockcounter);
                    
                    $innerHTML = '';
                    foreach ($root->childNodes as $child) {
                        $innerHTML .= $this->dom_content->saveHTML($child);
                    }
                    $innerHTML = autoglot_utils::prepare_HTML_translation($innerHTML);

                    $array[autoglot_utils::gettexthash($innerHTML)] = $innerHTML;
                    
                    //restore empty tags
                    if(count($firstChilds)) foreach($firstChilds as $fC){
                        $root->insertBefore($fC, $root->firstChild);
                    }
                    if(count($lastChilds)) foreach($lastChilds as $lC){
                        $root->appendChild($lC);
                    }
                }
                else {//continue recursion
                    $children = $root->childNodes;
                    for($i = 0; $i < $children->length; $i++) {
                        $child = $this->DOM2Array( $children->item($i) );
                        //don't keep textnode with only spaces and newline
                        if(!empty($child) && is_array($child)) {
                            $array = array_merge($array, $child);
                        }
                    }
                }
    
            }
    
        //handle text node
        } elseif($root->nodeType == XML_TEXT_NODE) {
            $value = autoglot_utils::prepare_HTML_translation($root->nodeValue);
            if(!empty($value) && strlen(trim($value))) {
                //$array['_type'] = '_text';
                //print_r($root);
                $array[autoglot_utils::gettexthash($value)] = $value;
            }
        } elseif($root->nodeType == XML_CDATA_SECTION_NODE) {
            return;
        }
        $this->blockcounter++;
//        print_r($array);    
        return $array;
    }



    /**
     * Create HTML back from array of translated strings
     */

    function Array2HTML($array_translated){
        $this->blockcounter = 0;    
        $this->array_translated = $array_translated;
        $this->dom_translated = $this->dom_content;
                
        $xpath = new DOMXPath($this->dom_translated);
        
        if($this->autoglot->options->custom_titles && (is_category() || is_tag() || is_tax() || is_author() || (is_archive() && !empty(get_query_var('year'))) || is_search())) {
            
            $custom_title = $this->array_translated[autoglot_utils::gettexthash(get_bloginfo( 'name' ))];
            $custom_pagetitle = "";
            
            if ( is_category() || is_tag() || is_tax()) {
                $term = single_term_title( '', false );
                if(array_key_exists(autoglot_utils::gettexthash($term), $this->array_translated))
                    $custom_pagetitle = $this->array_translated[autoglot_utils::gettexthash($term)]; 
            }
            elseif ( is_author() ) {
                $author = get_queried_object()->display_name;
                if(array_key_exists(autoglot_utils::gettexthash($author), $this->array_translated))
                    $custom_pagetitle = $this->array_translated[autoglot_utils::gettexthash($author)]; 
            }
            elseif ( is_archive() && !empty(get_query_var('year')) ) {
                $year = get_query_var('year');
                $monthnum = get_query_var('monthnum');
                $day = get_query_var('day');

                $custom_pagetitle = $this->array_translated[autoglot_utils::gettexthash(AUTOGLOT_ARCHIVE_TITLE)] . " " . $year;
                if ( !empty($monthnum) )
                        $custom_pagetitle .= ".".zeroise($monthnum,2);
                if ( !empty($day) )
                        $custom_pagetitle .= ".".zeroise($day,2);
            }
            elseif ( is_search()) {
                $custom_pagetitle = $this->array_translated[autoglot_utils::gettexthash(AUTOGLOT_SEARCH_TITLE)] . " &#8220;" . get_search_query() . "&#8221;";
            }
            
            if(strlen($custom_pagetitle))
                $custom_title = $custom_pagetitle . " - " . $this->array_translated[autoglot_utils::gettexthash(get_bloginfo( 'name' ))];
            else 
                $custom_pagetitle = $custom_title;
            
            //<title>
            $titledom = $xpath->query("//title");
            if ($titledom->length) $titledom->item(0)->nodeValue = str_replace($titledom->item(0)->textContent, $custom_title, $titledom->item(0)->nodeValue);
            //<h1 class="page-title"
            $titledom = $xpath->query("//*[contains(concat(\" \", normalize-space(@class), \" \"), \" page-title \")]");
            if ($titledom->length) $titledom->item(0)->nodeValue = str_replace($titledom->item(0)->textContent, $custom_pagetitle, $titledom->item(0)->nodeValue);
            //<meta ...
            $titledoms = $xpath->query("//meta[@property=\"og:title\" or @name=\"twitter:title\"]");
            if ($titledoms->length) foreach($titledoms as $node)
            {
                $node->setAttribute("content", $custom_title);
            }
            
        } else {
            $titledom = $xpath->query("//title");
            if ($titledom->length) if(array_key_exists(autoglot_utils::gettexthash($titledom->item(0)->textContent), $this->array_translated)){
                $titledom->item(0)->nodeValue = str_replace($titledom->item(0)->textContent, $this->array_translated[autoglot_utils::gettexthash($titledom->item(0)->textContent)], $titledom->item(0)->nodeValue);
            }            
            $titledoms = $xpath->query("//meta[@property=\"og:title\" or @name=\"twitter:title\"]");
            if ($titledoms->length) foreach($titledoms as $node)
            {
                $mvalue = $node->getAttribute("content");
                if(array_key_exists(autoglot_utils::gettexthash($mvalue), $this->array_translated)){
                    $node->setAttribute("content", $this->array_translated[autoglot_utils::gettexthash($mvalue)]);
                }
            }
        }

        $metadoms = $xpath->query("//meta[@property=\"og:description\" or @name=\"twitter:description\" or @name=\"description\" or @property=\"og:site_name\"]");
        if ($metadoms->length) foreach($metadoms as $node)
        {
            $mvalue = $node->getAttribute("content");
            if(array_key_exists(autoglot_utils::gettexthash($mvalue), $this->array_translated)){
                $node->setAttribute("content", $this->array_translated[autoglot_utils::gettexthash($mvalue)]);
            }
        }

        $metalangs = $xpath->query("//meta[@property=\"og:locale\"]");
        if ($metalangs->length) foreach($metalangs as $node)
        {
            $node->setAttribute("content", autoglot_utils::get_language_locale($this->autoglot->langURL));
        }
        
        $this->Array2DOM($this->dom_translated->getElementsByTagName('body')->item(0));
//        file_put_contents(__DIR__."/debug.txt", $this->dom_translated->saveHTML());

        //replace all links here
        
        $permalinks = $xpath->query("//a");
        if ($permalinks->length) foreach($permalinks as $node)
        {
            $href = $node->getAttribute('href');
            $type = $node->getAttribute('data-type');
            $href = autoglot_utils::make_link_absolute($href, $this->autoglot->homeURL);
            // Check if the link contains the old domain and does not contain admin links 
            if (strpos($href, $this->autoglot->homeURL) !== FALSE && $type != "languageswitcher" && autoglot_utils::check_not_admin_link($href) ) {
                $trylang = autoglot_utils::get_language_from_url($href, $this->autoglot->homeURL);
                if($trylang && strlen($trylang)>1 && in_array($trylang, $this->autoglot->options->active_languages)) {
                    //language already here, nothing to do?
                } else {
                // Replace old domain with new domain
                    if($this->autoglot->options->translate_urls && isset($this->autoglot->page_links_translated[hash("md5", $href)]) && strlen($this->autoglot->page_links_translated[hash("md5", $href)])) {
                        $newHref = $this->autoglot->page_links_translated[hash("md5", $href)];
                    } else {
                        $newHref = autoglot_utils::add_language_to_url($href, $this->autoglot->homeURL, $this->autoglot->langURL);
                    }
                    $node->setAttribute('href', $newHref);
                }
            }
        }
        
        $canonicals = $xpath->query("//link[@rel=\"canonical\"]");
        if ($canonicals->length) foreach($canonicals as $node)
        {
            $href = $node->getAttribute('href');
            $href = autoglot_utils::make_link_absolute($href, $this->autoglot->homeURL);
            // Check if the link contains the old domain and does not contain admin links 
            if (strpos($href, $this->autoglot->homeURL) !== FALSE && autoglot_utils::check_not_admin_link($href) ) {
                if($this->autoglot->options->translate_urls && isset($this->autoglot->page_links_translated[hash("md5", $href)]) && strlen($this->autoglot->page_links_translated[hash("md5", $href)])) {
                    $newHref = $this->autoglot->page_links_translated[hash("md5", $href)];
                } else {
                    $newHref = autoglot_utils::add_language_to_url($href, $this->autoglot->homeURL, $this->autoglot->langURL);
                }
                $node->setAttribute('href', $newHref);
            }
        }
        $canonicals = $xpath->query("//link[@rel=\"shortlink\"]");
        if ($canonicals->length) foreach($canonicals as $node)
        {
            $href = $node->getAttribute('href');
            $href = autoglot_utils::make_link_absolute($href, $this->autoglot->homeURL);
            // Check if the link contains the old domain and does not contain admin links 
            if (strpos($href, $this->autoglot->homeURL) !== FALSE && autoglot_utils::check_not_admin_link($href) ) {
                $newHref = autoglot_utils::add_language_to_url($href, $this->autoglot->homeURL, $this->autoglot->langURL);
                $node->setAttribute('href', $newHref);
            }
        }
        $canonicals = $xpath->query("//meta[@property=\"og:url\"]");
        if ($canonicals->length) foreach($canonicals as $node)
        {
            $href = $node->getAttribute('content');
            // Check if the link contains the old domain and does not contain admin links 
            if (strpos($href, $this->autoglot->homeURL) !== FALSE && autoglot_utils::check_not_admin_link($href) ) {
                if($this->autoglot->options->translate_urls && isset($this->autoglot->page_links_translated[hash("md5", $href)]) && strlen($this->autoglot->page_links_translated[hash("md5", $href)])) {
                    $newHref = $this->autoglot->page_links_translated[hash("md5", $href)];
                } else {
                    $newHref = autoglot_utils::add_language_to_url($href, $this->autoglot->homeURL, $this->autoglot->langURL);
                }
                $node->setAttribute('content', $newHref);
            }
        }

        $permalinks = $xpath->query("//form");
        if ($permalinks->length) foreach($permalinks as $node)
        {
            $href = $node->getAttribute('action');
            $id = $node->getAttribute('id');
            $href = autoglot_utils::make_link_absolute($href, $this->autoglot->homeURL);
            // Check if the link contains the old domain and does not contain admin links 
            if (strpos($href, $this->autoglot->homeURL) !== FALSE && $id != "commentform" && autoglot_utils::check_not_admin_link($href) ) {
                $trylang = autoglot_utils::get_language_from_url($href, $this->autoglot->homeURL);
                if($trylang && strlen($trylang)>1 && in_array($trylang, $this->autoglot->options->active_languages)) {
                    //language already here, nothing to do?
                } else {
                // Replace old domain with new domain
                    if($this->autoglot->options->translate_urls && isset($this->autoglot->page_links_translated[hash("md5", $href)]) && strlen($this->autoglot->page_links_translated[hash("md5", $href)])) {
                        $newHref = $this->autoglot->page_links_translated[hash("md5", $href)];
                    } else {
                        $newHref = autoglot_utils::add_language_to_url($href, $this->autoglot->homeURL, $this->autoglot->langURL);
                    }
                    $node->setAttribute('action', $newHref);
                }
            }
        }

        return $this->DOM2HTML();
        
    }


    /**
     * Put translated strings back to DOM
     */

    function Array2DOM($root){

        if(in_array($root->nodeName,autoglot_consts::SKIP_TAGS)) {
            return;
        }

        //handle classic node
        if($root->nodeType == XML_ELEMENT_NODE) {
            if($root->hasChildNodes()) {

                $attrexclude = "";
                if(($attrexclude = $root->getAttribute(AUTOGLOT_EXCLUDE_TRANSLATION)) && (strpos($attrexclude, AUTOGLOT_NOTRANSLATE_CLASS) !== false)) {
                    $root->removeAttribute(AUTOGLOT_EXCLUDE_TRANSLATION);
                    return;
                } 

                $this->trimNode($root);

                $whileChecking = true;

                while($root->hasChildNodes() && $whileChecking){
                    
                    if($root->childNodes->item(0)->nodeType == XML_ELEMENT_NODE && $root->childNodes->length == 1 && trim($root->childNodes->item(0)->nodeValue) == trim($root->nodeValue)) {
                        $root = $root->childNodes->item(0);
                        $this->trimNode($root);
                        $attrexclude = "";
                        if(($attrexclude = $root->getAttribute(AUTOGLOT_EXCLUDE_TRANSLATION)) && (strpos($attrexclude, AUTOGLOT_NOTRANSLATE_CLASS) !== false)) {
                            $root->removeAttribute(AUTOGLOT_EXCLUDE_TRANSLATION);
                            return;
                        } 
                        if(in_array($root->nodeName,autoglot_consts::SKIP_TAGS)) return; 
                    
                    }elseif(($root->childNodes->item(0)->nodeType == XML_TEXT_NODE || $root->childNodes->item(0)->nodeType == XML_CDATA_SECTION_NODE) && $root->childNodes->length == 1) {
                        return $this->Array2DOM($root->childNodes->item(0)); 
                    
                    }else {

                        $iFirstChild = 0;
                        while(!is_null($root->childNodes->item($iFirstChild)) && (
                            $root->childNodes->item($iFirstChild)->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->childNodes->item($iFirstChild))))) || 
                            $root->childNodes->item($iFirstChild)->nodeType == XML_TEXT_NODE && !strlen(trim($root->childNodes->item($iFirstChild)->nodeValue)) ||
                            in_array($root->childNodes->item($iFirstChild)->nodeName,autoglot_consts::SKIP_TAGS)
                        )){
                            $iFirstChild++;
                        }
                        if($iFirstChild == $root->childNodes->length) return;//only empty tags/text here

                        $iLastChild = $root->childNodes->length-1;
                        while(!is_null($root->childNodes->item($iLastChild)) && (
                            $root->childNodes->item($iLastChild)->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->childNodes->item($iLastChild))))) || 
                            $root->childNodes->item($iLastChild)->nodeType == XML_TEXT_NODE && !strlen(trim($root->childNodes->item($iLastChild)->nodeValue)) ||
                            in_array($root->childNodes->item($iLastChild)->nodeName,autoglot_consts::SKIP_TAGS)
                        )){
                            $iLastChild--;
                        }

                        if($iFirstChild == $iLastChild){//only one reasonable tag here, go in
                            if(($root->childNodes->item($iFirstChild)->nodeType == XML_TEXT_NODE || $root->childNodes->item($iFirstChild)->nodeType == XML_CDATA_SECTION_NODE)){
                                return $this->Array2DOM($root->childNodes->item($iFirstChild)); 
                            }
                            $root = $root->childNodes->item($iFirstChild);
                            $this->trimNode($root);
                            $attrexclude = "";
                            if($root->nodeType == XML_ELEMENT_NODE && ($attrexclude = $root->getAttribute(AUTOGLOT_EXCLUDE_TRANSLATION)) && (strpos($attrexclude, AUTOGLOT_NOTRANSLATE_CLASS) !== false)) {
                                $root->removeAttribute(AUTOGLOT_EXCLUDE_TRANSLATION);
                                return;
                            }  
                        } else {
                            $whileChecking = false;
                        } 
                        
                    }
                    
                }
                
                $innerHTML = '';
                if(@is_object($root->childNodes))foreach ($root->childNodes as $child) {
                    $innerHTML .= $this->dom_translated->saveHTML($child);
                }
                if (trim(strip_tags($innerHTML)) == '') {
                //only tags - no text. skip
                }
                elseif(strlen($innerHTML) && $innerHTML == strip_tags($innerHTML, autoglot_consts::INLINE_TAGS)) {//text with "allowed" inline tags - translate

                    $firstChilds = array();
                    $lastChilds = array();
                    //first, let's check for empty tags in first/last - often used in links, headers, etc. No need them in translation
                    while($root->firstChild && (
                        $root->firstChild->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->firstChild)))) ||
                        in_array($root->firstChild->nodeName,autoglot_consts::SKIP_TAGS)
                    )){
                        array_unshift($firstChilds, $root->firstChild);
                        $root->removeChild($root->firstChild);
                        $this->trimNode($root);
                    }
                    while($root->lastChild && (
                        $root->lastChild->nodeType == XML_ELEMENT_NODE && !strlen(trim(strip_tags($this->dom_content->saveHTML($root->lastChild)))) ||
                        in_array($root->lastChild->nodeName,autoglot_consts::SKIP_TAGS)
                    )){
                        array_unshift($lastChilds, $root->lastChild);
                        $root->removeChild($root->lastChild);
                        $this->trimNode($root);
                    }

                    $innerHTML = '';
                    foreach ($root->childNodes as $child) {
                        $innerHTML .= $this->dom_translated->saveHTML($child);
                    }
                    $innerHTML = autoglot_utils::prepare_HTML_translation($innerHTML);

                    if(strlen(trim($innerHTML)) && key_exists(autoglot_utils::gettexthash($innerHTML), $this->array_translated) && strlen($this->array_translated[autoglot_utils::gettexthash($innerHTML)])) {
                        while ($root->hasChildNodes()) {
                            $root->removeChild($root->firstChild);
                        }
                        $this->appendHTML($root, "<ag_tag>".$this->array_translated[autoglot_utils::gettexthash($innerHTML)]."</ag_tag>");
                    }
                    $xpath = new DOMXPath($this->dom_translated);
                    $xpathnodes = $xpath->query('.//*', $root);
                    $this->restoreNodeAttributes($xpathnodes, $this->blockcounter);

                    //restore empty tags
                    if(count($firstChilds)) foreach($firstChilds as $fC){
                        $root->insertBefore($fC, $root->firstChild);
                    }
                    if(count($lastChilds)) foreach($lastChilds as $lC){
                        $root->appendChild($lC);
                    }
                }
                else {//continue recursion
                    $children = $root->childNodes;
                    for($i = 0; $i < $children->length; $i++) {
                        $child = $this->Array2DOM( $children->item($i) );
                    }
                }
    
            }
    
        //handle text node
        } elseif($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
            $nodehtml = autoglot_utils::prepare_HTML_translation($root->nodeValue);
            $nodehash = autoglot_utils::gettexthash($nodehtml);
            if(!empty($nodehtml) && strlen(trim($nodehtml)) && key_exists($nodehash, $this->array_translated) && strlen($this->array_translated[$nodehash]) && $this->array_translated[$nodehash] != $nodehtml) {
                $root->nodeValue = strip_tags(html_entity_decode($this->array_translated[$nodehash], ENT_QUOTES | ENT_HTML401, 'UTF-8'));//why strip tags? artefacts from previous versions.
            }
        }
        $this->blockcounter++;    
        
    }
    
    function restoreNodeAttributes($nodes, $blockcounter){
        // Restore attributes
        $nodecounter = 0;

        foreach ($nodes as $node) {
            if($node->hasAttributes() && isset($node->attributes->getNamedItem(AUTOGLOT_REPLACE_ATTRIBUTES)->nodeValue)) {
                $nodenum = $node->attributes->getNamedItem(AUTOGLOT_REPLACE_ATTRIBUTES)->nodeValue;
                $node->removeAttribute(AUTOGLOT_REPLACE_ATTRIBUTES);
                
                if(isset($this->savenodes[$blockcounter][$nodenum]) && is_array($this->savenodes[$blockcounter][$nodenum])) {
                    foreach ($this->savenodes[$blockcounter][$nodenum] as $attr) {
                        $node->setAttribute($attr->nodeName, $attr->nodeValue);
                    }
                }
                /*if(isset($this->savecontent[$blockcounter][$nodenum]["content"])) {
                    //$this->appendHTML($node,"<ag_tag>".$this->savecontent[$blockcounter][$nodenum]["content"]."</ag_tag>");
                    $node->nodeValue = $this->savecontent[$blockcounter][$nodenum]["content"];
                }     */           
            }
        }
    }
    
    function DOM2HTML(){
        // Generate final output
        $output = "";
        $output = $this->dom_translated->saveHTML($this->dom_translated);
        return $output;   
    }
    
}

?>