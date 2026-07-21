<?php
/**
 * GetBookingDetails.
 * php version 5.6
 *
 * @category GetBookingDetails
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

/**
 * GetBookingDetails
 *
 * @category GetBookingDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetBookingDetails extends AutomateAction {

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
	public $action = 'jet_get_booking_details';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Booking Details', 'suretriggers' ),
			'action'   => 'jet_get_booking_details',
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

		return JetBooking::get_booking_context( $booking );
	}
}

GetBookingDetails::get_instance();
