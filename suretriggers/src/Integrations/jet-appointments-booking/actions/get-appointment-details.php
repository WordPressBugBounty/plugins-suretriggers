<?php
/**
 * GetAppointmentDetails.
 * php version 5.6
 *
 * @category GetAppointmentDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\JetAppointmentsBooking\JetAppointmentsBooking;
use JET_APB\Plugin;

/**
 * GetAppointmentDetails
 *
 * @category GetAppointmentDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetAppointmentDetails extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'JetAppointmentsBooking';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'jet_get_appointment_details';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Appointment Details', 'suretriggers' ),
			'action'   => 'jet_get_appointment_details',
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
	 * @return array|bool
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( '\JET_APB\Plugin' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Jet Appointments Booking plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$appointment_id = isset( $selected_options['appointment_id'] ) ? absint( $selected_options['appointment_id'] ) : 0;

		if ( empty( $appointment_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Appointment ID is required.', 'suretriggers' ),
			];
		}

		$appointment_data = Plugin::instance()->db->appointments->query( [ 'ID' => $appointment_id ], 1 );
		if ( empty( $appointment_data ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Appointment not found with ID: %d', 'suretriggers' ), $appointment_id ),
			];
		}
		$appointment_data = $appointment_data[0];

		$appointment_meta = JetAppointmentsBooking::get_appointment_meta( $appointment_id );

		$context = array_merge( $appointment_data, $appointment_meta );

		if ( ! empty( $context['service'] ) ) {
			$service_post = get_post( $context['service'] );
			if ( $service_post ) {
				$context['service_title']   = $service_post->post_title;
				$context['service_content'] = $service_post->post_content;
			}
		}

		if ( ! empty( $context['provider'] ) && $context['provider'] > 0 ) {
			$provider_post = get_post( $context['provider'] );
			if ( $provider_post ) {
				$context['provider_title']   = $provider_post->post_title;
				$context['provider_content'] = $provider_post->post_content;
			}
		}

		if ( ! empty( $context['user_id'] ) ) {
			$user = get_user_by( 'ID', $context['user_id'] );
			if ( $user ) {
				$context['user_login']        = $user->user_login;
				$context['user_email']        = $user->user_email;
				$context['user_display_name'] = $user->display_name;
				$context['user_nicename']     = $user->user_nicename;
			}
		}

		if ( ! empty( $context['order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $context['order_id'] );
			if ( $order && is_object( $order ) && method_exists( $order, 'get_status' ) ) {
				$context['order_status']   = $order->get_status();
				$context['order_total']    = $order->get_total();
				$context['order_currency'] = $order->get_currency();
			}
		}

		$date_format                           = get_option( 'date_format' );
		$time_format                           = get_option( 'time_format' );
		$datetime_format                       = get_option( 'datetime_format' );
		$context['date_formatted']             = ! empty( $context['date'] ) && is_string( $date_format ) ? date_i18n( $date_format, $context['date'] ) : '';
		$context['slot_formatted']             = ! empty( $context['slot'] ) && is_string( $time_format ) ? date_i18n( $time_format, $context['slot'] ) : '';
		$context['slot_end_formatted']         = ! empty( $context['slot_end'] ) && is_string( $time_format ) ? date_i18n( $time_format, $context['slot_end'] ) : '';
		$context['appointment_date_formatted'] = ! empty( $context['appointment_date'] ) && is_string( $datetime_format ) ? date_i18n( $datetime_format, strtotime( $context['appointment_date'] ) ) : '';

		return $context;
	}


}

GetAppointmentDetails::get_instance();
