<?php
/**
 * DeleteBooking.
 * php version 5.6
 *
 * @category DeleteBooking
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
 * DeleteBooking
 *
 * @category DeleteBooking
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeleteBooking extends AutomateAction {

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
	public $action = 'jet_delete_booking';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Booking', 'suretriggers' ),
			'action'   => 'jet_delete_booking',
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

		if ( empty( $booking_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Booking ID is required.', 'suretriggers' ),
			];
		}

		$booking = JetBooking::get_booking( $booking_id );

		if ( empty( $booking ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Booking not found with ID: %d', 'suretriggers' ), $booking_id ),
			];
		}

		Plugin::instance()->db->delete_booking( [ 'booking_id' => $booking_id ] );

		return [
			'success'      => true,
			'booking_id'   => $booking_id,
			'deleted_data' => $booking,
			'message'      => sprintf( __( 'Booking with ID %d has been successfully deleted.', 'suretriggers' ), $booking_id ),
		];
	}
}

DeleteBooking::get_instance();
