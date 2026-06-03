<?php
/**
 * AppointmentStatusChanged.
 * php version 5.6
 *
 * @category AppointmentStatusChanged
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

if ( ! class_exists( 'BookingPressAppointmentStatusChanged' ) ) :

	/**
	 * BookingPressAppointmentStatusChanged
	 *
	 * Fires on bookingpress_after_change_appointment_status.
	 * Status codes: 1=Approved, 2=Pending, 3=Canceled, 4=Rejected/Refunded.
	 *
	 * @category BookingPressAppointmentStatusChanged
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class BookingPressAppointmentStatusChanged {

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
		public $trigger = 'bookingpress_appointment_status_changed';

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
				'label'         => __( 'Appointment Status Changed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'bookingpress_after_change_appointment_status',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int        $appointment_id Booking ID from bookingpress_appointment_bookings.
		 * @param string|int $new_status     New status code (1=Approved, 2=Pending, 3=Canceled, 4=Rejected).
		 * @return void
		 */
		public function trigger_listener( $appointment_id, $new_status ) {
			if ( empty( $appointment_id ) ) {
				return;
			}

			$context = BookingPress::get_appointment_context( $appointment_id );

			if ( empty( $context ) ) {
				return;
			}

			$context['new_status'] = (string) $new_status;

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
	BookingPressAppointmentStatusChanged::get_instance();

endif;
