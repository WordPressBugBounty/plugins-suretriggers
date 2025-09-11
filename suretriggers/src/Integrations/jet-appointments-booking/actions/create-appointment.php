<?php
/**
 * CreateAppointment.
 * php version 5.6
 *
 * @category CreateAppointment
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use JET_APB\Plugin;
use JET_APB\Resources\Appointment_Model;


/**
 * CreateAppointment
 *
 * @category CreateAppointment
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateAppointment extends AutomateAction {

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
	public $action = 'jet_create_appointment';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Appointment', 'suretriggers' ),
			'action'   => 'jet_create_appointment',
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
		
		if ( ! class_exists( '\JET_APB\Plugin' ) || ! class_exists( '\JET_APB\Resources\Appointment_Model' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Jet Appointments Booking plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$service_id       = isset( $selected_options['service_id'] ) ? absint( $selected_options['service_id'] ) : 0;
		$provider_id      = isset( $selected_options['provider_id'] ) ? absint( $selected_options['provider_id'] ) : 0;
		$user_name        = isset( $selected_options['user_name'] ) ? sanitize_text_field( $selected_options['user_name'] ) : '';
		$user_email       = isset( $selected_options['user_email'] ) ? sanitize_email( $selected_options['user_email'] ) : '';
		$appointment_date = isset( $selected_options['date'] ) ? sanitize_text_field( $selected_options['date'] ) : '';
		$slot             = isset( $selected_options['slot'] ) ? sanitize_text_field( $selected_options['slot'] ) : '';
		$slot_end         = isset( $selected_options['slot_end'] ) ? sanitize_text_field( $selected_options['slot_end'] ) : '';
		$status           = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : 'pending';

		if ( empty( $service_id ) || empty( $user_name ) || empty( $user_email ) || empty( $appointment_date ) || empty( $slot ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Required fields are missing: service_id, user_name, user_email, appointment_date, and slot are required.', 'suretriggers' ),
			];
		}

		if ( ! get_post( $service_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid service ID provided.', 'suretriggers' ),
			];
		}

		if ( $provider_id > 0 && ! get_post( $provider_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid provider ID provided.', 'suretriggers' ),
			];
		}

		$appointment_data = [
			'service'    => $service_id,
			'provider'   => $provider_id,
			'user_name'  => $user_name,
			'user_email' => $user_email,
			'date'       => strtotime( $appointment_date ),
			'slot'       => strtotime( $slot ),
			'slot_end'   => ! empty( $slot_end ) ? strtotime( $slot_end ) : strtotime( $slot ) + 3600,
			'status'     => $status,
			'type'       => 'single',
		];

		if ( ! empty( $user_id ) ) {
			$appointment_data['user_id'] = $user_id;
		}

		$appointment    = new \JET_APB\Resources\Appointment_Model( $appointment_data );
		$appointment_id = $appointment->save();

		if ( ! $appointment_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create appointment.', 'suretriggers' ),
			];
		}

		$response_data = [
			'appointment_id' => $appointment_id,
			'service_id'     => $service_id,
			'provider_id'    => $provider_id,
			'user_name'      => $user_name,
			'user_email'     => $user_email,
			'status'         => $status,
			'date'           => $appointment_date,
			'slot'           => $slot,
		];

		return $response_data;
	}
}

CreateAppointment::get_instance();
