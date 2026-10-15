<?php
/**
 * GetReservation.
 * php version 5.6
 *
 * @category GetReservation
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
 * GetReservation
 *
 * @category GetReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetReservation extends AutomateAction {

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
	public $action = 'hbook_get_reservation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Reservation Details', 'suretriggers' ),
			'action'   => 'hbook_get_reservation',
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
		if ( empty( $resa_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Reservation ID is required.', 'suretriggers' ),
			];
		}

		$context = HBook::get_reservation_context( $resa_id );
		if ( empty( $context ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %d: reservation ID */
					__( 'Reservation not found with ID: %d', 'suretriggers' ),
					$resa_id
				),
			];
		}

		return $context;
	}
}

GetReservation::get_instance();
