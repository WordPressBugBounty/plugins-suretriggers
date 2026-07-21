<?php
/**
 * BookingDeleted.
 * php version 5.6
 *
 * @category BookingDeleted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetBooking\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\JetBooking\JetBooking;

if ( ! class_exists( 'BookingDeleted' ) ) :

	/**
	 * BookingDeleted
	 *
	 * @category BookingDeleted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class BookingDeleted {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'JetBooking';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'jet_booking_deleted';

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
				'label'         => __( 'Booking Deleted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'jet-booking/db/before-booking-delete',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * Fires before the booking row is removed from the database, so the
		 * booking data is fetched here while it is still available.
		 *
		 * @param array $where Delete criteria.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $where ) {
			if ( empty( $where ) || ! is_array( $where ) || empty( $where['booking_id'] ) ) {
				return;
			}

			$booking = JetBooking::get_booking( $where['booking_id'] );

			if ( empty( $booking ) ) {
				return;
			}

			$context = JetBooking::get_booking_context( $booking );

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
	BookingDeleted::get_instance();

endif;
