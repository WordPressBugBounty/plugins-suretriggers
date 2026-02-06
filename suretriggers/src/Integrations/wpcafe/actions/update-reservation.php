<?php
/**
 * UpdateReservation.
 * php version 5.6
 *
 * @category UpdateReservation
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
 * UpdateReservation
 *
 * @category UpdateReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateReservation extends AutomateAction {

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
	public $action = 'wpcafe_update_reservation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Reservation', 'suretriggers' ),
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
			$updated_fields = [];
			$status         = null;

			// Update status if provided.
			if ( isset( $selected_options['reservation_status'] ) && ! empty( $selected_options['reservation_status'] ) ) {
				$status      = sanitize_text_field( $selected_options['reservation_status'] );
				$update_post = wp_update_post(
					[
						'ID'          => $reservation_id,
						'post_status' => $status,
					],
					true
				);

				if ( is_wp_error( $update_post ) ) {
					return [
						'status'  => 'error',
						'message' => $update_post->get_error_message(),
					];
				}

				$updated_fields['reservation_status'] = $status;
			}

			// Get current date for timestamp conversion.
			$current_date = get_post_meta( $reservation_id, 'date', true );

			// Update name if provided.
			if ( isset( $selected_options['wpc_name'] ) && ! empty( $selected_options['wpc_name'] ) ) {
				$name = sanitize_text_field( $selected_options['wpc_name'] );
				update_post_meta( $reservation_id, 'name', $name );
				$updated_fields['name'] = $name;
			}

			// Update email if provided.
			if ( isset( $selected_options['wpc_email'] ) && ! empty( $selected_options['wpc_email'] ) ) {
				$email = sanitize_email( $selected_options['wpc_email'] );
				update_post_meta( $reservation_id, 'email', $email );
				$updated_fields['email'] = $email;
			}

			// Update phone if provided.
			if ( isset( $selected_options['wpc_phone'] ) && ! empty( $selected_options['wpc_phone'] ) ) {
				$phone = sanitize_text_field( $selected_options['wpc_phone'] );
				update_post_meta( $reservation_id, 'phone', $phone );
				$updated_fields['phone'] = $phone;
			}

			// Update party size if provided.
			if ( isset( $selected_options['wpc_party'] ) && ! empty( $selected_options['wpc_party'] ) ) {
				$party = absint( $selected_options['wpc_party'] );
				update_post_meta( $reservation_id, 'total_guest', $party );
				$updated_fields['total_guest'] = $party;
			}

			// Update date if provided.
			if ( isset( $selected_options['wpc_date'] ) && ! empty( $selected_options['wpc_date'] ) ) {
				$date = sanitize_text_field( $selected_options['wpc_date'] );
				update_post_meta( $reservation_id, 'date', $date );
				$updated_fields['date'] = $date;
				$current_date           = $date; // Update for timestamp conversion.
			}

			// Update start time if provided (convert to timestamp).
			if ( isset( $selected_options['wpc_time'] ) && ! empty( $selected_options['wpc_time'] ) ) {
				$time            = sanitize_text_field( $selected_options['wpc_time'] );
				$start_timestamp = strtotime( $current_date . ' ' . $time );
				update_post_meta( $reservation_id, 'start_time', $start_timestamp );
				$updated_fields['start_time'] = $start_timestamp;
			}

			// Update end time if provided (convert to timestamp).
			if ( isset( $selected_options['wpc_end_time'] ) && ! empty( $selected_options['wpc_end_time'] ) ) {
				$end_time      = sanitize_text_field( $selected_options['wpc_end_time'] );
				$end_timestamp = strtotime( $current_date . ' ' . $end_time );
				update_post_meta( $reservation_id, 'end_time', $end_timestamp );
				$updated_fields['end_time'] = $end_timestamp;
			}

			// Update message/notes if provided.
			if ( isset( $selected_options['wpc_message'] ) && ! empty( $selected_options['wpc_message'] ) ) {
				$message = sanitize_textarea_field( $selected_options['wpc_message'] );
				update_post_meta( $reservation_id, 'notes', $message );
				$updated_fields['notes'] = $message;
			}

			// Update branch if provided.
			if ( isset( $selected_options['wpc_branch'] ) && ! empty( $selected_options['wpc_branch'] ) ) {
				$branch = sanitize_text_field( $selected_options['wpc_branch'] );
				update_post_meta( $reservation_id, 'branch_id', $branch );
				$updated_fields['branch_id'] = $branch;

				// Get branch name from Location_Model.
				if ( class_exists( 'WpCafe\Models\Location_Model' ) ) {
					$location = \WpCafe\Models\Location_Model::find( $branch );
					if ( $location ) {
						update_post_meta( $reservation_id, 'branch_name', $location->restaurant_name );
						$updated_fields['branch_name'] = $location->restaurant_name;
					}
				}
			}

			// Update status in meta as well.
			if ( isset( $selected_options['reservation_status'] ) && ! empty( $selected_options['reservation_status'] ) ) {
				update_post_meta( $reservation_id, 'status', $status );
			}

			// Get current reservation data.
			$current_data = [
				'reservation_id'     => $reservation_id,
				'reservation_status' => get_post_status( $reservation_id ),
				'name'               => get_post_meta( $reservation_id, 'name', true ),
				'email'              => get_post_meta( $reservation_id, 'email', true ),
				'phone'              => get_post_meta( $reservation_id, 'phone', true ),
				'total_guest'        => get_post_meta( $reservation_id, 'total_guest', true ),
				'date'               => get_post_meta( $reservation_id, 'date', true ),
				'start_time'         => get_post_meta( $reservation_id, 'start_time', true ),
				'end_time'           => get_post_meta( $reservation_id, 'end_time', true ),
				'notes'              => get_post_meta( $reservation_id, 'notes', true ),
				'branch_id'          => get_post_meta( $reservation_id, 'branch_id', true ),
				'branch_name'        => get_post_meta( $reservation_id, 'branch_name', true ),
				'invoice'            => get_post_meta( $reservation_id, 'invoice', true ),
			];

			return array_merge(
				[
					'status'         => 'success',
					'message'        => __( 'Reservation updated successfully.', 'suretriggers' ),
					'updated_fields' => array_keys( $updated_fields ),
				],
				$current_data
			);

		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}
}

UpdateReservation::get_instance();
