<?php
/**
 * CheckIfOrderIsRenewal.
 * php version 5.6
 *
 * @category CheckIfOrderIsRenewal
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WooCommerce\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * CheckIfOrderIsRenewal
 *
 * @category CheckIfOrderIsRenewal
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CheckIfOrderIsRenewal extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WooCommerce';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wc_check_if_order_is_renewal';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Check if Order is a Renewal', 'suretriggers' ),
			'action'   => 'wc_check_if_order_is_renewal',
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|null
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$order_id = $selected_options['order_id'];

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Abstract_Order ) {
			return [
				'status'  => 'error',
				'message' => 'There is no order associated with this Order ID.',
			];
		}

		$is_renewal              = false;
		$related_subscription_id = '';
		$subscription_status     = '';
		$subscription_start_date = '';
		$next_payment_date       = '';

		// Method 1: Use WooCommerce Subscriptions API to check renewal.
		if ( function_exists( 'wcs_order_contains_renewal' ) ) {
			$is_renewal = (bool) wcs_order_contains_renewal( $order_id );
		}

		// Get related subscriptions for this renewal order.
		if ( $is_renewal && function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			if ( ! empty( $subscriptions ) ) {
				$subscription = reset( $subscriptions );
				if ( $subscription instanceof \WC_Abstract_Order ) {
					$related_subscription_id = (string) $subscription->get_id();
					$subscription_status     = $subscription->get_status();
					$start_date              = $subscription->get_date_created();
					$subscription_start_date = is_object( $start_date ) ? (string) $start_date : '';
					if ( method_exists( $subscription, 'get_date' ) ) {
						$next_payment_date = $subscription->get_date( 'next_payment' );
					}
				}
			}
		}

		// Method 2: Fallback via order relationship.
		if ( ! $is_renewal && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id, [ 'order_type' => 'renewal' ] );
			if ( ! empty( $subscriptions ) ) {
				$is_renewal   = true;
				$subscription = reset( $subscriptions );
				if ( $subscription instanceof \WC_Abstract_Order ) {
					$related_subscription_id = (string) $subscription->get_id();
					$subscription_status     = $subscription->get_status();
					$start_date              = $subscription->get_date_created();
					$subscription_start_date = is_object( $start_date ) ? (string) $start_date : '';
					if ( method_exists( $subscription, 'get_date' ) ) {
						$next_payment_date = $subscription->get_date( 'next_payment' );
					}
				}
			}
		}

		// Method 3: Fallback to order meta check if WCS functions not available.
		if ( ! $is_renewal ) {
			$subscription_renewal = $order->get_meta( '_subscription_renewal' );
			if ( ! empty( $subscription_renewal ) ) {
				$is_renewal              = true;
				$related_subscription_id = is_scalar( $subscription_renewal ) ? (string) $subscription_renewal : '';
			}
		}

		return [
			'order_id'                => $order_id,
			'is_renewal'              => $is_renewal,
			'related_subscription_id' => $related_subscription_id,
			'subscription_status'     => $subscription_status,
			'subscription_start_date' => $subscription_start_date,
			'next_payment_date'       => $next_payment_date,
		];
	}
}

CheckIfOrderIsRenewal::get_instance();
