<?php
/**
 * DeleteReservation.
 * php version 5.6
 *
 * @category DeleteReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPCafe\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * DeleteReservation
 *
 * @category DeleteReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeleteReservation extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WPCafe';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wpcafe_delete_reservation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Reservation', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id         User ID.
	 * @param int   $automation_id   Automation ID.
	 * @param array $fields          Fields.
	 * @param array $selected_options Selected options.
	 *
	 * @return array|void
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		// Get reservation ID.
		$reservation_id = isset( $selected_options['reservation_id'] ) ? absint( $selected_options['reservation_id'] ) : 0;
		$force_delete   = isset( $selected_options['force_delete'] ) && 'true' === $selected_options['force_delete'];

		// Validate reservation ID.
		if ( empty( $reservation_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation ID is required.', 'suretriggers' ),
			];
		}

		// Check if reservation exists.
		$reservation = get_post( $reservation_id );

		if ( ! $reservation || 'wpc_reservation' !== $reservation->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation not found.', 'suretriggers' ),
			];
		}

		try {
			// Store reservation data before deletion.
			$reservation_data = [
				'reservation_id'     => $reservation_id,
				'reservation_status' => $reservation->post_status,
				'wpc_name'           => get_post_meta( $reservation_id, 'wpc_name', true ),
				'wpc_email'          => get_post_meta( $reservation_id, 'wpc_email', true ),
				'wpc_phone'          => get_post_meta( $reservation_id, 'wpc_phone', true ),
				'wpc_party'          => get_post_meta( $reservation_id, 'wpc_party', true ),
				'wpc_date'           => get_post_meta( $reservation_id, 'wpc_date', true ),
				'wpc_time'           => get_post_meta( $reservation_id, 'wpc_from_time', true ),
				'wpc_end_time'       => get_post_meta( $reservation_id, 'wpc_to_time', true ),
				'wpc_message'        => get_post_meta( $reservation_id, 'wpc_message', true ),
				'wpc_branch'         => get_post_meta( $reservation_id, 'wpc_branch', true ),
			];

			// Delete the reservation.
			$deleted = wp_delete_post( $reservation_id, $force_delete );

			if ( ! $deleted ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to delete reservation.', 'suretriggers' ),
				];
			}

			return array_merge(
				[
					'status'       => 'success',
					'message'      => $force_delete
						? __( 'Reservation permanently deleted.', 'suretriggers' )
						: __( 'Reservation moved to trash.', 'suretriggers' ),
					'force_delete' => $force_delete,
				],
				$reservation_data
			);

		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}
}

DeleteReservation::get_instance();
