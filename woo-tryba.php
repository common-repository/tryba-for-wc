<?php
/*
	Plugin Name:			Tryba Payment Gateway for WooCommerce
	Plugin URI: 			http://tryba.io
	Description:            Tryba payment gateway for WooCommerce
	Version:                1.3.1
	Author: 				Tryba
    Author URI:             https://tryba.io/
	License:        		GPL-2.0+
	License URI:    		http://www.gnu.org/licenses/gpl-2.0.txt
	WC requires at least:   5.0.0
	WC tested up to:        7.3.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WAF_WC_TRYBA_MAIN_FILE', __FILE__ );

define( 'WAF_WC_TRYBA_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

define( 'WAF_WC_TRYBA_VERSION', '1.3.1' );

/**
 * Initialize Tryba WooCommerce payment gateway.
 */
function waf_wc_tryba_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	require_once dirname( __FILE__ ) . '/includes/class-waf-wc-tryba-gateway.php';
	add_filter( 'woocommerce_payment_gateways', 'waf_wc_add_tryba_gateway' );
    add_filter( 'woocommerce_available_payment_gateways', 'conditionally_hide_waf_tryba_payment_gateways' );

}
add_action( 'plugins_loaded', 'waf_wc_tryba_init' );


/**
* Add Settings link to the plugin entry in the plugins menu
**/
function waf_wc_tryba_plugin_action_links( $links ) {

    $settings_link = array(
    	'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=waf_tryba' ) . '" title="View Settings">Settings</a>'
    );
    return array_merge( $settings_link, $links );

}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'waf_wc_tryba_plugin_action_links' );


/**
* Add tryba Gateway to WC
**/
function waf_wc_add_tryba_gateway( $methods ) {
    $methods[] = 'Waf_WC_Tryba_Gateway';
	return $methods;

}

/**
 * @param $available_gateways
 * @return mixed
 * Hide Tryba Condition payment method if the currency is not one of the following: USD, GBP, EUR, GHS, NGN
 */
function conditionally_hide_waf_tryba_payment_gateways( $available_gateways ) {
    // Not in backend (admin)
    if( is_admin() ){
        return $available_gateways;
    }
    $tryba_api = new Waf_WC_Tryba_Gateway();
    $available_currencies = $tryba_api->get_supported_currencies();
    $currency = get_woocommerce_currency();
    if(array_search($currency, $available_currencies) === false){
        unset($available_gateways['waf_tryba']);
    }
    return $available_gateways;
}