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

namespace SureTriggers\Integrations\Bookly\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

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
	public $integration = 'Bookly';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'bookly_update_appointment_status';

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
			'action'   => $this->action,
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
	 * @param array $selected_options selected_options.
	 *
	 * @return array|void
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		if ( ! class_exists( '\Bookly\Lib\Entities\CustomerAppointment' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Bookly is not installed or activated.', 'suretriggers' ),
			];
		}

		$appointment_id = isset( $selected_options['customer_appointment_id'] ) ? intval( $selected_options['customer_appointment_id'] ) : 0;
		$status         = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';

		if ( empty( $appointment_id ) || empty( $status ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Customer Appointment ID and Status are required.', 'suretriggers' ),
			];
		}

		$allowed_statuses = [
			\Bookly\Lib\Entities\CustomerAppointment::STATUS_PENDING,
			\Bookly\Lib\Entities\CustomerAppointment::STATUS_APPROVED,
			\Bookly\Lib\Entities\CustomerAppointment::STATUS_CANCELLED,
			\Bookly\Lib\Entities\CustomerAppointment::STATUS_REJECTED,
			\Bookly\Lib\Entities\CustomerAppointment::STATUS_WAITLISTED,
			\Bookly\Lib\Entities\CustomerAppointment::STATUS_DONE,
		];

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: comma-separated allowed status list */
					__( 'Invalid status. Allowed values are: %s.', 'suretriggers' ),
					implode( ', ', $allowed_statuses )
				),
			];
		}

		try {
			$ca = new \Bookly\Lib\Entities\CustomerAppointment();
			if ( ! $ca->load( $appointment_id ) ) {
				return [
					'status'  => 'error',
					'message' => sprintf(
						/* translators: %d: appointment id */
						__( 'Appointment not found with ID: %d', 'suretriggers' ),
						$appointment_id
					),
				];
			}

			$previous_status = $ca->getStatus();
			$ca->setStatus( $status );
			$ca->save();

			return [
				'success'         => true,
				'message'         => __( 'Appointment status updated successfully.', 'suretriggers' ),
				'appointment_id'  => $ca->getId(),
				'previous_status' => $previous_status,
				'new_status'      => $ca->getStatus(),
			];
		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to update appointment status: ', 'suretriggers' ) . $e->getMessage(),
			];
		}
	}
}

UpdateAppointmentStatus::get_instance();
