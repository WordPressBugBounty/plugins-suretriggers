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

namespace SureTriggers\Integrations\JetAppointmentsBooking\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\JetAppointmentsBooking\JetAppointmentsBooking;
use JET_APB\Plugin;

if ( ! class_exists( 'AppointmentStatusChanged' ) ) :

	/**
	 * AppointmentStatusChanged
	 *
	 * @category AppointmentStatusChanged
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class AppointmentStatusChanged {

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
		public $trigger = 'jet_appointment_status_changed';

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
				'label'         => __( 'Appointment Status Changed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'jet-apb/db/update/appointments',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array        $new_data New appointment data.
		 * @param array|string $where Update criteria.
		 * @param array        $old_data Previous appointment data.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $new_data, $where, $old_data ) {
			if ( empty( $new_data ) || empty( $old_data ) ) {
				return;
			}

			$old_status = isset( $old_data['status'] ) ? $old_data['status'] : '';
			$new_status = isset( $new_data['status'] ) ? $new_data['status'] : '';

			if ( $old_status === $new_status ) {
				return;
			}

			$appointment_id = isset( $new_data['ID'] ) ? $new_data['ID'] : ( isset( $where['ID'] ) ? $where['ID'] : null );

			if ( ! $appointment_id ) {
				return;
			}

			$context           = JetAppointmentsBooking::get_appointment_context( $appointment_id, $new_status, $old_status );
			$context['status'] = $new_status;

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
	AppointmentStatusChanged::get_instance();

endif;
