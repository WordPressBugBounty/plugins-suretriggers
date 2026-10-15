<?php
/**
 * ReservationPaymentReceived.
 * php version 5.6
 *
 * @category ReservationPaymentReceived
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

if ( ! class_exists( 'ReservationPaymentReceived' ) ) :

	/**
	 * ReservationPaymentReceived
	 *
	 * @category ReservationPaymentReceived
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class ReservationPaymentReceived {

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
		public $trigger = 'hbook_reservation_payment_received';

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
				'label'         => __( 'Reservation Payment Received', 'suretriggers' ),
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
		 * @param string $type    What was updated.
		 * @param int    $resa_id Reservation ID.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $type, $resa_id ) {
			if ( ! in_array( $type, [ 'paid', 'payment' ], true ) || empty( $resa_id ) ) {
				return;
			}

			$context = HBook::get_reservation_context( absint( $resa_id ) );
			if ( empty( $context ) ) {
				return;
			}

			// Gateway ('payment') updates also fire for delayed/unpaid states; only report real money received.
			if ( 'payment' === $type && ( empty( $context['paid'] ) || (float) $context['paid'] <= 0 || ! empty( $context['payment_delayed'] ) ) ) {
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
	ReservationPaymentReceived::get_instance();

endif;
