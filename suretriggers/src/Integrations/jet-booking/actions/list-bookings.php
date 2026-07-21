<?php
/**
 * ListBookings.
 * php version 5.6
 *
 * @category ListBookings
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
 * ListBookings
 *
 * @category ListBookings
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class ListBookings extends AutomateAction {

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
	public $action = 'jet_list_bookings';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'List Bookings', 'suretriggers' ),
			'action'   => 'jet_list_bookings',
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

		$apartment_id = isset( $selected_options['apartment_id'] ) ? absint( $selected_options['apartment_id'] ) : 0;
		$status       = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';

		$args = [];

		if ( ! empty( $apartment_id ) ) {
			$args['apartment_id'] = $apartment_id;
		}

		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		try {
			$bookings = Plugin::instance()->db->query( $args );

			if ( empty( $bookings ) ) {
				return [
					'success'     => true,
					'bookings'    => [],
					'total_count' => 0,
					'message'     => __( 'No bookings found matching the criteria.', 'suretriggers' ),
				];
			}

			$processed_bookings = [];

			foreach ( $bookings as $booking ) {
				$processed_bookings[] = JetBooking::get_booking_context( $booking );
			}

			return [
				'success'     => true,
				'bookings'    => $processed_bookings,
				'total_count' => count( $processed_bookings ),
			];
		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}
}

ListBookings::get_instance();
