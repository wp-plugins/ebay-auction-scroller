<?php
/*
 Plugin Name: ebay auction scroller - free
 Plugin URI:http://www.webimpressions.co.uk/wp/plugins/eas/ 
 Author URI:http://www.webimpressions.co.uk
 Description: ebay auction display widget. Vertical scrolling Ebay auction listing filtered by region, category and seller id.
 Author: Dave Heath
 Version: 1.0
 */

if(!defined('WP_CONTENT_URL')) {
	$eas_p_url = get_option('siteurl') . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__)).'/';
}else{
	$eas_p_url = WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)) . '/';
}

define('WI_EAS_F_VERSION', '0.9');
define('WI_EAS_F_AUTHOR', 'Dave Heath');
define('WI_EAS_F_URL', $eas_p_url);
## Default color styles
$eas_color_styles = array(
'No style' => 'none',
'Grey' => 'grey',
'Dark' => 'dark',
'Orange' => 'orange',
'Simple modern' => 'smodern',
'Pastel blue' => 'blue'		
);
$ebay_global_id_list = array(
		'EBAY-AT' => 'eBay Austria',
		'EBAY-AU' => 'eBay Australia',
		'EBAY-CH' => 'eBay Switzerland',
		'EBAY-DE' => 'eBay Germany',
		'EBAY-ENCA' => 'eBay Canada (English)',
		'EBAY-ES' => 'eBay Spain',
		'EBAY-FR' => 'eBay France',
		'EBAY-FRBE' => 'eBay Belgium (French)',
		'EBAY-FRCA' => 'eBay Canada (French)',
		'EBAY-GB' => 'eBay UK',
		'EBAY-HK' => 'eBay Hong Kong',
		'EBAY-IE' => 'Ireland',
		'EBAY-IN' => 'eBay India',
		'EBAY-IT' => 'eBay Italy',
		'EBAY-MOTOR' => 'eBay Motors',
		'EBAY-MY' => 'eBay Malaysia',
		'EBAY-NL' => 'eBay Netherlands',
		'EBAY-NLBE' => 'eBay Belgium (Dutch)',
		'EBAY-PH' => 'eBay Philippines',
		'EBAY-PL' => 'eBay Poland',
		'EBAY-SG' => 'eBay Singapore',
		'EBAY-US' => 'eBay United States'

);
date_default_timezone_set('GMT');
require_once('functions.php' );
add_action('wp_enqueue_scripts', 'eas_public_scripts');
add_action('wp_enqueue_scripts', 'eas_public_styles');
add_action('widgets_init', 'eas_scroller_init');
add_action('admin_footer', 'eas_widget_scripts');