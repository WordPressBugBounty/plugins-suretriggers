<?php
/**
 * AddBookingToOrder.
 * php version 5.6
 *
 * @category AddBookingToOrder
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\LatePoint\Actions;

use Exception;
use OsBookingModel;
use OsCustomerModel;
use OsOrderModel;
use OsOrderItemModel;
use OsOrdersHelper;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * AddBookingToOrder
 *
 * @category AddBookingToOrder
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class AddBookingToOrder extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'LatePoint';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'lp_add_booking_to_order';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add Booking to Existing Order', 'suretriggers' ),
			'action'   => 'lp_add_booking_to_order',
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
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'OsOrderModel' ) || ! class_exists( 'OsBookingModel' ) ||
			! class_exists( 'OsOrderItemModel' ) || ! class_exists( 'OsOrdersHelper' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'LatePoint plugin not installed.', 'suretriggers' ),
			];
		}

		$required_params = [
			'order_id',
			'service_id', 
			'agent_id',
			'start_date',
			'start_time',
		];

		foreach ( $required_params as $param ) {
			if ( empty( $selected_options[ $param ] ) ) {
				return [
					'status'  => 'error',
					'message' => sprintf( __( 'Missing required parameter: %s', 'suretriggers' ), $param ),
				];
			}
		}

		$order_id = intval( $selected_options['order_id'] );
		$order    = new OsOrderModel( $order_id );

		if ( ! $order->id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Order not found.', 'suretriggers' ),
			];
		}

		try {
			// Convert time to minutes.
			$convert_to_minutes = function( $time ) {
				if ( $time ) {
					if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
						throw new Exception( __( 'Invalid time format. Expected HH:MM format.', 'suretriggers' ) );
					}
					$time_parts = explode( ':', $time );
					$hours      = (int) $time_parts[0];
					$minutes    = (int) $time_parts[1];
					return ( $hours * 60 ) + $minutes;
				}
				return null;
			};

			$start_time = $convert_to_minutes( $selected_options['start_time'] );
			$end_time   = isset( $selected_options['end_time'] ) ? $convert_to_minutes( $selected_options['end_time'] ) : null;

			$start_date           = gmdate( 'Y-m-d', strtotime( $selected_options['start_date'] ) );
			$start_date_formatted = gmdate( 'm/d/Y', strtotime( $selected_options['start_date'] ) );
			$end_date             = $start_date;

			$booking_params = [
				'agent_id'             => intval( $selected_options['agent_id'] ),
				'service_id'           => intval( $selected_options['service_id'] ),
				'customer_id'          => $order->customer_id,
				'location_id'          => isset( $selected_options['location_id'] ) ? intval( $selected_options['location_id'] ) : 1,
				'start_date'           => $start_date,
				'start_date_formatted' => $start_date_formatted,
				'end_date'             => $end_date,
				'start_time'           => $start_time,
				'end_time'             => $end_time,
				'status'               => isset( $selected_options['status'] ) ? $selected_options['status'] : 'approved',
				'total_attendees'      => isset( $selected_options['total_attendees'] ) ? intval( $selected_options['total_attendees'] ) : 1,
				'customer_comment'     => isset( $selected_options['customer_comment'] ) ? sanitize_textarea_field( $selected_options['customer_comment'] ) : '',
				'payment_status'       => 'not_paid',
				'buffer_before'        => isset( $selected_options['buffer_before'] ) ? intval( $selected_options['buffer_before'] ) : 0,
				'buffer_after'         => isset( $selected_options['buffer_after'] ) ? intval( $selected_options['buffer_after'] ) : 0,
				'source_url'           => site_url(),
			];

			$booking_custom_fields = [];
			if ( ! empty( $selected_options['booking_fields'] ) ) {
				foreach ( $selected_options['booking_fields'] as $field ) {
					if ( is_array( $field ) && ! empty( $field ) ) {
						foreach ( $field as $key => $value ) {
							if ( false === strpos( $key, 'field_column' ) && '' !== $value ) {
								$booking_custom_fields[ $key ] = sanitize_text_field( $value );
							}
						}
					}
				}
			}
			$booking_params['custom_fields'] = $booking_custom_fields;

			$order_item           = new OsOrderItemModel();
			$order_item->order_id = $order->id;
			$order_item->variant  = defined( 'LATEPOINT_ITEM_VARIANT_BOOKING' ) ? LATEPOINT_ITEM_VARIANT_BOOKING : 'booking';

			if ( ! $order_item->save() ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to create order item for booking.', 'suretriggers' ),
				];
			}

			// Use LatePoint helper for better consistency.
			$booking                = OsOrdersHelper::create_booking_object_from_booking_data_form( $booking_params );
			$booking->customer_id   = $order->customer_id;
			$booking->order_item_id = $order_item->id;

			if ( ! $booking->save() ) {
				$order_item->delete();
				return [
					'status'  => 'error',
					'message' => sprintf( 
						__( 'Failed to save booking: %s', 'suretriggers' ), 
						implode( ', ', $booking->get_error_messages() ) 
					),
				];
			}

			// Update order item following LatePoint pattern.
			if ( $order_item->is_booking() ) {
				$order_item->item_data = $booking->generate_item_data();
				$order_item->recalculate_prices();
				$order_item->save();
			}

			if ( method_exists( $order, 'recalculate_totals' ) ) {
				$order->recalculate_totals();
			} else {
				$order_items = $order->get_items();
				$total       = 0;
				$subtotal    = 0;
				foreach ( $order_items as $item ) {
					$total    += $item->total;
					$subtotal += $item->subtotal;
				}
				$order->total    = $total;
				$order->subtotal = $subtotal;
			}

			$order->save();

			do_action( 'latepoint_booking_created', $booking );

			return [
				'status'  => 'success',
				'message' => __( 'Booking added to order successfully.', 'suretriggers' ),
				'booking' => $booking->get_data_vars(),
				'order'   => [
					'id'       => $order->id,
					'total'    => $order->total,
					'subtotal' => $order->subtotal,
				],
			];

		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}
}

AddBookingToOrder::get_instance();
