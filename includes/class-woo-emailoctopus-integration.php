<?php
/**
 * Extend EmailOctopus for WooCommerce
 *
 * @package  FWS_Woo_EmailOctopus_Integration
 * @category Integration
 * @author   Olaf Lederer
 */



class FWS_Woo_EmailOctopus_Integration extends WC_Integration {


	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {

		$this->id = 'fws-woo-emailoctopus';
		$this->method_title = __( 'MailerLite Woo Extension', 'fws-woo-emailoctopus' );
		$this->method_description = __( 'Add newsletter subscribers to a specific MailerLite group', 'fws-woo-emailoctopus' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.




		// Actions.
		add_action( 'woocommerce_update_options_integration_'.$this->id, array( $this, 'process_admin_options' ) ); // callback from parent class
  		add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_to_emailoctopus_callback' ) );

	}

	public function init_form_fields() {
		$this->form_fields = array(
			'group' => array(
                'title' 		=> __( 'Group', 'fws-woo-emailoctopus' ),
                'type' 			=> 'select',
                'class'         => 'wc-enhanced-select',
                'description' => __( 'The default group which will be taken for new subscribers', 'fws-woo-emailoctopus' ),
                'default' 		=> '',
                'options'		=> $this->get_group_options(),
                'desc_tip' => true
            ),
            'checkout_position' => array(
				'title' 		=> __( 'Position', 'fws-woo-emailoctopus' ),
				'type' 			=> 'select',
				'class'         => 'wc-enhanced-select',
				'default' 		=> 'checkout_billing',
				'options'		=> array(
					'checkout_billing' => __( 'After billing details', 'fws-woo-emailoctopus' ),
					'checkout_shipping' => __( 'After shipping details', 'fws-woo-emailoctopus' ),
					'checkout_after_customer_details' => __( 'After customer details', 'fws-woo-emailoctopus' ),
					'checkout_after_terms_and_conditions' => __( 'After terms and conditions', 'fws-woo-emailoctopus' )
				),
			),
			'mailerlite_subscribe_text' => array(
				'title'             => __( 'Text for the newsletter subscription', 'fws-woo-emailoctopus' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'The text for the subscription on the checkout page.', 'fws-woo-emailoctopus' ),
			)
		);
	}

	public function get_group_options() {
		$options = array( '' => __('Choose one...', 'fws-woo-emailoctopus' ) );
		$groups = mailerlite_wp_get_groups();
		foreach ($groups as $group) {
			$options[$group['id']] = $group['name'];
		}
		return $options;
	}

	public function add_to_emailoctopus_callback( $order_id) {
		$order = wc_get_order( $order_id );
		$billing_email  = $order->get_billing_email();
		$resp = $this->get_subscriber_by_email($billing_email);
		if ($this->response_code == 200 && $resp->email == $billing_email) {
			$settings = get_option('woocommerce_fws-woo-mailerlite_settings');
			$response = $this->add_subscriber_to_group($billing_email, $settings['group']);
			if ($this->response_code == 200) {
				$order->add_order_note( $order->get_billing_email().' added to the mailing group');
			} else {
				$order->add_order_note('Failed to add '. $order->get_billing_email().' to the mailing group');
			}
		} else {
			$order->add_order_note('The email address '. $order->get_billing_email().' is not (yet) subscribed.');
		}

	}
	
	public function get_subscriber_by_email($email) {
		$client = new MailerLiteClient('https://api.mailerlite.com/api/v2', [
            'X-MailerLite-ApiKey' => MAILERLITE_WP_API_KEY,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]);
		$response = $client->remote_get( '/subscribers/'.$email );
		return $this->get_response($response);
	}

	public function add_subscriber_to_group($email, $group) {
		$client = new MailerLiteClient('https://api.mailerlite.com/api/v2', [
            'X-MailerLite-ApiKey' => MAILERLITE_WP_API_KEY,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]);
        $subscriber_data = array('email' => $email);
        $response = $client->remote_post( '/groups/' . $group . '/subscribers', $subscriber_data );
        return $this->get_response($response);
    }

    public function get_response($response) {
        if ( ! is_wp_error($response) ) {

            $this->response = wp_remote_retrieve_body($response);
            $this->response_code = wp_remote_retrieve_response_code($response);

            if ( ! is_wp_error($this->response)) {
                $response = json_decode($this->response);

                if (json_last_error() == JSON_ERROR_NONE) {

                    if ( ! isset($response->error)) {
                        return $response;
                    }
                }
            }
        } else {
            $this->response = $response->get_error_message();
            $this->response_code = 0;
        }
        
    }


	public function sanitize_settings( $settings ) {
		return $settings;
	}
}
