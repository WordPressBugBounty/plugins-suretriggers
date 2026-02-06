<?php
/**
 * AssignListingPlanToUser.
 * php version 5.6
 *
 * @category AssignListingPlanToUser
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Voxel\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * AssignListingPlanToUser
 *
 * @category AssignListingPlanToUser
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class AssignListingPlanToUser extends AutomateAction {

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
	public $action = 'voxel_assign_listing_plan_to_user';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Assign Listing Plan to User', 'suretriggers' ),
			'action'   => 'voxel_assign_listing_plan_to_user',
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
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		global $wpdb;

		// Check if required Voxel classes exist.
		if ( ! class_exists( 'Voxel\User' ) || ! class_exists( 'Voxel\Modules\Paid_Listings\Listing_Plan' ) ) {
			return [
				'success' => false,
				'message' => __( 'Voxel paid listings module is not available.', 'suretriggers' ),
			];
		}

		if ( ! class_exists( 'Voxel\Cart_Item' ) || ! class_exists( 'Voxel\Product_Types\Cart\Direct_Cart' ) ) {
			return [
				'success' => false,
				'message' => __( 'Voxel cart classes are not available.', 'suretriggers' ),
			];
		}

		if ( ! class_exists( 'Voxel\Utils\Config_Schema\Schema' ) ) {
			return [
				'success' => false,
				'message' => __( 'Voxel schema class is not available.', 'suretriggers' ),
			];
		}

		// Get user by email.
		$user_email = isset( $selected_options['wp_user_email'] ) ? $selected_options['wp_user_email'] : '';
		if ( is_email( $user_email ) ) {
			$user    = get_user_by( 'email', $user_email );
			$user_id = $user ? $user->ID : 0;
		}

		if ( empty( $user_id ) ) {
			return [
				'success' => false,
				'message' => __( 'User not found.', 'suretriggers' ),
			];
		}

		// Get the listing plan key.
		$plan_key = isset( $selected_options['listing_plan_key'] ) ? $selected_options['listing_plan_key'] : '';
		if ( empty( $plan_key ) ) {
			return [
				'success' => false,
				'message' => __( 'Listing plan key is required.', 'suretriggers' ),
			];
		}

		// Get the Voxel user.
		$voxel_user = \Voxel\User::get( $user_id );
		if ( ! $voxel_user ) {
			return [
				'success' => false,
				'message' => __( 'Voxel user not found.', 'suretriggers' ),
			];
		}

		// Get the listing plan.
		$plan = \Voxel\Modules\Paid_Listings\Listing_Plan::get( $plan_key );
		if ( ! $plan ) {
			return [
				'success' => false,
				'message' => __( 'Listing plan not found.', 'suretriggers' ),
			];
		}

		// Set product field to free.
		add_filter(
			'voxel/paid-listings/registered-product-field',
			function ( $field ) {
				$value                 = $field->get_value();
				$value['product_type'] = 'voxel:listing_plan_payment';
				$value['base_price']   = [ 'amount' => 0 ];
				$field->_set_value( $value );
				return $field;
			}
		);

		// Create cart with listing plan.
		$cart_item = \Voxel\Cart_Item::create(
			[
				'product' => [
					'post_id'   => $plan->get_product_id(),
					'field_key' => 'voxel:listing_plan',
				],
			]
		);

		$cart = new \Voxel\Product_Types\Cart\Direct_Cart();
		$cart->add_item( $cart_item );

		// Prepare order details.
		$order_details = \Voxel\Utils\Config_Schema\Schema::optimize_for_storage(
			[
				'cart'    => [
					'type'  => $cart->get_type(),
					'items' => array_map(
						function ( $item ) {
							return $item->get_value_for_storage();
						},
						$cart->get_items()
					),
				],
				'pricing' => [
					'currency' => $cart->get_currency(),
					'subtotal' => 0,
					'total'    => 0,
				],
			]
		);

		// Get testmode value.
		$testmode = ( function_exists( '\Voxel\is_test_mode' ) && \Voxel\is_test_mode() ) ? 1 : 0;

		// Get current time.
		$created_at = function_exists( '\Voxel\utc' ) ? \Voxel\utc()->format( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

		// Insert order.
		$order_result = $wpdb->insert(
			$wpdb->prefix . 'vx_orders',
			[
				'customer_id'     => $voxel_user->get_id(),
				'vendor_id'       => null,
				'status'          => 'completed',
				'shipping_status' => null,
				'payment_method'  => 'offline_payment',
				'transaction_id'  => null,
				'details'         => wp_json_encode( $order_details ),
				'parent_id'       => null,
				'testmode'        => $testmode,
				'created_at'      => $created_at,
			]
		);

		if ( false === $order_result ) {
			return [
				'success' => false,
				'message' => __( 'Could not create order.', 'suretriggers' ),
			];
		}

		$order_id = $wpdb->insert_id;

		// Insert order item.
		$item_details = \Voxel\Utils\Config_Schema\Schema::optimize_for_storage(
			$cart_item->get_order_item_config()
		);

		$item_result = $wpdb->insert(
			$wpdb->prefix . 'vx_order_items',
			[
				'order_id'     => $order_id,
				'post_id'      => $cart_item->get_post()->get_id(),
				'product_type' => $cart_item->get_product_type()->get_key(),
				'field_key'    => $cart_item->get_product_field()->get_key(),
				'details'      => wp_json_encode( $item_details ),
			]
		);

		if ( false === $item_result ) {
			return [
				'success' => false,
				'message' => __( 'Could not create order item.', 'suretriggers' ),
			];
		}

		$order_item_id = $wpdb->insert_id;

		// Finalize order.
		if ( class_exists( 'Voxel\Order' ) ) {
			$order = \Voxel\Order::get( $order_id );
			if ( $order ) {
				$order->set_transaction_id( sprintf( 'offline_%d', $order->get_id() ) );
				$order->save();

				// Trigger Voxel hook.
				do_action( 'voxel/paid-listings/backend/assigned-package', $order, $voxel_user, $plan ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			}
		}

		// Get package limits for response.
		$limits_summary = [];
		if ( class_exists( 'Voxel\Modules\Paid_Listings\Listing_Package' ) ) {
			$package = \Voxel\Modules\Paid_Listings\Listing_Package::get( $order_item_id );
			if ( $package ) {
				foreach ( $package->get_limits() as $limit ) {
					$limits_summary[] = [
						'post_types'    => $limit['post_types'],
						'total'         => $limit['total'],
						'used'          => $limit['usage']['count'],
						'available'     => $limit['total'] - $limit['usage']['count'],
						'mark_verified' => $limit['mark_verified'],
					];
				}
			}
		}

		return [
			'success'         => true,
			'message'         => __( 'Listing plan assigned successfully.', 'suretriggers' ),
			'user_id'         => $voxel_user->get_id(),
			'user_email'      => $voxel_user->get_email(),
			'plan_key'        => $plan->get_key(),
			'plan_label'      => $plan->get_label(),
			'package_id'      => $order_item_id,
			'limits'          => $limits_summary,
			'supported_types' => $plan->get_supported_post_types(),
		];
	}
}

AssignListingPlanToUser::get_instance();
