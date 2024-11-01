<?php
/*
 * Plugin Name: Payment Integration with third party for WooCommerce(Demo)
 * Plugin URI: https://techskype.com/ourproduct/wc-sodexo/
 * Description: Pluxee/Sodexo Payment Gateway Integration | please <a href="https://techskype.com/contact-us/" target="_blank">contact us</a> for premium
 * Version: 2.4.1
 * Tested up to: 6.3
 * Stable tag: 2.4.1
 * Tags: pluxee, sodexo, techskype, payments, india, woocommerce, ecommerce
 * Author: Techskype
 * WC tested up to: 3.7.1
 * Author URI: https://techskype.com
*/

if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

add_action('plugins_loaded', 'techskype_woocommerce_sodexo_init', 0);

add_filter('plugins_api', 'techskype_sodexo_plugin_info', 20, 3);

if (!function_exists('techskype_sodexo_plugin_info'))
{
	function techskype_sodexo_plugin_info( $res, $action, $args ){

		// do nothing if this is not about getting plugin information
		if( 'plugin_information' !== $action ) {
			return false;
		}

		$plugin_slug = 'woo-sodexo'; // we are going to use it in many places in this function

		// do nothing if it is not our plugin
		if( $plugin_slug !== $args->slug ) {
			return false;
		}

		// trying to get from cache first
		if( false == $remote = get_transient( 'techskype_update_' . $plugin_slug ) ) {

			// info.json is the file with the actual plugin information on your server
			$remote = wp_remote_get( 'https://techskype.com/plugin/woo-sodexo/info.json', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				) )
			);

			if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
				set_transient( 'techskype_update_' . $plugin_slug, $remote, 43200 ); // 12 hours cache
			}

		}

		if( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {

			$remote = json_decode( $remote['body'] );
			$res = new stdClass();

			$res->name = $remote->name;
			$res->slug = $plugin_slug;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = '<a href="https://techskype.com">TECHSKYPE</a>';
			$res->author_profile = 'https://profiles.wordpress.org/jain640/';
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->requires_php = '5.3';
			$res->last_updated = $remote->last_updated;
			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
				// you can add your custom sections (tabs) here
			);

			// in case you want the screenshots tab, use the following HTML format for its content:
			// <ol><li><a href="IMG_URL" target="_blank"><img src="IMG_URL" alt="CAPTION" /></a><p>CAPTION</p></li></ol>
			if( !empty( $remote->sections->screenshots ) ) {
				$res->sections['screenshots'] = $remote->sections->screenshots;
			}

			$res->banners = array(
				'low' => 'https://techskype.com/banner-772x250.jpg',
				'high' => 'https://techskype.com/banner-1544x500.jpg'
			);
			return $res;

		}

		return false;

	}
}

if (!function_exists('techskype_sodexo_update'))
{
	add_filter('site_transient_update_plugins', 'techskype_sodexo_update' );

	function techskype_sodexo_update( $transient ){

		if ( empty($transient->checked ) ) {
				return $transient;
			}

		// trying to get from cache first, to disable cache comment 10,20,21,22,24
		if( false == $remote = get_transient( 'techskype_upgrade_woo-sodexo' ) ) {

			// info.json is the file with the actual plugin information on your server
			$remote = wp_remote_get( 'https://techskype.com/plugin/woo-sodexo/info.json', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				) )
			);

			if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
				set_transient( 'techskype_upgrade_woo-sodexo', $remote, 43200 ); // 12 hours cache
			}

		}

		if( $remote ) {

			$remote = json_decode( $remote['body'] );

			// your installed plugin version should be on the line below! You can obtain it dynamically of course
			if( $remote && version_compare( '1.0', $remote->version, '<' ) && version_compare($remote->requires, get_bloginfo('version'), '<' ) ) {
				$res = new stdClass();
				$res->slug = 'woo-sodexo';
				$res->plugin = 'woo-sodexo/woo-sodexo.php'; // it could be just woo-sodexo.php if your plugin doesn't have its own directory
				$res->new_version = $remote->version;
				$res->tested = $remote->tested;
				$res->package = $remote->download_url;
					$transient->response[$res->plugin] = $res;
					//$transient->checked[$res->plugin] = $remote->version;
				}

		}
			return $transient;
	}
}

