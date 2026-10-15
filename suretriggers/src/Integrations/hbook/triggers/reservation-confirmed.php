<?php
/**
 * ReservationConfirmed.
 * php version 5.6
 *
 * @category ReservationConfirmed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\HBook\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\HBook\HBook;

if ( ! class_exists( 'ReservationConfirmed' ) ) :

	/**
	 * ReservationConfirmed
	 *
	 * @category ReservationConfirmed
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class ReservationConfirmed {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'HBook';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'hbook_reservation_confirmed';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Reservation Confirmed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'hb_reservation_updated',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * HBook reports status changes either as a 'status' update (admin actions) or as a
		 * 'payment' update (the payment gateway sets the status while recording the payment).
		 *
		 * @param string $type    What was updated.
		 * @param int    $resa_id Reservation ID.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $type, $resa_id ) {
			if ( ! in_array( $type, [ 'status', 'payment' ], true ) || empty( $resa_id ) ) {
				return;
			}

			$transition = HBook::get_status_transition( absint( $resa_id ) );
			if ( '' === $transition['status'] ) {
				return;
			}

			// A payment update that did not change the status is not a status change.
			if ( 'payment' === $type && $transition['status'] === $transition['previous_status'] ) {
				return;
			}

			if ( 'confirmed' !== $transition['status'] ) {
				return;
			}

			$context = HBook::get_reservation_context( absint( $resa_id ) );
			if ( empty( $context ) ) {
				return;
			}

			$context['old_status'] = $transition['previous_status'];
			$context['new_status'] = $transition['status'];

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}

	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	ReservationConfirmed::get_instance();

endif;
