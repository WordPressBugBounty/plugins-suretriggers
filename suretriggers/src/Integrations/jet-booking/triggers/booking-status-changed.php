<?php
/**
 * BookingStatusChanged.
 * php version 5.6
 *
 * @category BookingStatusChanged
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

if ( ! class_exists( 'BookingStatusChanged' ) ) :

	/**
	 * BookingStatusChanged
	 *
	 * @category BookingStatusChanged
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class BookingStatusChanged {

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
		public $trigger = 'jet_booking_status_changed';

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
				'label'         => __( 'Booking Status Changed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'jet-booking/db/update/bookings',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array        $new_data New booking data.
		 * @param array|string $where    Update criteria.
		 * @param array        $old_data Previous booking data.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $new_data, $where, $old_data ) {
			if ( empty( $new_data ) || empty( $old_data ) || ! is_array( $new_data ) || ! is_array( $old_data ) ) {
				return;
			}

			$old_status = isset( $old_data['status'] ) ? $old_data['status'] : '';
			$new_status = isset( $new_data['status'] ) ? $new_data['status'] : '';

			if ( $old_status === $new_status ) {
				return;
			}

			$booking_id = isset( $new_data['booking_id'] ) ? $new_data['booking_id'] : ( isset( $where['booking_id'] ) ? $where['booking_id'] : null );

			if ( ! $booking_id ) {
				return;
			}

			$context = JetBooking::get_booking_context( $new_data, $new_status, $old_status );

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
	BookingStatusChanged::get_instance();

endif;
