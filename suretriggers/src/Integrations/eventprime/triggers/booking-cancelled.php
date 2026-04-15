<?php
/**
 * BookingCancelled.
 * php version 5.6
 *
 * @category BookingCancelled
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventPrime\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\EventPrime\EventPrime;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'BookingCancelled' ) ) :

	/**
	 * BookingCancelled
	 *
	 * @category BookingCancelled
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class BookingCancelled {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'EventPrime';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'ep_event_booking_cancelled';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
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
				'label'         => __( 'Booking Cancelled', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'ep_booking_cancelled',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $booking Booking object from EventPrime.
		 *
		 * @return void
		 */
		public function trigger_listener( $booking ) {
			if ( ! is_object( $booking ) || empty( $booking->em_id ) ) {
				return;
			}

			$booking_id = (int) $booking->em_id;

			$booking_context = EventPrime::get_booking_context( $booking_id );
			if ( empty( $booking_context ) ) {
				return;
			}

			$event_id = is_numeric( $booking_context['event_id'] ) ? (int) $booking_context['event_id'] : 0;
			$user_id  = is_numeric( $booking_context['user_id'] ) ? (int) $booking_context['user_id'] : 0;

			$context = $booking_context;
			if ( ! empty( $event_id ) ) {
				$context = array_merge( $context, EventPrime::get_event_context( $event_id ) );
			}
			if ( ! empty( $user_id ) ) {
				$context = array_merge( WordPress::get_user_context( $user_id ), $context );
			}
			$context['event_id'] = $event_id;
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
	BookingCancelled::get_instance();

endif;
