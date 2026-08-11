<?php
/**
 * Studiocart core integration file
 *
 * @since 1.1.21
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\Studiocart;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;
use WP_User;

/**
 * Class Studiocart
 *
 * @package SureTriggers\Integrations\Studiocart
 */
class Studiocart extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'Studiocart';

	/**
	 * Studiocart constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Studiocart', 'suretriggers' );
		$this->description = __( 'Studiocart is a checkout page builder for WordPress that lets you sell products, order bumps and subscriptions.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/studiocart.svg';

		parent::__construct();
	}

	/**
	 * Is dependent plugin installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'NCS_Cart' );
	}

	/**
	 * Build the shared trigger context from a Studiocart order data array.
	 *
	 * @param array       $order_data Order data supplied by Studiocart.
	 * @param string|null $order_type Order type, e.g. "main" or a bump/upsell slug.
	 * @return array
	 */
	public static function get_order_context( $order_data, $order_type = null ) {
		$context = [
			'order_id'     => isset( $order_data['ID'] ) ? $order_data['ID'] : '',
			'order_status' => isset( $order_data['status'] ) ? $order_data['status'] : '',
			'order_amount' => isset( $order_data['amount'] ) ? $order_data['amount'] : '',
			'product_id'   => isset( $order_data['product_id'] ) ? $order_data['product_id'] : '',
			'product_name' => isset( $order_data['product_name'] ) ? $order_data['product_name'] : '',
			'order_type'   => null !== $order_type ? $order_type : '',
		];

		$user_id = get_current_user_id();

		if ( $user_id ) {
			$user = get_userdata( $user_id );

			$context['user_id']    = $user_id;
			$context['user_email'] = ( $user instanceof WP_User ) ? $user->user_email : '';
		}

		return $context;
	}

}

IntegrationsController::register( Studiocart::class );
