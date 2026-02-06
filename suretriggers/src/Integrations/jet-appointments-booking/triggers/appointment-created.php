<?php
/**
 * AppointmentCreated.
 * php version 5.6
 *
 * @category AppointmentCreated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\JetAppointmentsBooking\JetAppointmentsBooking;
use JET_APB\Plugin;

if ( ! class_exists( 'AppointmentCreated' ) ) :

	/**
	 * AppointmentCreated
	 *
	 * @category AppointmentCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class AppointmentCreated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'JetAppointmentsBooking';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'jet_appointment_created';

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
				'label'         => __( 'Appointment Created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'jet-apb/db/create/appointments',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array  $appointment_data Appointment data.
		 * @param object $appointment_model Appointment model instance.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $appointment_data, $appointment_model ) {
			if ( empty( $appointment_data ) || ! is_object( $appointment_model ) ) {
				return;
			}

			$appointment_id = is_object( $appointment_model ) && method_exists( $appointment_model, 'get' ) ? $appointment_model->get( 'ID' ) : null;

			if ( ! $appointment_id ) {
				return;
			}

			$context = JetAppointmentsBooking::get_appointment_context( $appointment_id );

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
	AppointmentCreated::get_instance();

endif;
