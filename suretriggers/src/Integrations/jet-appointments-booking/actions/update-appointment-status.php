<?php
/**
 * UpdateAppointmentStatus.
 * php version 5.6
 *
 * @category UpdateAppointmentStatus
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

/**
 * UpdateAppointmentStatus
 *
 * @category UpdateAppointmentStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateAppointmentStatus extends AutomateAction {

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
	public $action = 'jet_update_appointment_status';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Appointment Status', 'suretriggers' ),
			'action'   => 'jet_update_appointment_status',
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
		$status         = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';

		if ( empty( $appointment_id ) || empty( $status ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Appointment ID and status are required fields.', 'suretriggers' ),
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

		$valid_statuses = [ 'pending', 'processing', 'on-hold', 'cancelled', 'completed', 'refunded', 'failed' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid status. Valid statuses are: %s', 'suretriggers' ), implode( ', ', $valid_statuses ) ),
			];
		}

		$update_result = Plugin::instance()->db->appointments->update(
			[ 'status' => $status ],
			[ 'ID' => $appointment_id ]
		);

		if ( false === $update_result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to update appointment status.', 'suretriggers' ),
			];
		}

		$updated_appointment = Plugin::instance()->db->appointments->query( [ 'ID' => $appointment_id ], 1 );
		$updated_appointment = ! empty( $updated_appointment ) ? $updated_appointment[0] : [];

		return [
			'appointment_id' => $appointment_id,
			'old_status'     => $appointment_data['status'],
			'new_status'     => $status,
			'updated_data'   => $updated_appointment,
		];
	}
}

UpdateAppointmentStatus::get_instance();

