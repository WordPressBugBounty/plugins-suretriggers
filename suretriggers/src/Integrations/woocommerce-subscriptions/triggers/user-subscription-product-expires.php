<?php
/**
 * UserSubscriptionProductExpires.
 * php version 5.6
 *
 * @category UserSubscriptionProductExpires
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WoocommerceSubscriptions\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use WC_Subscription;
use WC_Customer;
use SureTriggers\Integrations\WordPress\WordPress;

if ( ! class_exists( 'UserSubscriptionProductExpires' ) ) :

	/**
	 * UserSubscriptionProductExpires
	 *
	 * @category UserSubscriptionProductExpires
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UserSubscriptionProductExpires {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'WoocommerceSubscriptions';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wc_subscription_product_expires';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'User Subscription Expires', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'woocommerce_subscription_status_expired',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 30,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 *  Trigger listener
		 *
		 * @param object $subscription Subscription.
		 *
		 * @return void
		 */
		public function trigger_listener( $subscription ) {

			if ( ! class_exists( '\WC_Subscription' ) ) {
				return;
			}
			if ( ! $subscription instanceof \WC_Subscription ) {
				return;
			}

			if ( is_object( $subscription ) ) {
				$subscription = $subscription;
			}

			$items       = $subscription->get_items();
			$product_ids = [];
			foreach ( $items as $item ) {
				$product = $item->get_product();
				if ( class_exists( '\WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
					$product_ids[] = $item->get_product_id();
				}
			}

			$subscription_status            = $subscription->get_status();
			$subscription_start_date        = $subscription->get_date_created();
			$subscription_next_payment_date = $subscription->get_date( 'next_payment' );
			
			$context['subscription_data'] = [
				'status'            => $subscription_status,
				'start_date'        => $subscription_start_date,
				'next_payment_date' => $subscription_next_payment_date,
			];
			$context['subscription_id']   = $subscription->get_id();
			$context['user']              = WordPress::get_user_context( $subscription->get_user_id() );
			// Get WooCommerce checkout instance for the user details.
			WC()->customer = new WC_Customer( $subscription->get_user_id() );
			$checkout      = WC()->checkout();
			if ( $checkout ) {
				$billing_fields = $checkout->get_checkout_fields( 'billing' );
				$field_details  = [];
				foreach ( $billing_fields as $key => $field ) {
					$field_id                   = $key;
					$field_value                = get_user_meta( $subscription->get_user_id(), $field_id, true );
					$field_details[ $field_id ] = $field_value;
				}
				$context['user_billing_data'] = $field_details;
			}

			foreach ( $product_ids as $val ) {
				$context['subscription']      = $val;
				$context['subscription_name'] = get_the_title( $val );
			}
			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	UserSubscriptionProductExpires::get_instance();

endif;
