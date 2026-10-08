<?php
/**
 * GetSubscriptionRenewalDetails.
 * php version 5.6
 *
 * @category GetSubscriptionRenewalDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Voxel\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\WordPress\WordPress;
use Exception;

/**
 * GetSubscriptionRenewalDetails
 *
 * On-demand lookup of an order's subscription/renewal state — a live
 * counterpart to Get Member By Email/ID's cached membership snapshot, useful
 * when an automation (e.g. one started by an unrelated trigger) needs to
 * check "is this still an active subscription, and how close is its renewal"
 * for a specific order.
 *
 * Only Stripe-subscription orders carry a renewal date in Voxel; offline/
 * manual membership orders have no renewal date concept.
 *
 * @category GetSubscriptionRenewalDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetSubscriptionRenewalDetails extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Voxel';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'voxel_get_subscription_renewal_details';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Subscription Renewal Details', 'suretriggers' ),
			'action'   => 'voxel_get_subscription_renewal_details',
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
	 * @throws Exception Exception.
	 *
	 * @return bool|array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'Voxel\Product_Types\Orders\Order' ) ) {
			return false;
		}

		$order_id = absint( isset( $selected_options['order_id'] ) ? $selected_options['order_id'] : 0 );

		if ( ! $order_id ) {
			return [
				'status'  => 'error',
				'message' => 'Please enter a valid order ID.',
			];
		}

		$order = \Voxel\Product_Types\Orders\Order::get( $order_id );
		if ( ! $order ) {
			return [
				'status'  => 'error',
				'message' => 'Order not found.',
			];
		}

		$response = [
			'success'            => true,
			'order_id'           => $order->get_id(),
			'status'             => $order->get_status(),
			'is_subscription'    => 'stripe_subscription' === $order->get_payment_method_key(),
			'is_active'          => in_array( $order->get_status(), [ 'sub_active', 'sub_trialing' ], true ),
			'is_past_due'        => 'sub_past_due' === $order->get_status(),
			'renews_at'          => null,
			'will_renew'         => null,
			'days_until_renewal' => null,
		];

		$renews_at = $order->get_details( 'subscription.items.0.current_period_end' );

		if ( is_numeric( $renews_at ) ) {
			$renews_at                       = (int) $renews_at;
			$response['renews_at']           = gmdate( 'Y-m-d H:i:s', $renews_at );
			$response['renews_at_timestamp'] = $renews_at;
			$response['will_renew']          = ! $order->get_details( 'subscription.cancel_at_period_end' );
			$response['days_until_renewal']  = (int) floor( ( $renews_at - time() ) / DAY_IN_SECONDS );
			$response['hours_until_renewal'] = (int) floor( ( $renews_at - time() ) / HOUR_IN_SECONDS );
		}

		$customer_id = $order->get_customer_id();
		if ( $customer_id ) {
			$response['customer'] = WordPress::get_user_context( $customer_id );
		}

		if ( $order->has_vendor() ) {
			$response['vendor'] = WordPress::get_user_context( $order->get_vendor_id() );
		}

		return $response;
	}

}

GetSubscriptionRenewalDetails::get_instance();
