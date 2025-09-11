<?php
/**
 * DeleteAppointment.
 * php version 5.6
 *
 * @category DeleteAppointment
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
 * DeleteAppointment
 *
 * @category DeleteAppointment
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeleteAppointment extends AutomateAction {

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
	public $action = 'jet_delete_appointment';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Appointment', 'suretriggers' ),
			'action'   => 'jet_delete_appointment',
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

		$delete_result = Plugin::instance()->db->appointments->delete( [ 'ID' => $appointment_id ] );
		Plugin::instance()->db->appointments_meta->delete( [ 'appointment_id' => $appointment_id ] );

		if ( false === $delete_result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to delete appointment.', 'suretriggers' ),
			];
		}

		return [
			'success'        => true,
			'appointment_id' => $appointment_id,
			'deleted_data'   => $appointment_data,
			'message'        => sprintf( 'Appointment with ID %d has been successfully deleted.', $appointment_id ),
		];
	}
}

DeleteAppointment::get_instance();
