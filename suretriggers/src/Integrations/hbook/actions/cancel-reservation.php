<?php
/**
 * CancelReservation.
 * php version 5.6
 *
 * @category CancelReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\HBook\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\HBook\HBook;

/**
 * CancelReservation
 *
 * @category CancelReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CancelReservation extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'HBook';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'hbook_cancel_reservation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Cancel Reservation', 'suretriggers' ),
			'action'   => 'hbook_cancel_reservation',
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
		if ( ! class_exists( '\HbDataBaseActions' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'HBook plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$db = new \HbDataBaseActions();

		$resa_id = isset( $selected_options['reservation_id'] ) ? absint( $selected_options['reservation_id'] ) : 0;
		$status  = 'cancelled';

		if ( empty( $resa_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation ID is required.', 'suretriggers' ),
			];
		}

		$old_status = $db->get_resa_status( $resa_id );
		if ( empty( $old_status ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %d: reservation ID */
					__( 'Reservation not found with ID: %d', 'suretriggers' ),
					$resa_id
				),
			];
		}

		if ( $old_status !== $status ) {
			$updated = $db->update_resa_status( $resa_id, $status );
			if ( ! $updated ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to update the reservation status.', 'suretriggers' ),
				];
			}
		}

		$context               = HBook::get_reservation_context( $resa_id );
		$context['old_status'] = $old_status;
		$context['new_status'] = $status;

		return $context;
	}
}

CancelReservation::get_instance();
