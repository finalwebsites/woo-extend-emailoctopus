<?php
/*
Plugin Name: Extend EmailOctopus for WooCommerce
Author: Olaf Lederer
Description: Integrate EmailOctopus for the WooCommerce checkout page
Version: 1.0


*/

define('WCEXTEO_DIR', plugin_dir_path( __FILE__ ));


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
if ( ! class_exists( 'FWS_Woo_EmailOctopus' ) ) {

	class FWS_Woo_EmailOctopus {

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

			// Checks if EmailOctopus plugin is installed.
			if ( class_exists( 'EmailOctopus\Plugin' ) && class_exists( 'WooCommerce') ) {
				// Include the integration class.
				include_once WCEXTEO_DIR . 'includes/class-woo-emailoctopus-integration.php';


				$this->eo_settings = get_option('woocommerce_fws-woo-emailoctopus_settings');

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

				if (isset($this->eo_settings['checkout_position'])) {
					add_action('woocommerce_'.$this->eo_settings['checkout_position'], array( $this, 'subscribe_checkbox_field'));
				}

				add_action('woocommerce_checkout_update_order_meta', array( $this, 'checkout_order_meta'));

				$api_key = get_option('emailoctopus_api_key', false);

				$is_valid_key = \EmailOctopus\Utils::is_valid_api_key($api_key, true);
				if ($is_valid_key === false) {
					add_action( 'admin_notices', function() {
	                $class = 'notice notice-error';
	                $message = __( 'The EmailOctopus API key is not valid. Install a valid API key.', 'fws-woo-emailoctopus' );
	                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

	            });
				}
			} else {
	            add_action( 'admin_notices', function() {
	                $class = 'notice notice-error';
	                $message = __( 'The required plugins "EmailOctopus" and "WooCommerce" need to be installed.', 'fws-woo-emailoctopus' );
	                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

	            });
			}
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'FWS_Woo_EmailOctopus_Integration';
			return $integrations;
		}

		/**
		 * Create a checkbox field for the checkout page.
		 */
		public function subscribe_checkbox_field() {
			$settings = get_option('woocommerce_fws-woo-emailoctopus_settings');
			$label = (!empty($settings['emailoctopus_subscribe_text'])) ? $settings['emailoctopus_subscribe_text'] : __( 'Subscribe newsletter', 'fws-woo-emailoctopus' );
			echo '<div class="fws_custom_class">';
			woocommerce_form_field( 'fws_emailoctopus_checkbox', array(
				'type'          => 'checkbox',
				'label'         => $label,
				'required'  => false
			), null);
			echo '</div>';
		}

		public function checkout_order_meta( $order_id ) {
			if (!empty($_POST['fws_emailoctopus_checkbox'])) 
				update_post_meta( $order_id, 'emailoctopus_subscribed', esc_attr($_POST['fws_emailoctopus_checkbox']));
		}
	}
}

$FWS_Woo_EmailOctopus = new FWS_Woo_EmailOctopus();

