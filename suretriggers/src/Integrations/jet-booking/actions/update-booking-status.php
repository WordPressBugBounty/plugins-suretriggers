<?php
/**
 * UpdateBookingStatus.
 * php version 5.6
 *
 * @category UpdateBookingStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetBooking\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\JetBooking\JetBooking;
use JET_ABAF\Plugin;

/**
 * UpdateBookingStatus
 *
 * @category UpdateBookingStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateBookingStatus extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'JetBooking';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'jet_update_booking_status';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Booking Status', 'suretriggers' ),
			'action'   => 'jet_update_booking_status',
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

		if ( ! class_exists( '\JET_ABAF\Plugin' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'JetBooking plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$booking_id = isset( $selected_options['booking_id'] ) ? absint( $selected_options['booking_id'] ) : 0;
		$status     = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';

		if ( empty( $booking_id ) || empty( $status ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Booking ID and status are required fields.', 'suretriggers' ),
			];
		}

		$booking = JetBooking::get_booking( $booking_id );

		if ( empty( $booking ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Booking not found with ID: %d', 'suretriggers' ), $booking_id ),
			];
		}

		$valid_statuses = Plugin::instance()->statuses->get_statuses_ids();
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid status. Valid statuses are: %s', 'suretriggers' ), implode( ', ', $valid_statuses ) ),
			];
		}

		$old_status = isset( $booking['status'] ) ? $booking['status'] : '';

		Plugin::instance()->db->update_booking( $booking_id, [ 'status' => $status ] );

		$updated_booking = JetBooking::get_booking( $booking_id );

		return [
			'booking_id'   => $booking_id,
			'old_status'   => $old_status,
			'new_status'   => $status,
			'updated_data' => $updated_booking,
		];
	}
}

UpdateBookingStatus::get_instance();
