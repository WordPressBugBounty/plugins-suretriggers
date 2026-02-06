<?php
/**
 * CreateReservation.
 * php version 5.6
 *
 * @category CreateReservation
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
 * CreateReservation
 *
 * @category CreateReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateReservation extends AutomateAction {

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
	public $action = 'wpcafe_create_reservation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Reservation', 'suretriggers' ),
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
		// Get reservation data from selected options.
		$name     = isset( $selected_options['wpc_name'] ) ? sanitize_text_field( $selected_options['wpc_name'] ) : '';
		$email    = isset( $selected_options['wpc_email'] ) ? sanitize_email( $selected_options['wpc_email'] ) : '';
		$phone    = isset( $selected_options['wpc_phone'] ) ? sanitize_text_field( $selected_options['wpc_phone'] ) : '';
		$party    = isset( $selected_options['wpc_party'] ) ? absint( $selected_options['wpc_party'] ) : 1;
		$date     = isset( $selected_options['wpc_date'] ) ? sanitize_text_field( $selected_options['wpc_date'] ) : '';
		$time     = isset( $selected_options['wpc_time'] ) ? sanitize_text_field( $selected_options['wpc_time'] ) : '';
		$end_time = isset( $selected_options['wpc_end_time'] ) ? sanitize_text_field( $selected_options['wpc_end_time'] ) : '';
		$message  = isset( $selected_options['wpc_message'] ) ? sanitize_textarea_field( $selected_options['wpc_message'] ) : '';
		$branch   = isset( $selected_options['wpc_branch'] ) ? sanitize_text_field( $selected_options['wpc_branch'] ) : '';
		$status   = isset( $selected_options['reservation_status'] ) ? sanitize_text_field( $selected_options['reservation_status'] ) : 'pending';

		// Validate required fields.
		if ( empty( $name ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Name is required.', 'suretriggers' ),
			];
		}

		if ( empty( $email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Email is required.', 'suretriggers' ),
			];
		}

		if ( empty( $date ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation date is required.', 'suretriggers' ),
			];
		}

		if ( empty( $time ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation time is required.', 'suretriggers' ),
			];
		}

		try {
			// Create reservation post.
			$reservation_data = [
				'post_type'   => 'wpc_reservation',
				'post_status' => $status,
				'post_title'  => sprintf(
					/* translators: %1$s: Name, %2$s: Date, %3$s: Time */
					__( 'Reservation - %1$s - %2$s %3$s', 'suretriggers' ),
					$name,
					$date,
					$time
				),
			];

			$reservation_id = wp_insert_post( $reservation_data, true );

			if ( is_wp_error( $reservation_id ) ) {
				return [
					'status'  => 'error',
					'message' => $reservation_id->get_error_message(),
				];
			}

			// Convert time to Unix timestamp (WPCafe stores times as timestamps).
			$start_timestamp = strtotime( $date . ' ' . $time );
			$end_timestamp   = ! empty( $end_time ) ? strtotime( $date . ' ' . $end_time ) : $start_timestamp + 3600; // Default 1 hour duration.

			// Add reservation meta data using WPCafe's meta keys.
			update_post_meta( $reservation_id, 'name', $name );
			update_post_meta( $reservation_id, 'email', $email );
			update_post_meta( $reservation_id, 'phone', $phone );
			update_post_meta( $reservation_id, 'total_guest', $party );
			update_post_meta( $reservation_id, 'date', $date );
			update_post_meta( $reservation_id, 'start_time', $start_timestamp );
			update_post_meta( $reservation_id, 'end_time', $end_timestamp );
			update_post_meta( $reservation_id, 'status', $status );
			update_post_meta( $reservation_id, 'invoice', 'WPC' . wp_rand( 1000, 9999 ) );

			if ( ! empty( $message ) ) {
				update_post_meta( $reservation_id, 'notes', $message );
			}

			if ( ! empty( $branch ) ) {
				update_post_meta( $reservation_id, 'branch_id', $branch );
				// Get branch name from Location_Model.
				if ( class_exists( 'WpCafe\Models\Location_Model' ) ) {
					$location = \WpCafe\Models\Location_Model::find( $branch );
					if ( $location ) {
						update_post_meta( $reservation_id, 'branch_name', $location->restaurant_name );
					}
				}
			}

			return [
				'status'             => 'success',
				'message'            => __( 'Reservation created successfully.', 'suretriggers' ),
				'reservation_id'     => $reservation_id,
				'reservation_status' => $status,
				'wpc_name'           => $name,
				'wpc_email'          => $email,
				'wpc_phone'          => $phone,
				'wpc_party'          => $party,
				'wpc_date'           => $date,
				'wpc_time'           => $time,
				'wpc_end_time'       => $end_time,
				'wpc_message'        => $message,
				'wpc_branch'         => $branch,
			];

		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}
}

CreateReservation::get_instance();
