<?php
/**
 * UpdateReservationStatus.
 * php version 5.6
 *
 * @category UpdateReservationStatus
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
 * UpdateReservationStatus
 *
 * @category UpdateReservationStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateReservationStatus extends AutomateAction {

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
	public $action = 'hbook_update_reservation_status';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Reservation Status', 'suretriggers' ),
			'action'   => 'hbook_update_reservation_status',
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
		$status  = isset( $selected_options['status'] ) && is_string( $selected_options['status'] ) ? sanitize_key( $selected_options['status'] ) : '';

		if ( empty( $resa_id ) || '' === $status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation ID and status are required fields.', 'suretriggers' ),
			];
		}

		if ( ! in_array( $status, HBook::STATUSES, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: list of valid statuses */
					__( 'Invalid status. Valid statuses are: %s', 'suretriggers' ),
					implode( ', ', HBook::STATUSES )
				),
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

		// Pending and cancelled reservations do not hold their dates, so re-activating one must re-check availability (as HBook's admin does).
		$holding    = [ 'new', 'confirmed' ];
		$needs_hold = in_array( $status, $holding, true ) && ! in_array( $old_status, $holding, true );
		$resa       = $needs_hold ? $db->get_single( 'resa', $resa_id ) : false;
		$accom_id   = 0;
		$accom_num  = 0;
		$check_in   = '';
		$check_out  = '';

		if ( $needs_hold && is_array( $resa ) ) {
			$accom_id  = isset( $resa['accom_id'] ) && is_numeric( $resa['accom_id'] ) ? absint( $resa['accom_id'] ) : 0;
			$accom_num = isset( $resa['accom_num'] ) && is_numeric( $resa['accom_num'] ) ? absint( $resa['accom_num'] ) : 0;
			$check_in  = isset( $resa['check_in'] ) && is_string( $resa['check_in'] ) ? $resa['check_in'] : '';
			$check_out = isset( $resa['check_out'] ) && is_string( $resa['check_out'] ) ? $resa['check_out'] : '';

			if ( 0 === $accom_num ) {
				$free_num = $db->get_first_available_accom_num( $accom_id, $check_in, $check_out );
				if ( ! $free_num ) {
					return [
						'status'  => 'error',
						'message' => __( 'The accommodation is no longer available for the reservation dates.', 'suretriggers' ),
					];
				}
				$accom_num = absint( $free_num );
				$db->update_resa_accom( $resa_id, $accom_id, $accom_num );
			} elseif ( ! $db->is_available_accom_num( $accom_id, $accom_num, $check_in, $check_out ) ) {
				return [
					'status'  => 'error',
					'message' => __( 'The accommodation is no longer available for the reservation dates.', 'suretriggers' ),
				];
			}
		}

		if ( $old_status !== $status ) {
			$updated = $db->update_resa_status( $resa_id, $status );
			if ( ! $updated ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to update the reservation status.', 'suretriggers' ),
				];
			}

			if ( $needs_hold && is_array( $resa ) ) {
				$db->automatic_block_accom( $accom_id, $accom_num, $check_in, $check_out, $resa_id );
			}
		}

		$context               = HBook::get_reservation_context( $resa_id );
		$context['old_status'] = $old_status;
		$context['new_status'] = $status;

		return $context;
	}
}

UpdateReservationStatus::get_instance();
