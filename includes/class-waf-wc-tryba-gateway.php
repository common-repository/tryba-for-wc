<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Waf_WC_Tryba_Gateway
 */
class Waf_WC_Tryba_Gateway extends WC_Payment_Gateway {

	/**
	 * Checkout page title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Checkout page description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Is gateway enabled?
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	public $secret_key;

    /**
     * Invoice Prefix for the webiste
     * @var string
     */
    public $invoice_prefix;
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'waf_tryba';
		$this->method_title       = 'Tryba';
        $this->order_button_text = __( 'Proceed to Tryba', 'woocommerce' );
        $this->method_title      = __( 'Tryba', 'woocommerce' );
		$this->method_description = sprintf( 'Receive money from anyone with Our Borderless Payment Collection Platform. Payout straight to your bank account. <a href="%1$s" target="_blank">Sign up</a> for a Tryba account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'https://tryba.io', 'https://tryba.io/user/api' );
        $this->icon = WC_HTTPS::force_https_url( plugins_url( 'assets/images/tryba_logo.jpg', WAF_WC_TRYBA_MAIN_FILE ) );
		$this->has_fields = true;
		$this->supports = array(
			'products',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->public_key = $this->get_option( 'public_key' );
		$this->secret_key = $this->get_option( 'secret_key' );
        $this->invoice_prefix = $this->get_option( 'invoice_prefix' );

		// Hooks.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Payment listener/API hook.
		add_action( 'woocommerce_api_waf_wc_tryba_gateway', array( $this, 'verify_tryba_transaction' ) );
		// Webhook listener/API hook.
		add_action( 'woocommerce_api_tryba_success', array( $this, 'process_success' ) );
        add_action( 'woocommerce_api_tryba_proceed', array( $this, 'tryba_proceed' ) );
	}

    /**
     * @param bool $string
     * @return array|string
     * Get currently supported currencies from tryba endpoint
     */
    public function get_supported_currencies($string = false){
	    $currency_request = wp_remote_get("https://tryba.io/api/currency-supported2");
        $currency_array = array();
	    if ( ! is_wp_error( $currency_request ) && 200 == wp_remote_retrieve_response_code( $currency_request ) ){
            $currencies = json_decode(wp_remote_retrieve_body($currency_request));
            if($currencies->currency_code && $currencies->currency_name){
                foreach ($currencies->currency_code as $index => $item){
                    if($string === true){
                        $currency_array[] = $currencies->currency_name[$index];
                    }else{
                        $currency_array[$currencies->currency_code[$index]] = $currencies->currency_name[$index];
                    }
                }
            }
        }
        if($string === true){
            return implode(", ", $currency_array);
        }
	    return $currency_array;
    }
	/**
	 * Check if Tryba merchant details is filled
	 */
	public function admin_notices() {

		if ( 'no' === $this->enabled ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>' . sprintf( 'Please enter your Tryba merchant details <a href="%s">here</a> to be able to use the Tryba WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=waf_tryba' ) ) . '</p></div>';
			return;
		}

	}

	/**
	 * Check if Tryba gateway is enabled.
	 */
	public function is_available() {

		if ( 'yes' === $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}

	/**
	 * Admin Panel Options
	 */
	public function admin_options() {
		?>

		<h3>Tryba</h3>
        <h4>Our Supported Currencies: <?php echo esc_attr($this->get_supported_currencies(true)); ?></h4>
		<?php
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';

	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'         => array(
				'title'       => __( 'Enable/Disable', 'woo-tryba' ),
				'label'       => __( 'Enable Tryba', 'woo-tryba' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Tryba as a payment option on the checkout page.', 'woo-tryba' ),
				'default'     => 'no',
				'desc_tip'    => false,
			),
			'title'           => array(
				'title'       => __( 'Title', 'woo-tryba' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'woo-tryba' ),
				'desc_tip'    => false,
				'default'     => __( 'Tryba', 'woo-tryba' ),
			),
			'description'     => array(
				'title'       => __( 'Description', 'woo-tryba' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'woo-tryba' ),
				'desc_tip'    => false,
				'default'     => __( 'Make payment using your debit, credit card & bank account', 'woo-tryba' ),
			),
            'invoice_prefix' => array(
                'title'       => __( 'Invoice Prefix', 'woo-tryba' ),
                'type'        => 'text',
                'description' => __( 'Please enter a prefix for your invoice numbers. If you use your Tryba account for multiple stores ensure this prefix is unique as Tryba will not allow orders with the same invoice number.', 'woo-tryba' ),
                'default'     => 'WC_',
                'desc_tip'    => false,
            ),
			'public_key' => array(
				'title'       => __( 'Public Key', 'woo-tryba' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Public Key here. You can get your Public Key from <a href="https://tryba.io/user/api">here</a>', 'woo-tryba' ),
				'default'     => '',
				'desc_tip'    => false,
			),
			'secret_key' => array(
				'title'       => __( 'Secret Key', 'woo-tryba' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Secret Key here. You can get your Secret Key from <a href="https://tryba.io/user/api">here</a>', 'woo-tryba' ),
				'default'     => '',
				'desc_tip'    => false,
			)
		);

	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( ! is_ssl() ){
			return;
		}
	}

    /**
     * Process the payment and return the result.
     *
     * @param  int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
        // Remove cart
        $woocommerce->cart->empty_cart();
        $currency = $order->get_currency();
        $currency_array = $this->get_supported_currencies();
        $currency_code = array_search( $currency , $currency_array ) ? $currency : '';
        $public_key = urlencode($this->public_key);
        $tx_ref = urlencode($this->invoice_prefix . $order_id);
        $amount = urlencode($order->get_total());
        $email = urlencode($order->get_billing_email());
        $callback_url = urlencode(WC()->api_request_url( 'Tryba_Success' ) . "?order_id=" . $order_id . "&payment_id=");
        $first_name = urlencode($order->get_billing_first_name());
        $last_name = urlencode($order->get_billing_last_name());
        $url = WC()->api_request_url( 'Tryba_Proceed' ) . "?public_key={$public_key}&callback_url={$callback_url}&return_url={$callback_url}&tx_ref={$tx_ref}&amount={$amount}&email={$email}&first_name={$first_name}&last_name={$last_name}&currency={$currency_code}";
        //Return to Tryba Proceed page for the next step
        return array(
            'result' => 'success',
            'redirect' => $url
        );
    }

    /**
     * API page to handle the callback data from Tryba
     */
    public function process_success(){
        if ($_GET['order_id']) {
            $order_id = intval(sanitize_text_field($_GET['order_id']));
            $wc_order = wc_get_order($order_id);
            // Verify Tryba payment
            $tryba_payment_id = str_replace('?payment_id=', '', sanitize_text_field($_GET['payment_id']));
            $tryba_request = wp_remote_get(
                'https://checkout.tryba.io/api/v1/payment-intent/' . $tryba_payment_id,
                [
                    'method' => 'GET',
                    'headers' => [
                        'content-type' => 'application/json',
                        'SECRET-KEY' => $this->secret_key,
                    ]
                ]
            );
            if ( ! is_wp_error( $tryba_request ) && 200 == wp_remote_retrieve_response_code( $tryba_request ) ) {
                $tryba_order = json_decode( wp_remote_retrieve_body( $tryba_request ) );
                $status = $tryba_order->status;
                if ($status === "SUCCESS") {
                    $order_total = $wc_order->get_total();
                    $amount_paid = $tryba_order->amount;
                    $order_currency = $wc_order->get_currency();
                    $currency_symbol = get_woocommerce_currency_symbol( $order_currency );
                    if ($amount_paid < $order_total) {
                        // Mark as on-hold
                        $wc_order->update_status('on-hold','' );
                        update_post_meta( $order_id, '_transaction_id', $tryba_payment_id );
                        $notice      = 'Thank you for shopping with us.<br />Your payment was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
                        $notice_type = 'notice';
                        // Add Customer Order Note
                        $wc_order->add_order_note( $notice, 1 );
                        // Add Admin Order Note
                        $wc_order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br /><strong>Reference ID:</strong> ' . $tryba_payment_id);

                        wc_add_notice( $notice, $notice_type );
                    } else {
                        //Complete order
                        $wc_order->payment_complete( $tryba_payment_id );
                        $wc_order->add_order_note( sprintf( 'Payment via Tryba successful (<strong>Reference ID:</strong> %s)', $tryba_payment_id ) );
                    }
                    wp_redirect($this->get_return_url($wc_order));
                    die();
                } else if ($status === "CANCELLED") {
                    $wc_order->update_status( 'canceled', 'Payment was canceled.' );
                    wc_add_notice( 'Payment was canceled.', 'error' );
                    // Add Admin Order Note
                    $wc_order->add_order_note('Payment was canceled by Tryba.');
                    wp_redirect( wc_get_page_permalink( 'checkout' ) );
                    die();
                } else {
                    $wc_order->update_status( 'failed', 'Payment was declined by Tryba.' );
                    wc_add_notice( 'Payment was declined by Tryba.', 'error' );
                    // Add Admin Order Note
                    $wc_order->add_order_note('Payment was declined by Tryba.');
                    wp_redirect( wc_get_page_permalink( 'checkout' ) );
                    die();
                }
            }
        }
        die();
    }

    /**
     * API page to redirect user to Tryba
     */
    public function tryba_proceed(){
        $invalid = 0;
        if(!empty($_GET['public_key']) && wp_http_validate_url($_GET['callback_url'])){
            $public_key = sanitize_text_field($_GET['public_key']);
            $callback_url = sanitize_url($_GET['callback_url']);
        }else{
            wc_add_notice( 'The payment setting of this website is not correct, please contact Administrator', 'error' );
            $invalid++;
        }
        if(!empty($_GET['tx_ref'])){
            $tx_ref = sanitize_text_field($_GET['tx_ref']);
        }else{
            wc_add_notice( 'It seems that something is wrong with your order. Please try again', 'error' );
            $invalid++;
        }
        if(!empty($_GET['amount']) && is_numeric($_GET['amount'])){
            $amount = floatval(sanitize_text_field($_GET['amount']));
        }else{
            wc_add_notice( 'It seems that you have submitted an invalid price for this order. Please try again', 'error' );
            $invalid++;
        }
        if(!empty($_GET['email']) && is_email($_GET['email'])){
            $email = sanitize_email($_GET['email']);
        }else{
            wc_add_notice( 'Your email is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
        if(!empty($_GET['first_name'])){
            $first_name = sanitize_text_field($_GET['first_name']);
        }else{
            wc_add_notice( 'Your first name is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
        if(!empty($_GET['last_name'])){
            $last_name = sanitize_text_field($_GET['last_name']);
        }else{
            wc_add_notice( 'Your last name is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
        if(!empty($_GET['currency'])){
            $currency = sanitize_text_field($_GET['currency']);
        }else{
            wc_add_notice( 'The currency code is not valid. Please check and try again.', 'error' );
            $invalid++;
        }
        if($invalid === 0){
            $apiUrl = 'https://checkout.tryba.io/api/v1/payment-intent/create';
			$apiResponse = wp_remote_post($apiUrl,
				[
					'method' => 'POST',
					'headers' => [
						'content-type' => 'application/json',
						'PUBLIC-KEY' => $public_key,
					],
					'body' => json_encode(array(
						"amount" => $amount,
						"externalId" => $tx_ref,
						"first_name" => $first_name,
						"last_name" => $last_name,
						"meta" => array(),
						"email" => $email,
						"redirect_url" => $callback_url,
						"currency" => $currency
					))
				]
			);
			if (!is_wp_error($apiResponse)) {
				$apiBody = json_decode(wp_remote_retrieve_body($apiResponse));
				$external_url = $apiBody->externalUrl;
				wp_redirect($external_url);
				die();
			} else {
                wc_add_notice( 'Payment was declined by Tryba. Please check and try again', 'error' );
				wp_redirect(wc_get_page_permalink('checkout'));
				die();
			}
        }else{
            wp_redirect(wc_get_page_permalink('checkout'));
        }
        die();
    }
    /**
     * Get the return url (thank you page).
     *
     * @param WC_Order|null $order Order object.
     * @return string
     */
    public function get_return_url( $order = null ) {
        if ( $order ) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
        }
        return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
    }
}
