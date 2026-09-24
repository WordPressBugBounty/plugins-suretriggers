<?php
/**
 * LatePoint core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\LatePoint;

use Exception;
use OsAgentModel;
use OsBookingModel;
use OsCustomerModel;
use OsOrderModel;
use OsOrdersHelper;
use OsOrderItemModel;
use OsServiceModel;
use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\LatePoint
 */
class LatePoint extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'LatePoint';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'LatePoint', 'suretriggers' );
		$this->description = __( 'Appointment Scheduling Plugin for WordPress.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/late-point.svg';

		// Some LatePoint pricing add-ons (e.g. base-fee pricing) type-hint a
		// non-nullable float on this filter and fatal when a service has no
		// charge_amount configured. Sanitize at the source, before any other
		// callback receives it - this protects every caller (our own actions,
		// LatePoint's native admin UI, any other integration), not just the
		// specific code path inside this class.
		add_filter( 'latepoint_full_amount_for_service', [ __CLASS__, 'sanitize_full_amount_for_service' ], 1 );

		parent::__construct();
	}

	/**
	 * Coerce a null full-amount-for-service value to 0.0 before any other
	 * 'latepoint_full_amount_for_service' filter callback can receive it.
	 *
	 * @param mixed $amount Amount to charge for the service.
	 * @return mixed
	 */
	public static function sanitize_full_amount_for_service( $amount ) {
		return null === $amount ? 0.0 : $amount;
	}

	/**
	 * Create/Update booking.
	 *
	 * @param array $selected_options Selected options.
	 * @param bool  $is_update is update.
	 * @return array
	 * @throws Exception Exception.
	 */
	public static function create_or_update_booking( $selected_options, $is_update = false ) {
		if ( ! class_exists( 'OsBookingModel' ) || ! class_exists( 'OsCustomerModel' ) || ! class_exists( 'OsOrderModel' ) || ! class_exists( 'OsOrderItemModel' ) || ! class_exists( 'OsOrdersHelper' ) ) {
			throw new Exception( 'LatePoint plugin not installed.' );
		}

		if ( $is_update ) {
			$booking_id = isset( $selected_options['booking_id'] ) ? $selected_options['booking_id'] : null;
			if ( ! $booking_id ) {
				throw new Exception( 'Booking ID not provided.' );
			}

			$booking = new OsBookingModel( $booking_id );
			if ( ! isset( $booking->id ) || ! $booking->id ) {
				throw new Exception( 'Booking not found.' );
			}
			$old_booking = clone $booking;
		} else {
			$booking = new OsBookingModel();
		}

		$customer_type = isset( $selected_options['customer_type'] ) ? $selected_options['customer_type'] : 'new';
		$customer_id   = null;

		if ( 'existing' === $customer_type ) {
			$customer_id = isset( $selected_options['customer_id'] ) ? $selected_options['customer_id'] : null;
			if ( ! $customer_id ) {
				throw new Exception( 'Customer ID not provided.' );
			}
		}

		$start_date = isset( $selected_options['start_date'] ) ? gmdate( 'Y-m-d', strtotime( $selected_options['start_date'] ) ) : '';

		$convert_to_minutes = function( $time ) {
			if ( $time ) {
				if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
					throw new Exception( 'Invalid time format. Expected HH:MM format.' );
				}
				$time_parts = explode( ':', $time );
				$hours      = (int) $time_parts[0];
				$minutes    = (int) $time_parts[1];
				return ( $hours * 60 ) + $minutes;
			}
			return null;
		};
		$start_time         = null;
		$end_time           = null;
		
		if ( isset( $selected_options['start_time'] ) ) {
			$start_time = $convert_to_minutes( $selected_options['start_time'] );
		}
		
		if ( isset( $selected_options['end_time'] ) ) {
			$end_time = $convert_to_minutes( $selected_options['end_time'] );
		}

		$agent_id   = isset( $selected_options['agent_id'] ) ? $selected_options['agent_id'] : null;
		$service_id = isset( $selected_options['service_id'] ) ? $selected_options['service_id'] : null;

		// The action has no dedicated "Select Location" field. location_id
		// previously fell back to $agent_id's own value, which silently wrote
		// an agent ID into a column that references a completely different
		// table, corrupting the booking's location on every create and
		// update. Resolve a real location instead: keep the existing
		// booking's location on update, or look up a location genuinely
		// connected to this agent/service pair via the same connector table
		// LatePoint itself uses for that relationship.
		if ( isset( $selected_options['location_id'] ) ) {
			$location_id = $selected_options['location_id'];
		} elseif ( $is_update && ! empty( $old_booking->location_id ) ) {
			$location_id = $old_booking->location_id;
		} else {
			global $wpdb;
			$location_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT location_id FROM {$wpdb->prefix}latepoint_agents_services WHERE agent_id = %d AND service_id = %d AND location_id IS NOT NULL LIMIT 1", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$agent_id,
					$service_id
				)
			);
		}

		$booking_params        = [
			'agent_id'         => $agent_id,
			'location_id'      => $location_id,
			'status'           => isset( $selected_options['status'] ) ? $selected_options['status'] : '',
			'total_attendees'  => isset( $selected_options['total_attendees'] ) ? $selected_options['total_attendees'] : 1,
			'service_id'       => $service_id,
			'start_date'       => $start_date,
			'start_time'       => $start_time,
			'end_time'         => $end_time,
			'customer_comment' => isset( $selected_options['customer_comment'] ) ? $selected_options['customer_comment'] : '',
			'payment_status'   => 'not_paid',
			'buffer_before'    => isset( $selected_options['buffer_before'] ) ? $selected_options['buffer_before'] : 0,
			'buffer_after'     => isset( $selected_options['buffer_after'] ) ? $selected_options['buffer_after'] : 0,
			'source_url'       => site_url(),
		];
		$booking_custom_fields = [];
		if ( ! empty( $selected_options['booking_fields'] ) ) {
			foreach ( $selected_options['booking_fields'] as $field ) {
				if ( is_array( $field ) && ! empty( $field ) ) {
					foreach ( $field as $key => $value ) {
						if ( false === strpos( $key, 'field_column' ) && '' !== $value ) {
							$booking_custom_fields[ $key ] = $value;
						} 
					}
				}
			}
		}

		
		$booking_params['custom_fields'] = $booking_custom_fields;

		$booking->set_data( $booking_params );

		// Set custom end time/date if it was passed in params.
		if ( isset( $booking_params['end_time']['formatted_value'] ) ) {
			$booking->set_custom_end_time_and_date( $booking_params );
		}

		if ( 'new' === $customer_type ) {
			$customer_params = [
				'first_name' => isset( $selected_options['customer_first_name'] ) ? $selected_options['customer_first_name'] : '',
				'last_name'  => isset( $selected_options['customer_last_name'] ) ? $selected_options['customer_last_name'] : '',
				'email'      => isset( $selected_options['customer_email'] ) ? $selected_options['customer_email'] : '',
				'phone'      => isset( $selected_options['customer_phone'] ) ? $selected_options['customer_phone'] : '',
				'notes'      => isset( $selected_options['customer_notes'] ) ? $selected_options['customer_notes'] : '',
			];

			$old_customer_data = [];
			$customer          = new OsCustomerModel();
			$customer          = $customer->where( [ 'email' => $customer_params['email'] ] )->set_limit( 1 )->get_results_as_models();

			if ( isset( $customer->id ) && ! empty( $customer->id ) ) {
				$is_new_customer   = false;
				$customer          = new OsCustomerModel( $customer->id );
				$old_customer_data = $customer->get_data_vars();
			} else {
				$is_new_customer = true;
				$customer        = new OsCustomerModel();
			}
			$customer_custom_fields = [];
			if ( ! empty( $selected_options['customer_fields'] ) ) {
				foreach ( $selected_options['customer_fields'] as $field ) {
					if ( is_array( $field ) && ! empty( $field ) ) {
						foreach ( $field as $key => $value ) {
							if ( false === strpos( $key, 'field_column' ) && '' !== $value ) {
								$customer_custom_fields[ $key ] = $value;
							}
						}
					}
				}
			}
			$customer_params['custom_fields'] = $customer_custom_fields;
			$customer->set_data( $customer_params );
			if ( ! $customer->save() ) {
				$errors    = $customer->get_error_messages();
				$error_msg = isset( $errors[0] ) ? $errors[0] : 'Customer could not be created.';
				throw new Exception( $error_msg );
			}

			if ( $is_new_customer ) {
				do_action( 'latepoint_customer_created', $customer );
			} else {
				do_action( 'latepoint_customer_updated', $customer, $old_customer_data );
			}
		} else {
			$customer = new OsCustomerModel( $customer_id );
			if ( ! $customer->id ) {
				throw new Exception( 'Customer not found.' );
			}
		}

		// On update, reuse the booking's existing order/order item instead of
		// always creating a new one, and skip LatePoint's price recalculation
		// entirely when nothing pricing-related actually changed (e.g. a
		// status-only update). Some LatePoint pricing add-ons (e.g. base-fee
		// pricing) can fatal during recalculation for bookings that don't
		// carry the pricing data they expect, so we should only pay that
		// cost when the update could actually affect price.
		$existing_order = null;
		if ( $is_update && ! empty( $booking->order_item_id ) ) {
			$existing_order_item = new OsOrderItemModel( $booking->order_item_id );
			if ( ! empty( $existing_order_item->id ) && ! empty( $existing_order_item->order_id ) ) {
				$existing_order = new OsOrderModel( $existing_order_item->order_id );
				if ( empty( $existing_order->id ) ) {
					$existing_order = null;
				}
			}
		}

		$requires_price_recalculation = ! $is_update || ! $existing_order
			|| (string) $old_booking->service_id !== (string) $booking->service_id
			|| (string) $old_booking->start_date !== (string) $booking->start_date
			|| (string) $old_booking->start_time !== (string) $booking->start_time
			|| (string) $old_booking->end_time !== (string) $booking->end_time
			|| (int) $old_booking->total_attendees !== (int) $booking->total_attendees;

		if ( $requires_price_recalculation ) {
			$order                     = new OsOrderModel();
			$order->status             = isset( $selected_options['status'] ) ? $selected_options['status'] : OsOrdersHelper::get_default_order_status();
			$order->fulfillment_status = $order->get_default_fulfillment_status();
			$order->customer_comment   = isset( $selected_options['customer_comment'] ) ? $selected_options['customer_comment'] : '';
			$order->customer_id        = $customer->id;
			$order->payment_status     = 'not_paid';

			// Save the order and check for errors.
			if ( ! $order->save() ) {
				$errors    = $order->get_error_messages();
				$error_msg = isset( $errors[0] ) ? $errors[0] : 'Order could not be created.';
				throw new Exception( $error_msg );
			}

			$order_item_model           = new OsOrderItemModel();
			$order_item_model->variant  = 'booking';
			$order_item_model->order_id = $order->id;

			if ( $order_item_model->save() ) {
				$booking->customer_id   = $order->customer_id;
				$booking->order_item_id = $order_item_model->id;
				if ( $booking->save() ) {
					$order_item_model->item_data = $booking->generate_item_data();

					// Some LatePoint pricing add-ons (e.g. base-fee pricing) type-hint a
					// non-nullable float on the 'latepoint_full_amount_for_service' filter
					// and fatal when the service has no charge_amount configured. That
					// only affects updates re-using an already-priced booking, so skip
					// recalculation for that specific, detectable case rather than
					// risking masking unrelated recalculation failures on new bookings.
					$service_has_no_charge_amount = false;
					if ( $is_update && class_exists( 'OsServiceModel' ) && ! empty( $booking->service_id ) ) {
						$service = new OsServiceModel( $booking->service_id );
						if ( ! empty( $service->id ) && null === $service->get_full_amount_for_duration( $booking->duration ) ) {
							$service_has_no_charge_amount = true;
							do_action( 'suretriggers_latepoint_price_recalculation_skipped', $booking, $service );
						}
					}

					if ( ! $service_has_no_charge_amount ) {
						$order_item_model->recalculate_prices();
					}
					$order->total    = $order_item_model->total;
					$order->subtotal = $order_item_model->subtotal;
					$order->save();
					$order_item_model->save();
				}
			} else {
				$errors    = $order_item_model->get_error_messages();
				$error_msg = isset( $errors[0] ) ? $errors[0] : 'Order Item could not be created.';
				throw new Exception( $error_msg );
			}
		} else {
			// Status-only (or similarly non-pricing) update: keep the booking
			// on its existing order and just sync the order status, without
			// touching pricing.
			$order                = $existing_order;
			$booking->customer_id = $customer->id;
			if ( isset( $selected_options['status'] ) ) {
				$order->status = $selected_options['status'];
				$order->save();
			}
		}

		$booking->set_utc_datetimes();

		if ( ! $booking->save() ) {
			$errors    = $booking->get_error_messages();
			$operation = $is_update ? 'updated' : 'created';
			$error_msg = isset( $errors[0] ) ? $errors[0] : 'Booking could not be ' . $operation . '.';
			throw new Exception( $error_msg );
		}


		if ( $is_update ) {
			do_action( 'latepoint_booking_updated', $booking, $old_booking );
		} else {

			do_action( 'latepoint_booking_created', $booking );
			do_action( 'latepoint_order_created', $order );
		}
		$return_data                    = $booking->get_data_vars();
		$return_data['service_id']      = ! empty( $booking->service_id ) ? $booking->service_id : ( ! empty( $booking_params['service_id'] ) ? $booking_params['service_id'] : null );
		$return_data['order']           = $order->get_data_vars();
		$return_data['total_attendees'] = $selected_options['total_attendees'];
		return $return_data;
	}

	/**
	 * Find object by email.
	 *
	 * @param array  $selected_options selected options.
	 * @param string $object model name.
	 * @return array
	 * @throws Exception Exception.
	 */
	public static function find_object_by_email( $selected_options, $object ) {

		if ( ! class_exists( 'OsAgentModel' ) || ! class_exists( 'OsCustomerModel' ) ) {
			throw new Exception( 'LatePoint plugin not installed.' );
		}

		$email = isset( $selected_options['email'] ) ? trim( $selected_options['email'] ) : '';

		if ( empty( $email ) ) {
			throw new Exception( $object . ' Email Address not provided.' );
		}

		$model = 'Agent' === $object ? new OsAgentModel() : new OsCustomerModel();
		$model = $model->where( [ 'email' => $email ] )->set_limit( 1 )->get_results( ARRAY_A );

		$model_data          = [];
		$model_data['found'] = 'no';
		if ( 'Customer' == $object ) {
			global $wpdb;
			$customer_fields = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,meta_value FROM {$wpdb->prefix}latepoint_customer_meta WHERE object_id= %s", intval( $model['id'] ) ) );
			if ( ! empty( $customer_fields ) ) {
				foreach ( $customer_fields as $field ) {
					$model[ $field->meta_key ] = $field->meta_value;
				}
			}       
		}
		

		if ( $model ) {
			unset( $model['password'] );
			$model_data          = $model;
			$model_data['found'] = 'yes';
		}

		return $model_data;
	}

	/**
	 * Find booking by customer email.
	 *
	 * @param array $selected_options selected options.
	 * @return array
	 * @throws Exception Exception.
	 */
	public static function find_booking_by_customer_email( $selected_options ) {

		if ( ! class_exists( 'OsBookingModel' ) || ! class_exists( 'OsCustomerModel' ) ) {
			throw new Exception( 'LatePoint plugin not installed.' );
		}

		$email = isset( $selected_options['email'] ) ? trim( $selected_options['email'] ) : '';

		if ( empty( $email ) ) {
			throw new Exception( 'Customer Email Address not provided.' );
		}

		$booking_data          = [];
		$booking_data['found'] = 'no';

		$customer_model = new OsCustomerModel();
		$customer       = $customer_model->where( [ 'email' => $email ] )->set_limit( 1 )->get_results( ARRAY_A );

		if ( empty( $customer['id'] ) ) {
			return $booking_data;
		}

		$booking_model = new OsBookingModel();
		$booking       = $booking_model->where( [ 'customer_id' => $customer['id'] ] )->order_by( 'id DESC' )->set_limit( 1 )->get_results( ARRAY_A );

		if ( ! empty( $booking['id'] ) ) {
			$booking = new OsBookingModel( $booking['id'] );

			$booking_data          = $booking->get_data_vars();
			$booking_data['found'] = 'yes';
		}

		return $booking_data;
	}

	/**
	 * Is Plugin depended on plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'LatePoint' );
	}
}

IntegrationsController::register( LatePoint::class );
