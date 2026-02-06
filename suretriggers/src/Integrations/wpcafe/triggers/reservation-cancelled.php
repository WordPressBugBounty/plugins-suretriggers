<?php
/**
 * ReservationCancelled.
 * php version 5.6
 *
 * @category ReservationCancelled
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPCafe\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'ReservationCancelled' ) ) :

	/**
	 * ReservationCancelled
	 *
	 * @category ReservationCancelled
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class ReservationCancelled {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'WPCafe';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wpcafe_reservation_cancelled';

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
				'label'         => __( 'Reservation Cancelled', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wpcafe_after_reservation_cancelled',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int $reservation Reservation.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $reservation ) {
			if ( empty( $reservation ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $reservation,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	ReservationCancelled::get_instance();

endif;
