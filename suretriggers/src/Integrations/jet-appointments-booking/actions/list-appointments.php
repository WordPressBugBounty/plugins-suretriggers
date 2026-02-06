<?php
/**
 * ListAppointments.
 * php version 5.6
 *
 * @category ListAppointments
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\JetAppointmentsBooking\JetAppointmentsBooking;
use JET_APB\Plugin;

/**
 * ListAppointments
 *
 * @category ListAppointments
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class ListAppointments extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'JetAppointmentsBooking';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'jet_list_appointments';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'List Appointments', 'suretriggers' ),
			'action'   => 'jet_list_appointments',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|bool
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		
		if ( ! class_exists( '\JET_APB\Plugin' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Jet Appointments Booking plugin is not installed or active.', 'suretriggers' ),
			];
		}

		// Fetch all appointments without any filters or limits.
		try {
			$appointments = Plugin::instance()->db->appointments->query( [], 0 );

			if ( empty( $appointments ) ) {
				return [
					'success'      => true,
					'appointments' => [],
					'total_count'  => 0,
					'message'      => 'No appointments found matching the criteria.',
				];
			}

			$processed_appointments = [];

			foreach ( $appointments as $appointment ) {
				$appointment_meta = JetAppointmentsBooking::get_appointment_meta( $appointment['ID'] );
				$context          = array_merge( $appointment, $appointment_meta );

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

				$processed_appointments[] = $context;
			}

			return [
				'success'      => true,
				'appointments' => $processed_appointments,
				'total_count'  => count( $processed_appointments ),
			];
		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}


}

ListAppointments::get_instance();
