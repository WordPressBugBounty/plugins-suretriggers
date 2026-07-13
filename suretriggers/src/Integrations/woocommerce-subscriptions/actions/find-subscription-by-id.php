<?php
/**
 * FindSubscriptionByID.
 * php version 5.6
 *
 * @category FindSubscriptionByID
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WoocommerceSubscriptions\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\WordPress\WordPress;

/**
 * FindSubscriptionByID
 *
 * @category FindSubscriptionByID
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class FindSubscriptionByID extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WoocommerceSubscriptions';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wc_find_subscription_by_id';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Find Subscription by Subscription ID', 'suretriggers' ),
			'action'   => 'wc_find_subscription_by_id',
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
	 * @return object|array|void
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$subscription_id = isset( $selected_options['subscription_id'] ) ? $selected_options['subscription_id'] : '';

		if ( empty( $subscription_id ) || ! is_numeric( $subscription_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Please provide a valid Subscription ID.', 'suretriggers' ),
			];
		}

		$subscription_id = absint( $subscription_id );

		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'wcs_get_subscription function not found.', 'suretriggers' ),
			];
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return [
				'status'  => 'error',
				'message' => 'Subscription not found for the provided Subscription ID.',
			];
		}

		$product_ids   = [];
		$product_names = [];
		$items         = $subscription->get_items();
		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}
				if ( $product->is_type( [ 'variable-subscription', 'subscription_variation' ] ) ) {
					$product_ids[]   = $item->get_variation_id();
					$product_names[] = get_the_title( $item->get_variation_id() );
				} else {
					$product_ids[]   = $item->get_product_id();
					$product_names[] = get_the_title( $item->get_product_id() );
				}
			}
		}

		$context = [
			'subscription' => [
				'id'                => $subscription->get_id(),
				'status'            => $subscription->get_status(),
				'order_id'          => $subscription->get_parent_id(),
				'start_date'        => $subscription->get_date( 'start' ),
				'next_payment_date' => $subscription->get_date( 'next_payment' ),
				'end_date'          => $subscription->get_date( 'end' ),
				'total'             => $subscription->get_total(),
				'currency'          => $subscription->get_currency(),
				'product_ids'       => implode( ', ', $product_ids ),
				'product_names'     => implode( ', ', $product_names ),
			],
		];

		return array_merge( $context, WordPress::get_user_context( $subscription->get_user_id() ) );
	}
}

FindSubscriptionByID::get_instance();
