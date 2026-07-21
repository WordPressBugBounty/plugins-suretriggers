<?php
/**
 * CreateBooking.
 * php version 5.6
 *
 * @category CreateBooking
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetBooking\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use JET_ABAF\Plugin;

/**
 * CreateBooking
 *
 * @category CreateBooking
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateBooking extends AutomateAction {

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
	public $action = 'jet_create_booking';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Booking', 'suretriggers' ),
			'action'   => 'jet_create_booking',
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

		$apartment_id   = isset( $selected_options['apartment_id'] ) ? absint( $selected_options['apartment_id'] ) : 0;
		$apartment_unit = isset( $selected_options['apartment_unit'] ) ? absint( $selected_options['apartment_unit'] ) : 0;
		$check_in_date  = isset( $selected_options['check_in_date'] ) ? sanitize_text_field( $selected_options['check_in_date'] ) : '';
		$check_out_date = isset( $selected_options['check_out_date'] ) ? sanitize_text_field( $selected_options['check_out_date'] ) : '';
		$user_email     = isset( $selected_options['user_email'] ) ? sanitize_email( $selected_options['user_email'] ) : '';
		$status         = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : 'pending';

		if ( empty( $apartment_id ) || empty( $check_in_date ) || empty( $check_out_date ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Required fields are missing: apartment_id, check_in_date, and check_out_date are required.', 'suretriggers' ),
			];
		}

		if ( ! get_post( $apartment_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid apartment ID provided.', 'suretriggers' ),
			];
		}

		$valid_statuses = Plugin::instance()->statuses->get_statuses_ids();
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid status. Valid statuses are: %s', 'suretriggers' ), implode( ', ', $valid_statuses ) ),
			];
		}

		$booking_data = [
			'apartment_id'   => $apartment_id,
			'check_in_date'  => strtotime( $check_in_date ),
			'check_out_date' => strtotime( $check_out_date ),
			'status'         => $status,
		];

		if ( ! empty( $apartment_unit ) ) {
			$booking_data['apartment_unit'] = $apartment_unit;
		}

		if ( ! empty( $user_email ) ) {
			$booking_data['user_email'] = $user_email;
		}

		if ( ! empty( $user_id ) ) {
			$booking_data['user_id'] = $user_id;
		}

		$booking_id = Plugin::instance()->db->insert_booking( $booking_data );

		if ( ! $booking_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create booking. The selected dates might not be available.', 'suretriggers' ),
			];
		}

		return [
			'booking_id'     => $booking_id,
			'apartment_id'   => $apartment_id,
			'check_in_date'  => $check_in_date,
			'check_out_date' => $check_out_date,
			'status'         => $status,
		];
	}
}

CreateBooking::get_instance();
