<?php
/**
 * CancelBooking.
 * php version 5.6
 *
 * @category CancelBooking
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventPrime\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\EventPrime\EventPrime;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * CancelBooking
 *
 * @category CancelBooking
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CancelBooking extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'EventPrime';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'ep_cancel_booking';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Cancel a Booking', 'suretriggers' ),
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
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|mixed
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		$booking_id = isset( $selected_options['booking_id'] ) ? absint( $selected_options['booking_id'] ) : 0;

		if ( empty( $booking_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Booking ID is required.', 'suretriggers' ),
			];
		}

		$booking_post = get_post( $booking_id );
		if ( ! $booking_post || 'em_booking' !== $booking_post->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid booking ID.', 'suretriggers' ),
			];
		}

		$current_status = get_post_meta( $booking_id, 'em_status', true );
		if ( 'cancelled' === $current_status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Booking is already cancelled.', 'suretriggers' ),
			];
		}

		if ( ! class_exists( 'EventPrime_Bookings' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'EventPrime_Bookings class not found.', 'suretriggers' ),
			];
		}

		$ep_bookings = new \EventPrime_Bookings();
		$ep_bookings->update_status( $booking_id, 'cancelled' );

		// Verify the status was actually updated.
		$updated_status = get_post_meta( $booking_id, 'em_status', true );
		if ( 'cancelled' !== $updated_status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to cancel the booking.', 'suretriggers' ),
			];
		}

		$booking_context = EventPrime::get_booking_context( $booking_id );

		if ( empty( $booking_context ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Booking was cancelled but context could not be retrieved.', 'suretriggers' ),
			];
		}

		$event_id        = is_numeric( $booking_context['event_id'] ) ? (int) $booking_context['event_id'] : 0;
		$booking_user_id = is_numeric( $booking_context['user_id'] ) ? (int) $booking_context['user_id'] : 0;

		$context = $booking_context;
		if ( ! empty( $event_id ) ) {
			$context = array_merge( $context, EventPrime::get_event_context( $event_id ) );
		}
		if ( ! empty( $booking_user_id ) ) {
			$context = array_merge( WordPress::get_user_context( $booking_user_id ), $context );
		}

		return $context;
	}
}

CancelBooking::get_instance();