if (!function_exists('techskype_after_update'))
{
	add_action( 'upgrader_process_complete', 'techskype_after_update', 10, 2 );
	function techskype_after_update( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] === 'plugin' )  {
			// just clean the cache when new plugin version is installed
			delete_transient( 'techskype_upgrade_woo-sodexo' );
		}
	}
}
if (!function_exists('techskype_woocommerce_sodexo_init'))
{
	function techskype_woocommerce_sodexo_init()
	{
		if (!class_exists('WC_Payment_Gateway'))
		{
			return;
		}

		class techskype_Wc_Sodexo extends WC_Payment_Gateway
		{
			// This one stores the WooCommerce Order Id
			//DEV
			const DEV_API_URL                      = 'https://pay-gw.preprod.zeta.in';
			//PROD
			const API_URL                      = 'https://pay-gw.preprod.zeta.in';
			const SESSION_KEY                  = 'sodexo_wc_order_id';
			const SODEXO_PAYMENT_ID            = 'q';
			const SODEXO_ORDER_ID              = 'sodexo_order_id';
			const SODEXO_redirectUserTo        = 'sodexo_redirectUserTo';
			const SODEXO_SIGNATURE             = 'sodexo_signature';
			const SODEXO_WC_FORM_SUBMIT        = 'sodexo_wc_form_submit';

			const INR                            = 'INR';
			const CAPTURE                        = 'capture';
			const AUTHORIZE                      = 'authorize';
			const WC_ORDER_ID                    = 'woocommerce_order_id';

			const DEFAULT_LABEL                  = 'Pay With Sodexo(Demo)';
			const DEFAULT_DESCRIPTION            = 'Pay securely through Sodexo.';
			const DEFAULT_SUCCESS_MESSAGE        = 'Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be processing your order soon.';

			protected $visibleSettings = array(
				'enabled',
				'prod_env',
				'title',
				'description',
				'key_id',
				'acquirer',
				'mid',
				'tid',
				'payment_action',
				'order_success_message'/*,
				'enable_webhook',
				'webhook_secret',*/
			);

			public $form_fields = array();

			public $supports = array(
				'products',
				'refunds'
			);

			/**
			 * Can be set to true if you want payment fields
			 * to show on the checkout (if doing a direct integration).
			 * @var boolean
			 */
			public $has_fields = false;

			/**
			 * Unique ID for the gateway
			 * @var string
			 */
			public $id = 'sodexo';

			/**
			 * Title of the payment method shown on the admin page.
			 * @var string
			 */
			public $method_title = 'Sodexo';


			/**
			 * Description of the payment method shown on the admin page.
			 * @var  string
			 */
			public $method_description = 'Allow customers to securely pay via Sodexo Cards(Demo Version)  | please <a href="https://techskype.com/contact-us/" target="_blank">contact us</a> for premium';

			/**
			 * Icon URL, set in constructor
			 * @var string
			 */
			public $icon;

			public $url_link;

			/**
			 * TODO: Remove usage of $this->msg
			 */
			protected $msg = array(
				'message'   =>  '',
				'class'     =>  '',
			);

			/**
			 * Return Wordpress plugin settings
			 * @param  string $key setting key
			 * @return mixed setting value
			 */
			public function getSetting($key)
			{
				return $this->settings[$key];
			}

			protected function getCustomOrdercreationMessage()
			{
				$message =  $this->getSetting('order_success_message');
				if (isset($message) === false)
				{
					$message = STATIC::DEFAULT_SUCCESS_MESSAGE;
				}
				return $message;
			}

			/**
			 * @param boolean $hooks Whether or not to
			 *                       setup the hooks on
			 *                       calling the constructor
			 */
			public function __construct($hooks = true)
			{

				$this->icon =  plugins_url('images/logo.png' , __FILE__);

				$this->domain             = 'wcpg-sodexo';
				$this->has_fields         = false;

				$user_id = get_current_user_id();
				$tmp_prev_info = get_user_meta($user_id, 'sodexo_source_info');
				if(!empty($tmp_prev_info) && count($tmp_prev_info)>0)
				{
					$new_tmp = array();
					foreach($tmp_prev_info as $tmp_info)
					{
						if(is_array($tmp_info))
							$tmp_info = $tmp_info[0];
						if(!empty($tmp_info))
							$new_tmp[] = $tmp_info;
					}
					if(count($new_tmp)>0)
					{
						// Define "payment type" radio buttons options field
						$this->options = array(
							'new_card' => __( 'New Card', $this->domain ),
							'saved_card' => __( 'Saved Card', $this->domain ),
						);
					}
				}
				$this->init_form_fields();
				$this->init_settings();

				// TODO: This is hacky, find a better way to do this
				// See mergeSettingsWithParentPlugin() in subscriptions for more details.
				if ($hooks)
				{
					$this->initHooks();
				}

				$this->title = $this->getSetting('title');
			}

			/*
			 * Step 3. Content for the new page in My Account, woocommerce_account_{ENDPOINT NAME}_endpoint
			 */


			protected function initHooks()
			{
				add_action('init', array(&$this, 'check_sodexo_response'));

				add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

				add_action('woocommerce_api_' . $this->id, array($this, 'check_sodexo_response'));

				$cb = array($this, 'process_admin_options');

				if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
				{
					add_action("woocommerce_update_options_payment_gateways_{$this->id}", $cb);
				}
				else
				{
					add_action('woocommerce_update_options_payment_gateways', $cb);
				}

				add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_payment_type_meta_data' ), 10, 2 );
				add_filter( 'woocommerce_get_order_item_totals', array( $this, 'display_transaction_type_order_item_totals'), 10, 3 );
				add_action( 'woocommerce_admin_order_data_after_billing_address',  array( $this, 'display_payment_type_order_edit_pages'), 10, 1 );
			}

			public function init_form_fields()
			{
				$webhookUrl = esc_url(admin_url('admin-post.php')) . '?action=sodexo_wc_webhook';

				$defaultFormFields = array(
					'enabled' => array(
						'title' => __('Enable/Disable', $this->id),
						'type' => 'checkbox',
						'label' => __('Enable this module?', $this->id),
						'default' => 'yes'
					),
					'prod_env' => array(
						'title' => __('Enable/Disable', $this->id),
						'type' => 'checkbox',
						'label' => __('Enable this module for Production?', $this->id),
						'default' => 'no'
					),
					'title' => array(
						'title' => __('Title', $this->id),
						'type'=> 'text',
						'description' => __('This controls the title which the user sees during checkout.', $this->id),
						'default' => __(static::DEFAULT_LABEL, $this->id)
					),
					'description' => array(
						'title' => __('Description', $this->id),
						'type' => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', $this->id),
						'default' => __(static::DEFAULT_DESCRIPTION, $this->id)
					),
					'key_id' => array(
						'title' => __('API Key', $this->id),
						'type' => 'text',
						'description' => __('API Key will be shared by Zeta with requester during the on-boarding process..', $this->id)
					),
					'acquirer' => array(
						'title' => __('ACQUIRER', $this->id),
						'type' => 'text',
						'description' => __('acquirer ID given by Sodexo.', $this->id)
					),
					'mid' => array(
						'title' => __('MID', $this->id),
						'type' => 'text',
						'description' => __('merchant ID given by Sodexo.', $this->id)
					),
					'tid' => array(
						'title' => __('TID', $this->id),
						'type' => 'text',
						'description' => __('terminal ID given by Sodexo.', $this->id)
					)
				);

				foreach ($defaultFormFields as $key => $value)
				{
					if (in_array($key, $this->visibleSettings, true))
					{
						$this->form_fields[$key] = $value;
					}
				}
			}

			/**
			 * Output the "payment type" radio buttons fields in checkout.
			 */
			public function payment_fields(){
				return;
			}

			/**
			 * Save the chosen payment type as order meta data.
			 *
			 * @param object $order
			 * @param array $data
			 */
			public function save_order_payment_type_meta_data( $order, $data ) {
			   return '';
			}

			/**
			 * Display the chosen payment type on the order edit pages (backend)
			 *
			 * @param object $order
			 */
			public function display_payment_type_order_edit_pages( $order ){
			   return '';
			}

			/**
			 * Display the chosen payment type on order totals table
			 *
			 * @param array    $total_rows
			 * @param WC_Order $order
			 * @param bool     $tax_display
			 * @return array
			 */
			public function display_transaction_type_order_item_totals( $total_rows, $order, $tax_display ){
				if( is_a( $order, 'WC_Order' ) && $order->get_meta('_transaction_type') ) {
					$new_rows = []; // Initializing
					$options  = $this->options;

					// Loop through order total lines
					foreach( $total_rows as $total_key => $total_values ) {
						$new_rows[$total_key] = $total_values;
						if( $total_key === 'payment_method' ) {
							$new_rows['payment_type'] = [
								'label' => __("Transaction type", $this->domain) . ':',
								'value' => $options[$order->get_meta('_transaction_type')],
							];

							if( $order->get_meta('_transaction_type')=="saved_card" && !empty($order->get_meta('_souceinfo')) ) {
								$tmp_info = $order->get_meta('_souceinfo');
								$api = $this->getSodexoApiInstance().'/v1.0/sodexo/sources/'.$tmp_info;

								$sodexoOrder = $this->postdata_xml('', $api, false);
								$new_rows['payment_type'] = [
									'label' => __("Masked Pan", $this->domain) . ':',
									'value' => $sodexoOrder[trim(strtolower($sodexoOrder['sourceType'])).'SourceDetails']['maskedPan'],
								];
							}
						}
					}

					$total_rows = $new_rows;
				}
				return $total_rows;
			}

			public function admin_options()
			{
				echo '<h3>'.__('Sodexo Payment Gateway', $this->id) . '</h3>';
				echo '<p>'.__('Allows payments by Sodexo Cards(Demo) | please <a href="https://techskype.com/contact-us/" target="_blank">contact us</a> for premium') . '</p>';
				echo '<table class="form-table">';

				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				echo '</table>';
			}

			public function get_description()
			{
				return $this->getSetting('description');
			}

			/**
			 * Receipt Page
			 * @param string $orderId WC Order Id
			 **/
			function receipt_page($orderId)
			{
				echo $this->generate_sodexo_form($orderId);
			}

			/**
			 * Returns key to use in session for storing Sodexo order Id
			 * @param  string $orderId Sodexo Order Id
			 * @return string Session Key
			 */
			protected function getOrderSessionKey($orderId)
			{
				return self::SODEXO_ORDER_ID . $orderId;
			}

			/**
			 * Returns key to use in session for storing Sodexo order Id
			 * @param  string $orderId Sodexo Order Id
			 * @return string Session Key
			 */
			protected function getredirectUserToSessionKey($orderId)
			{
				return self::SODEXO_redirectUserTo . $orderId;
			}

			/**
			 * Given a order Id, find the associated
			 * Sodexo Order from the session and verify
			 * that is is still correct. If not found
			 * (or incorrect), create a new Sodexo Order
			 *
			 * @param  string $orderId Order Id
			 * @return mixed Sodexo Order Id or Exception
			 */
			protected function createOrGetSodexoOrderId($orderId)
			{
				return null;
			}

			/**
			 * Returns redirect URL post payment processing
			 * @return string redirect URL
			 */
			private function getRedirectUrl()
			{
				return get_site_url() . '/wc-api/' . $this->id;
			}

			/**
			 * Specific payment parameters to be passed to checkout
			 * for payment processing
			 * @param  string $orderId WC Order Id
			 * @return array payment params
			 */
			protected function getSodexoPaymentParams($orderId)
			{
				$session_redirectUserTo = $this->createOrGetSodexoOrderId($orderId);

				if ($session_redirectUserTo === null)
				{
					throw new Exception('SODEXO ERROR: Sodexo API could not be reached');
				}
				else if ($session_redirectUserTo instanceof Exception)
				{
					$message = $session_redirectUserTo->getMessage();
					$code = $session_redirectUserTo->getCode();
					if($code==2)
					{

					}
					throw new Exception("SODEXO ERROR: Order creation failed with the message: '$message'.");
				}

				return $session_redirectUserTo;
			}

			/**
			 * Generate sodexo button link
			 * @param string $orderId WC Order Id
			 **/
			public function generate_sodexo_form($orderId)
			{
				$order = new WC_Order($orderId);

				try
				{
					$session_redirectUserTo = $this->getSodexoPaymentParams($orderId);
				}
				catch (Exception $e)
				{
					return $e->getMessage();
				}
				$html = '<p>'.__('Thank you for your order, please click the button below to pay with Sodexo.', $this->id).'</p>';

				$html .= $this->generateOrderForm($session_redirectUserTo);

				return $html;
			}

			/**
			 * @param  WC_Order $order
			 * @return string currency
			 */
			private function getOrderCurrency($order)
			{
				if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>='))
				{
					return $order->get_currency();
				}

				return $order->get_order_currency();
			}

			protected function createSodexoOrderId($orderId, $sessionKey, $session_redirectUserTo)
			{
				// Calls the helper function to create order data
				global $woocommerce;

				$data = $this->getOrderCreationData($orderId);

				$api = $this->getSodexoApiInstance().$this->url_link;

				try
				{
					$sodexoOrder = $this->postdata_xml($data, $api, true);
				}
				catch (Exception $e)
				{
					return $e;
				}

				$sodexoOrderId = $sodexoOrder['transactionId'];
				$sodexoRedirectUserTo = $sodexoOrder['redirectUserTo'];

				$woocommerce->session->set($sessionKey, $sodexoOrderId);
				$woocommerce->session->set($session_redirectUserTo, $sodexoRedirectUserTo);

				//update it in order comments
				$order = new WC_Order($orderId);

				$order->add_order_note("Sodexo OrderId: $sodexoOrderId");

				return $sodexoRedirectUserTo;
			}

			protected function verifyOrderAmount($sodexoOrderId, $orderId)
			{
				return false;
			}

			private function getOrderCreationData($orderId)
			{

				$order = new WC_Order($orderId);
				$data = array(
				);
				return $data;
			}

			/**
			 * Generates the order form
			 **/
			function generateOrderForm($redirectUrl)
			{
				wp_redirect($redirectUrl);
				exit;
			}

			/**
			 * Gets the Order Key from the Order
			 * for all WC versions that we suport
			 */
			protected function getOrderKey($order)
			{
				$orderKey = null;

				if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>='))
				{
					return $order->get_order_key();
				}

				return $order->order_key;
			}

			public function process_refund($orderId, $amount = null, $reason = '')
			{
				return new WP_Error('error', __('Not availabe in Demo version, please <a href="https://techskype.com/contact-us/" target="_blank">contact us</a> for premium', 'woocommerce'));
			}

			/**
			 * Process the payment and return the result
			 **/
			function process_payment($order_id)
			{
				global $woocommerce;
				$order = new WC_Order($order_id);
				$woocommerce->session->set(self::SESSION_KEY, $order_id);

				$orderKey = $this->getOrderKey($order);

				if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>='))
				{
					return array(
						'result' => 'success',
						'redirect' => add_query_arg('key', $orderKey, $order->get_checkout_payment_url(true))
					);
				}
				else if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
				{
					return array(
						'result' => 'success',
						'redirect' => add_query_arg('order', $order->get_id(),
							add_query_arg('key', $orderKey, $order->get_checkout_payment_url(true)))
					);
				}
				else
				{
					return array(
						'result' => 'success',
						'redirect' => add_query_arg('order', $order->get_id(),
							add_query_arg('key', $orderKey, get_permalink(get_option('woocommerce_pay_page_id'))))
					);
				}
			}

			public function getSodexoApiInstance()
			{
					return self::DEV_API_URL;
			}

			function postdata_xml($xml_data, $url, $post= true){
				throw new exception('Not availabe in Demo version, please <a href="https://techskype.com/contact-us/" target="_blank">contact us</a> for premium');
			}

			/**
			 * Check for valid sodexo server callback
			 **/
			function check_sodexo_response()
			{
				 return new WP_Error('error', __('Not availabe in Demo version, please <a href="https://techskype.com/contact-us/" target="_blank">contact us</a> for premium', 'woocommerce'));
			}

			protected function redirectUser($order)
			{
				$redirectUrl = $this->get_return_url($order);

				wp_redirect($redirectUrl);
				exit;
			}

			protected function verifySignature($orderId)
			{
				global $woocommerce;

				$sessionKey = $this->getOrderSessionKey($orderId);
				$sodexoOrderId = $woocommerce->session->get($sessionKey);

				$api = $this->getSodexoApiInstance().'/v1.0/sodexo/transactions/'.$sodexoOrderId;

				$sodexoOrder = $this->postdata_xml('', $api, false);

				return $sodexoOrder;
			}

			/**
			 * Modifies existing order and handles success case
			 *
			 * @param $success, & $order
			 */
			public function updateOrder(& $order, $success, $errorMessage, $sodexoPaymentId, $webhook = false)
			{
				$this->add_notice("Dummy", $this->msg['class']);
			}

			/**
			 * Add a woocommerce notification message
			 *
			 * @param string $message Notification message
			 * @param string $type Notification type, default = notice
			 */
			protected function add_notice($message, $type = 'notice')
			{
				global $woocommerce;
				$type = in_array($type, array('notice','error','success'), true) ? $type : 'notice';
				// Check for existence of new notification api. Else use previous add_error
				if (function_exists('wc_add_notice'))
				{
					wc_add_notice($message, $type);
				}
				else
				{
					// Retrocompatibility WooCommerce < 2.1
					switch ($type)
					{
						case "error" :
							$woocommerce->add_error($message);
							break;
						default :
							$woocommerce->add_message($message);
							break;
					}
				}
			}
		}

		/**
		 * Add the Gateway to WooCommerce
		 **/
		function woocommerce_add_sodexo_gateway($methods)
		{
			$methods[] = 'techskype_Wc_Sodexo';
			return $methods;
		}
		/*
		 * Step 2. Register Permalink Endpoint
		 */


		add_filter('woocommerce_payment_gateways', 'woocommerce_add_sodexo_gateway' );
	}
}
