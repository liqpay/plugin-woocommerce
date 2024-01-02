<?php

/*
Plugin Name: LiqPay payment gateway
Plugin URI: https://github.com/liqpay/plugin-woocommerce
Description: LiqPay - це платіжний шлюз, який дозволяє приймати платежі з банківських карт Visa, MasterCard, а також здійснювати оплату через Apple Pay, Google Pay та інші популярні методи оплати.
Version: 0.1.0
Author: LiqPay
License: GPL3
Text Domain: liqpay
*/

$plugin_dir = plugin_dir_path( __FILE__ );

// Підключаємо SDK LiqPay
require_once $plugin_dir . 'includes/LiqPay.php';

add_filter( 'woocommerce_payment_gateways', 'liqpay_register_gateway_class' );

function liqpay_register_gateway_class( $gateways ) {
	$gateways[] = 'Liqpay_Gateway';
	return $gateways;
}

add_action( 'woocommerce_loaded', 'liqpay_gateway_class', 10 );
add_action('woocommerce_loaded', 'liqpay_load_textdomain', 20);
function liqpay_load_textdomain() {
	load_plugin_textdomain('liqpay', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}


function liqpay_gateway_class() {
	
	class Liqpay_Gateway extends WC_Payment_Gateway {
	
        protected $public_key;
        protected $private_key;
	protected $allowed_languages = array('en', 'ua', 'ru');
		public function __construct() {
			$this->id                 = 'liqpay';
			$this->icon               = 'https://www.liqpay.ua/logo_icon_lp.svg?v=1697106744062';
			$this->has_fields         = false;
			$this->method_title       = __( 'Платіжний шлюз від LiqPay', 'liqpay' );
			$this->method_description = __( 'Дозволяє здійснювати оплату через LiqPay', 'liqpay' );
			
			$this->supports = array(
				'products'
			);
			
			$this->init_form_fields();
			
			$this->init_settings();
			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled     = $this->get_option( 'enabled' );
			$this->testmode    = 'yes' === $this->get_option( 'testmode' );
			$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
			$this->public_key  = $this->testmode ? $this->get_option( 'test_publiс_key' ) : $this->get_option( 'publiс_key' );
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
			add_filter('woocommerce_settings_api_sanitized_fields_liqpay', array($this, 'save_private_keys'));
            
            add_action('woocommerce_api_liqpay', array($this, 'liqpay_webhook'));
		}
		
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'          => array(
					'title'       => __( 'Ввімкнений/Вимкнений', 'liqpay' ),
					'label'       => __( 'Ввімкнути оплату через LiqPay', 'liqpay' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title'            => array(
					'title'       => __( 'Заголовок', 'liqpay' ),
					'type'        => 'text',
					'description' => __( 'Це те, що користувач побачить як назву методу оплати на сторінці оформлення замовлення.', 'liqpay' ),
					'default'     => __( 'Оплатити за допомогою LiqPay', 'liqpay' ),
					'desc_tip'    => true,
				),
				'description'      => array(
					'title'       => __( 'Опис', 'liqpay' ),
					'type'        => 'textarea',
					'description' => __( 'Опис цього методу оплати, який буде відображатися користувачеві на сторінці оформлення замовлення.', 'liqpay' ),
					'default'     => __( 'Оплатіть за допомогою LiqPay легко та швидко.', 'liqpay' ),
				),
				'testmode'         => array(
					'title'       => __( 'Тестовий режим', 'liqpay' ),
					'label'       => __( 'Ввімкнути тестовий режим', 'liqpay' ),
					'type'        => 'checkbox',
					'description' => __( 'Якщо ввімкнений тестовий режим, то платіж не буде здійснений, а лише перевірений.', 'liqpay' ),
					'default'     => __( 'Так', 'liqpay' ),
					'desc_tip'    => true,
				),
				'test_publiс_key'  => array(
					'title' => __( 'Тестовий публічный ключ', 'liqpay' ),
					'type'  => 'text'
				),
				'test_private_key' => array(
					'title' => __( 'Тестовий приватний ключ', 'liqpay' ),
					'type'  => 'password',
				),
				'publiс_key'       => array(
					'title' => __( 'Публичний ключ', 'liqpay' ),
					'type'  => 'text'
				),
				'private_key'      => array(
					'title' => __( 'Приватний ключ', 'liqpay' ),
					'type'  => 'password'
				),
				'result_url'       => array(
					'title'       => __( 'Result URL', 'liqpay' ),
					'type'        => 'text',
					'description' => __( 'URL у Вашому магазині на який покупець буде переадресовано після завершення покупки.', 'liqpay' ),
					'default'     => 'https://example.com/checkout/order-received/',
					'desc_tip'    => true,
				),
				'server_url'       => array(
					'title'       => __( 'Server URL', 'liqpay' ),
					'type'        => 'text',
					'description' => __( 'URL у Вашому магазині на який буде відправлено повідомлення про статус платежу.', 'liqpay' ),
					'default'     => 'https://example.com/?wc-api=liqpay',
					'desc_tip'    => true,
				),
			);

		}

		public function save_private_keys($settings) {
			if (empty($settings['private_key'])) {
				$settings['private_key'] = $this->get_option('private_key');
			}
			if (empty($settings['test_private_key'])) {
				$settings['test_private_key'] = $this->get_option('test_private_key');
			}
			return $settings;
		}
  

		public function process_payment($order_id) {
			$order = wc_get_order($order_id);
			$is_test_mode = $this->get_option('testmode') === 'yes';

			$public_key = $is_test_mode ? $this->get_option('test_publiс_key') : $this->get_option('publiс_key');
			$private_key = $is_test_mode ? $this->get_option('test_private_key') : $this->get_option('private_key');


			$liqpay = new LiqPay($public_key, $private_key);

			$current_language = substr(get_locale(), 0, 2);
			$liqpay_language = in_array($current_language, $this->allowed_languages) ? $current_language : 'ua';

			$liqpay_args = array(
				'action'         => 'pay',
				'amount'         => $order->get_total(),
				'currency'       => get_woocommerce_currency(),
				'description'    => sprintf(__('Замовлення %s', 'your-textdomain'), $order->get_order_number()),
				'order_id'       => $order_id,
				'version'        => '3',
				'language'       => $liqpay_language
			);

			$result_url_option = $this->get_option('result_url');
			$server_url_option = $this->get_option('server_url');

			if (!empty($result_url_option)) {
				$liqpay_args['result_url'] = $result_url_option;
			} else {
				$liqpay_args['result_url'] = $this->get_return_url($order);
			}

			if (!empty($server_url_option)) {
				$liqpay_args['server_url'] = $server_url_option;
			}

			$result = $liqpay->cnb_form_raw($liqpay_args);
            
            $order->update_status('pending', __('Awaiting LiqPay payment', 'liqpay'));
            
            WC()->cart->empty_cart();
            
			return array(
				'result'   => 'success',
				'redirect' => "{$result['url']}?data={$result['data']}&signature={$result['signature']}"
			);
		}
  
		public function liqpay_webhook() {
            $liqpay = new LiqPay($this->public_key, $this->private_key);
            if (empty($_POST['data']) || empty($_POST['signature'])) {
                wp_die('No data or signature');
            }
            $params = $liqpay->decode_params($_POST['data']);
            $sign = base64_encode( sha1(
                $this->private_key .
                $_POST['data'] .
                $this->private_key
                , 1 ));
            if ($sign !== $_POST['signature']) {
                wp_die('Signature is not valid');
            }
            $order = wc_get_order($params['order_id']);
            if (!$order) {
                wp_die('Order not found');
            }
            if ($order->get_status() === 'completed') {
                wp_die('Order already completed');
            }
            if ($params['status'] === 'success') {
                $order->payment_complete();
            } elseif ($params['status'] === 'failure') {
                $order->update_status('failed');
            }
            wp_send_json_success();

		}
	
	
	}

}
