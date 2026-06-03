<?php
/**
 * AppointmentBooked.
 * php version 5.6
 *
 * @category AppointmentBooked
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\BookingPress\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\BookingPress\BookingPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'BookingPressAppointmentBooked' ) ) :

	/**
	 * BookingPressAppointmentBooked
	 *
	 * @category BookingPressAppointmentBooked
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class BookingPressAppointmentBooked {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'BookingPress';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'bookingpress_appointment_booked';

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
		 * Register trigger.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Appointment Booked', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'bookingpress_after_add_appointment_from_backend',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int   $inserted_booking_id Booking ID.
		 * @param array $appointment_data    Appointment data (may be empty at hook time).
		 * @param int   $entry_id            Entry ID.
		 * @return void
		 */
		public function trigger_listener( $inserted_booking_id, $appointment_data, $entry_id ) {
			if ( empty( $inserted_booking_id ) ) {
				return;
			}

			$context = BookingPress::get_appointment_context( $inserted_booking_id );

			if ( empty( $context ) ) {
				return;
			}

			$context['entry_id'] = $entry_id;

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
	BookingPressAppointmentBooked::get_instance();

endif;
