<?php
/**
 * AppointmentDeleted.
 * php version 5.6
 *
 * @category AppointmentDeleted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;
use JET_APB\Plugin;

if ( ! class_exists( 'AppointmentDeleted' ) ) :

	/**
	 * AppointmentDeleted
	 *
	 * @category AppointmentDeleted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class AppointmentDeleted {

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
		public $trigger = 'jet_appointment_deleted';

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
				'label'         => __( 'Appointment Deleted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'jet-apb/db/delete/appointments',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];
			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param array $appointment_data Deleted appointment data.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $appointment_data ) {
			if ( empty( $appointment_data ) ) {
				return;
			}

			$appointment_id = isset( $appointment_data['ID'] ) ? $appointment_data['ID'] : 0;

			if ( ! $appointment_id ) {
				return;
			}

			$context = $appointment_data;

			if ( ! empty( $context['service'] ) ) {
				$service_post = get_post( $context['service'] );
				if ( $service_post ) {
					$context['service_title'] = $service_post->post_title;
				}
			}

			if ( ! empty( $context['provider'] ) && $context['provider'] > 0 ) {
				$provider_post = get_post( $context['provider'] );
				if ( $provider_post ) {
					$context['provider_title'] = $provider_post->post_title;
				}
			}

			if ( ! empty( $context['user_id'] ) ) {
				$user = get_user_by( 'ID', $context['user_id'] );
				if ( $user ) {
					$context['user_login']        = $user->user_login;
					$context['user_email']        = $user->user_email;
					$context['user_display_name'] = $user->display_name;
				}
			}

			$date_format                   = get_option( 'date_format' );
			$time_format                   = get_option( 'time_format' );
			$context['date_formatted']     = ! empty( $context['date'] ) && is_string( $date_format ) ? date_i18n( $date_format, $context['date'] ) : '';
			$context['slot_formatted']     = ! empty( $context['slot'] ) && is_string( $time_format ) ? date_i18n( $time_format, $context['slot'] ) : '';
			$context['slot_end_formatted'] = ! empty( $context['slot_end'] ) && is_string( $time_format ) ? date_i18n( $time_format, $context['slot_end'] ) : '';

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
	AppointmentDeleted::get_instance();

endif;
