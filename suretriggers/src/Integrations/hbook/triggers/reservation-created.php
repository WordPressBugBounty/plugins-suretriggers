<?php
/**
 * ReservationCreated.
 * php version 5.6
 *
 * @category ReservationCreated
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

if ( ! class_exists( 'ReservationCreated' ) ) :

	/**
	 * ReservationCreated
	 *
	 * @category ReservationCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class ReservationCreated {

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
		public $trigger = 'hbook_reservation_created';

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
				'label'         => __( 'Reservation Created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'hb_create_reservation',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array $resa_info Reservation data as inserted by HBook (no ID).
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $resa_info ) {
			if ( empty( $resa_info ) || ! is_array( $resa_info ) || empty( $resa_info['uid'] ) ) {
				return;
			}

			if ( ! class_exists( '\HbDataBaseActions' ) ) {
				return;
			}

			$db = new \HbDataBaseActions();

			// HBook fires this hook before exposing the new row's ID, so resolve it from the unique uid.
			$resa_id = $db->get_resa_id_by_uid( $resa_info['uid'] );
			if ( ! $resa_id ) {
				return;
			}

			$context = HBook::get_reservation_context( absint( $resa_id ) );
			if ( empty( $context ) ) {
				return;
			}

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
	ReservationCreated::get_instance();

endif;
