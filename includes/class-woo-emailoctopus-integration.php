<?php
/**
 * Extend EmailOctopus for WooCommerce
 *
 * @package  FWS_Woo_EmailOctopus_Integration
 * @category Integration
 * @author   Olaf Lederer
 */

class FWS_Woo_EmailOctopus_Integration extends WC_Integration {

	private $api_key;
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id = 'fws-woo-emailoctopus';
		$this->method_title = __( 'EmailOctopus', 'fws-woo-emailoctopus' );
		$this->method_description = __( 'Add newsletter subscribers to a specific EmailOctopus list', 'fws-woo-emailoctopus' );
		// Load the settings.
		$this->api_key = get_option('emailoctopus_api_key', false);
		$this->init_form_fields();
		$this->init_settings();	
		// Actions.
		add_action( 'woocommerce_update_options_integration_'.$this->id, array( $this, 'custom_process_admin_options' ) ); // callback from parent class
  		add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_subscriber_callback' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'list' => array(
                'title' 		=> __( 'List', 'fws-woo-emailoctopus' ),
                'type' 			=> 'select',
                'class'         => 'wc-enhanced-select',
                'description' => __( 'The default list which will be taken for new subscribers', 'fws-woo-emailoctopus' ),
                'default' 		=> '',
                'options'		=> $this->get_list_options(),
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
			'emailoctopus_subscribe_text' => array(
				'title'             => __( 'Subscription label', 'fws-woo-emailoctopus' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'The text for the subscription on the checkout page.', 'fws-woo-emailoctopus' ),
			),
			'em_store_categories' => array(
				'title'             => __( 'Store product categories', 'fws-woo-emailoctopus' ),
				'type'              => 'checkbox',
				'default'           => '',
				'label'       => __( 'Use the product categories from an order as tags in EmailOctopus.', 'fws-woo-emailoctopus' ),
			),
			'em_store_used_coupon' => array(
				'title'             => __( 'Store coupon', 'fws-woo-emailoctopus' ),
				'type'              => 'checkbox',
				'default'           => '',
				'label'       => __( 'Send the "coupon" tag to EmailOctopus whenever a coupon is used during a checkout.', 'fws-woo-emailoctopus' ),
			),
			'em_store_last_purchase' => array(
				'title'             => __( 'Last order', 'fws-woo-emailoctopus' ),
				'type'              => 'checkbox',
				'default'           => '',
				'label'       => __( 'Store the last order date in EmailOctopus.', 'fws-woo-emailoctopus' ),
			)
		);
	}

	public function custom_process_admin_options() {    
        parent::process_admin_options();
     	$settings = get_option('woocommerce_fws-woo-emailoctopus_settings');
     	if (isset($settings['list']) && !empty($settings['em_store_last_purchase'])) {
			if ($api_response = $this->get_list($settings['list'])) {
				$last_purchase = false;
				foreach ($api_response['fields'] as $field) {
					if ($field['tag'] == 'LastPurchase') {
						$last_purchase = true;
						break;
					}
				}
				if (!$last_purchase) {
					$data = array(
						'api_key' => $this->api_key,
    					'label' => 'Last purchase',
    					'tag' => 'LastPurchase',
    					'type' => 'DATE'
					);
					$this->create_list_field($data, $settings['list']);
				}	
			}
		}
    }

    public function get_list($list_id) {
    	$url = 'https://emailoctopus.com/api/1.6/lists/'.$list_id.'?api_key='.$this->api_key;
		$response = wp_remote_get( esc_url_raw( $url ) );
		if (200 == wp_remote_retrieve_response_code($response)) {
			return json_decode( wp_remote_retrieve_body( $response ), true );
		} else {
			return false;
		}
    }

	public function create_list_field($field, $list) {
		$url = 'https://emailoctopus.com/api/1.6/lists/'.$list.'/fields';
    	$data = wp_remote_post($url, array(
		    'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
		    'body'        => json_encode($field),
		    'method'      => 'POST',
		    'data_format' => 'body',
		));
		
	}

	public function get_list_options() {
		$options = array( '' => __('Choose one...', 'fws-woo-emailoctopus' ) );
		$url = 'https://emailoctopus.com/api/1.6/lists?api_key='.$this->api_key;
		$response = wp_remote_get( esc_url_raw( $url ) );
		$lists = json_decode( wp_remote_retrieve_body( $response ), true );	
		foreach ($lists['data'] as $list) {
			$options[$list['id']] = $list['name'];
		}
		return $options;
	}

	public function get_product_categories($order) {
		$cats = array();
		foreach ( $order->get_items() as $item_id => $item ) {
   			$product_id = $item->get_product_id();
   			$terms = get_the_terms( $product_id, 'product_cat' );
        	foreach ( $terms as $term ) {
            	$cats[] = $term->slug;
            }
        }
        return $cats;
	}

	public function check_existing_tags($tags, $list) {
		if ($list_data = $this->get_list($list)) {
			$update_tags = array();
			foreach ($list_data['tags'] as $tag) {
				if ($test != 'new') {
					$update_tags = array();
					foreach ($tags as $tag) {
						$update_tags[$tag] = true;
					}
					$tags = $update_tags;
				}
			}
		}
	}

	public function add_subscriber_callback( $order_id) {
		$subscribed = get_post_meta($order_id, 'moosend_subscribed', true);
		if (empty($subscribed)) return;
		$order = wc_get_order( $order_id );
		//error_log(print_r($order, true));
		$settings = get_option('woocommerce_fws-woo-emailoctopus_settings');
		$billing_email  = $order->get_billing_email();
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$test = $this->check_subscriber_exists($billing_email, $settings['list']);
		$tags = array();
		if ($settings['em_store_categories']) {
			$categs = $this->get_product_categories($order);
			$update_tags = array();
			if ($test != 'new') {
				foreach ($categs as $cat) {
					$tags[$cat] = true;
				}
			} else {
				$tags = $categs;
			}
		}
		if ($settings['em_store_used_coupon']) {
			$coupons = $order->get_coupon_codes();
			//
			if (count($coupons) > 0) {
				if ($test != 'new') {
					$tags['coupon'] = true;
				} else {
					$tags[] = 'coupon';
				}
			}
		}
		$fields = array('FirstName' => $first_name, 'LastName' => $last_name);
		if ($settings['em_store_last_purchase']) {
			$fields['LastPurchase'] = date('Y-m-d');
		}
		$data_array = array(
			'api_key' => $this->api_key,
			'email_address' => $billing_email,
			'fields' => $fields,
			'tags' => $tags,
			'status' => 'SUBSCRIBED'
		);
		$this->add_subscriber_to_list($data_array, $settings['list'], $test);
		if ($this->response_code == 200) {
			if ($test) { 
				$order->add_order_note( $billing_email.' added to the mailing list');
			} else {
				$order->add_order_note('The email address '. $billing_email.' wasn\'t (yet) subscribed.');
			}
		} else {
			$order->add_order_note('Failed to add '. $billing_email.' to the mailing list');
		}
	}
	
	public function check_subscriber_exists($email_adr, $list_id) {
		$id = md5(strtolower($email_adr));
		$url = 'https://emailoctopus.com/api/1.6/lists/'.$list_id.'/contacts/'.$id.'?api_key='.$this->api_key;
		$response = wp_remote_get( esc_url_raw( $url ) );
		if (200 == wp_remote_retrieve_response_code($response)) {
			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );	
			return $api_response['id'];
		} else {
			return 'new';
		}
	}

	public function add_subscriber_to_list($data_array, $list, $test) {
		$url = 'https://emailoctopus.com/api/1.6/lists/'.$list.'/contacts';
		$method = 'POST';
		if ($test != 'new') {
			$url .= '/'.$test;
			$method = 'PUT';
		} 
		$data = wp_remote_post($url, array(
		    'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
		    'body'        => json_encode($data_array),
		    'method'      => $method,
		    'data_format' => 'body',
		));
        $this->get_response($data);
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
}
