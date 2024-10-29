<?php
/**
 * Autoglot
 * https://autoglot.com/
 *
 * Copyright 2021, Autoglot
 * Description: CURL class for API communication
 */
 
if ( !defined('ABSPATH') ) exit; // Exit if accessed directly

class autoglot_curl {

    /** @var autoglot_plugin father class */
    private $autoglot;
    
    private $balance;
    
    private $connected;
    private $connection_response;
    
    private $authheaders;

    function __construct(&$autoglot) {
        $this->autoglot = &$autoglot;
    }
    
    public function curlInit(){
        if(!strlen($this->autoglot->options->translation_API_key)){
            $this->connected = 0;
            $this->balance = 0;
        }
        else {
            $this->authheaders = array('Access-Key' => $this->autoglot->options->translation_API_key, 'Access-App' => 'AutoGlotWP');
            $httpapi_args = array(
                'timeout'     => 30,
                'headers'     => $this->authheaders,
            );
            $httpapi_response = wp_remote_get( AUTOGLOT_API_URL, $httpapi_args);
            
            if ( is_array( $httpapi_response ) && ! is_wp_error( $httpapi_response ) ) {
                $httpapi_headers = $httpapi_response['headers']; // array of http header lines
                $httpapi_body    = $httpapi_response['body']; // use the content
                $result = json_decode($httpapi_body);

                $this->connected = ($result->success === TRUE ? 1 : 0);
                $this->connection_response = $result->responseDescription;
            }
            else $this->connected = 0;
            
            //now check balance to prevent excess requests, we check balance for each translation, too
            if($this->getConnected()){
				$params = array("site" => get_bloginfo('url'));
                $post_array = array(
                    'apiFunctionName' => 'getBalance',
					'apiFunctionParams' => json_encode($params)
                );
                $httpapi_args = array(
                    'timeout'     => 30,
                    'body'        => $post_array,
                    'headers'     => $this->authheaders,
                );

                $httpapi_response = wp_remote_post( AUTOGLOT_API_URL, $httpapi_args);

                if ( is_array( $httpapi_response ) && ! is_wp_error( $httpapi_response ) ) {
                    $httpapi_headers = $httpapi_response['headers']; // array of http header lines
                    $httpapi_body    = $httpapi_response['body']; // use the content
                    $result = json_decode($httpapi_body);
                }
                else $this->connected = 0;

                if($this->getConnected() && ($result->success === TRUE) && (int)$result->balance > 0)
                    $this->balance = $result->balance;
                else {
                    $this->balance = 0;
                    if($result->response == 450 || $result->response == 451) $this->sendBalanceNotification();
                }
            }            
        }
    }
    
    public function getTranslation($content, $language){
        if($this->getConnected() && $this->getBalance()){
            $params = array("language"=>$language, "content"=>$content, "site"=>get_bloginfo('url'));
            $post_array = array(
                'apiFunctionName' => 'getTranslation',
                'apiFunctionParams' => json_encode($params)
            );

            $httpapi_args = array(
                'timeout'     => 30,
                'body'        => $post_array,
                'headers'     => $this->authheaders,
            );

            $httpapi_response = wp_remote_post( AUTOGLOT_API_URL, $httpapi_args);

            if ( is_array( $httpapi_response ) && ! is_wp_error( $httpapi_response ) ) {
                $httpapi_headers = $httpapi_response['headers']; // array of http header lines
                $httpapi_body    = $httpapi_response['body']; // use the content
                $result = json_decode($httpapi_body);
            }
            else $this->connected = 0;

            if($this->getConnected() && ($result->success === TRUE))
                return $result->translated;
            else {
                if($result->response == 450 || $result->response == 451) $this->sendBalanceNotification();
                return false;   
            }
        }
        return false;
    }
    
    public function getConnected(){
        return $this->connected;
    }
    
    public function getResponse(){
        return $this->connection_response;
    }

    public function getBalance(){
        return $this->balance;
    }
    
    public function sendBalanceNotification(){
        $time_lastsent = filter_var(get_option(AUTOGLOT_DB_LAST_NOTIFICATION, 0), FILTER_VALIDATE_INT);
        if ($time_lastsent && time() - $this->autoglot->options->repeat_balance_notifications < $time_lastsent) {
            return;
        }

        delete_option(AUTOGLOT_DB_LAST_NOTIFICATION);
        $email_address = get_bloginfo('admin_email');
        $email_subject = __('Notification of Low Translation Balance', 'autoglot');
        $email_message = __('Hello site administrator!', 'autoglot')."\r\n\r\n".
            __('This is a notification from Autoglot translation plugin installed on:', 'autoglot').
            ' "'.get_bloginfo('name').'" ('.get_bloginfo('wpurl').").\r\n\r\n".
            __('Your translation balance is either too low or empty and therefore Autoglot cannot automatically translate new content. Although your WordPress site will continue to display your previously translated content, we suggest that you replenish your translation balance in your Autoglot Control Panel:', 'autoglot').
            " ".AUTOGLOT_CP_URL_ORDER."\r\n\r\n".
            __('Once you increase your translation balance, we will continue automatically translating your WordPress website.', 'autoglot')."\r\n\r\n".
            __('Best Regards,', 'autoglot')."\r\n\r\n".
            AUTOGLOT_PLUGIN_NAME;
        if( function_exists('wp_mail') ) {
            wp_mail($email_address, $email_subject, $email_message);
        } else {
            update_option('autoglot_admin_notice',__('Failed to send e-mail notification, function "wp_mail" not found. Your translation balance is either too low or empty and therefore Autoglot cannot automatically translate new content.', 'autoglot'));
            //mail($email_address, $email_subject, $email_message);
        }
        update_option(AUTOGLOT_DB_LAST_NOTIFICATION, time());
    }
}

?>