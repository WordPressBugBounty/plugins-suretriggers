<?php
/**
 * UpdateOrderShipping.
 * php version 5.6
 *
 * @category UpdateOrderShipping
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Voxel\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * UpdateOrderShipping
 *
 * @category UpdateOrderShipping
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateOrderShipping extends AutomateAction {

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
	public $action = 'voxel_update_order_shipping';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Order Shipping', 'suretriggers' ),
			'action'   => 'voxel_update_order_shipping',
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

		$order_id        = absint( isset( $selected_options['order_id'] ) ? $selected_options['order_id'] : 0 );
		$shipping_status = isset( $selected_options['shipping_status'] ) && is_string( $selected_options['shipping_status'] ) ? sanitize_key( $selected_options['shipping_status'] ) : '';
		$raw_tracking    = isset( $selected_options['tracking_link'] ) && is_string( $selected_options['tracking_link'] ) ? trim( (string) $selected_options['tracking_link'] ) : '';
		$tracking_link   = '' !== $raw_tracking ? esc_url_raw( $raw_tracking ) : '';

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

		if ( ! $order->should_handle_shipping() ) {
			return [
				'status'  => 'error',
				'message' => 'This order does not have shipping enabled.',
			];
		}

		$valid_statuses = array_keys( $order::get_shipping_status_config() );

		if ( '' !== $shipping_status && ! in_array( $shipping_status, $valid_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => 'Invalid shipping status. Valid values: ' . implode( ', ', $valid_statuses ),
			];
		}

		if ( '' !== $raw_tracking && '' === $tracking_link ) {
			return [
				'status'  => 'error',
				'message' => 'Please enter a valid tracking link (http or https URL).',
			];
		}

		if ( '' === $shipping_status && '' === $tracking_link ) {
			return [
				'status'  => 'error',
				'message' => 'Please provide a shipping status and/or a tracking link to update.',
			];
		}

		$previous_status = $order->get_shipping_status();

		if ( '' !== $tracking_link ) {
			$order->set_details( 'shipping.tracking_details.link', $tracking_link );
		}

		if ( '' !== $shipping_status ) {
			$order->set_shipping_status( $shipping_status );
		}

		$order->save();

		// Base_Event::dispatch() catches its own exceptions (e.g. orders without a vendor) and logs them, so no try/catch is needed here.
		if ( '' !== $shipping_status && $shipping_status !== $previous_status ) {
			if ( 'shipped' === $shipping_status && class_exists( 'Voxel\Events\Products\Orders\Shipping\Vendor_Marked_Shipped_Event' ) ) {
				( new \Voxel\Events\Products\Orders\Shipping\Vendor_Marked_Shipped_Event() )->dispatch( $order->get_id() );
			} elseif ( 'delivered' === $shipping_status && class_exists( 'Voxel\Events\Products\Orders\Shipping\Vendor_Marked_Delivered_Event' ) ) {
				( new \Voxel\Events\Products\Orders\Shipping\Vendor_Marked_Delivered_Event() )->dispatch( $order->get_id() );
			}
		}

		if ( '' !== $tracking_link && class_exists( 'Voxel\Events\Products\Orders\Shipping\Vendor_Shared_Tracking_Event' ) ) {
			( new \Voxel\Events\Products\Orders\Shipping\Vendor_Shared_Tracking_Event() )->dispatch( $order->get_id() );
		}

		return [
			'success'         => true,
			'message'         => esc_attr__( 'Order shipping details updated successfully.', 'suretriggers' ),
			'order_id'        => $order->get_id(),
			'shipping_status' => $order->get_shipping_status(),
			'tracking_link'   => $order->get_details( 'shipping.tracking_details.link' ),
		];
	}

}

UpdateOrderShipping::get_instance();
