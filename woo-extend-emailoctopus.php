<?php
/*
Plugin Name: Extend MailerLite for WooCommerce
Author: Olaf Lederer
Description: Extend the MailerLite connector for WooCommerce
Version: 1.0


*/

define('WCEXTML_DIR', plugin_dir_path( __FILE__ ));


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
if ( ! class_exists( 'FWS_Woo_Mailerlite' ) ) :

class FWS_Woo_Mailerlite {

	public $ml_settings;

	/**
	* Construct the plugin.
	*/
	public function __construct() {

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	* Initialize the plugin.
	*/
	public function init() {

		// Checks if MailerLite - WooCommerce integration is installed.
		if ( class_exists( 'Woo_Mailerlite' ) ) {
			// Include the integration class.
			include_once WCEXTML_DIR . 'includes/class-woo-mailerlite-integration.php';


			$this->ml_settings = get_option('woocommerce_fws-woo-mailerlite_settings');

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'fws_add_integration' ) );

			add_action('woocommerce_'.$this->ml_settings['checkout_position'], array( $this, 'fws_subscribe_checkbox_field'));

		} else {
            add_action( 'admin_notices', function() {
                $class = 'notice notice-error';
                $message = __( 'The required plugin "MailerLite - WooCommerce integration" is not installed.', 'fws-woo-mailerlite' );
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

            });
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public function fws_add_integration( $integrations ) {
		$integrations[] = 'FWS_Woo_Mailerlite_Integration';
		return $integrations;
	}

	public function fws_subscribe_checkbox_field() {
		$settings = get_option('woocommerce_fws-woo-mailerlite_settings');
		$label = (!empty($settings['mailerlite_subscribe_text'])) ? $settings['mailerlite_subscribe_text'] : __( 'Subscribe newsletter', 'fws-woo-mailerlite' );
		echo '<div class="fws_custom_class">';
		woocommerce_form_field( 'fws_mailerlite_checkbox', array(
			'type'          => 'checkbox',
			'label'         => $label,
			'required'  => false
		), null);
		echo '</div>';
	}



}

$FWS_Woo_Mailerlite = new FWS_Woo_Mailerlite();

endif;
