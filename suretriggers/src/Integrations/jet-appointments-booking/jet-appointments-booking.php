<?php
/**
 * Jet Appointments Booking core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\JetAppointmentsBooking;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class JetAppointmentsBooking
 *
 * @package SureTriggers\Integrations\JetAppointmentsBooking
 */
class JetAppointmentsBooking extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'JetAppointmentsBooking';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Jet Appointments Booking', 'suretriggers' );
		$this->description = __( 'A WordPress plugin for creating appointment booking forms and managing appointments with JetEngine.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/jet-appointments-booking.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\JET_APB\Plugin' );
	}

	/**
	 * Get appointment meta data.
	 *
	 * @param int $appointment_id The appointment ID.
	 * @return array The appointment meta data.
	 */
	public static function get_appointment_meta( $appointment_id ) {
		if ( ! class_exists( '\JET_APB\Plugin' ) ) {
			return [];
		}

		global $wpdb;

		// Get and sanitize table name.
		$meta_table = \JET_APB\Plugin::instance()->db->appointments_meta->table();
		if ( empty( $meta_table ) || ! is_string( $meta_table ) ) {
			return [];
		}
		$meta_table = esc_sql( $meta_table );

		// Prepare and execute the query using a placeholder for the appointment ID only.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT meta_key, meta_value FROM ' . esc_sql( $meta_table ) . ' WHERE appointment_id = %d',
				$appointment_id
			),
			ARRAY_A
		);

		$meta = [];
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$meta[ $row['meta_key'] ] = $row['meta_value'];
			}
		}

		return $meta;
	}

	/**
	 * Get appointment context data.
	 *
	 * @param int    $appointment_id The appointment ID.
	 * @param string $new_status     New appointment status (optional).
	 * @param string $old_status     Old appointment status (optional).
	 * @return array The appointment context data.
	 */
	public static function get_appointment_context( $appointment_id, $new_status = null, $old_status = null ) {
		if ( ! class_exists( '\JET_APB\Plugin' ) ) {
			return [];
		}

		$appointment_data = \JET_APB\Plugin::instance()->db->appointments->query( [ 'ID' => $appointment_id ], 1 );
		if ( empty( $appointment_data ) ) {
			return [];
		}
		$appointment_data = $appointment_data[0];

		$appointment_meta = self::get_appointment_meta( $appointment_id );

		$context = array_merge( $appointment_data, $appointment_meta );
		
		// Add status information if provided.
		if ( null !== $old_status ) {
			$context['old_status'] = $old_status;
		}
		if ( null !== $new_status ) {
			$context['new_status'] = $new_status;
		}

		// Add service title.
		if ( ! empty( $context['service'] ) ) {
			$service_post = get_post( $context['service'] );
			if ( $service_post ) {
				$context['service_title'] = $service_post->post_title;
			}
		}

		// Add provider title.
		if ( ! empty( $context['provider'] ) && $context['provider'] > 0 ) {
			$provider_post = get_post( $context['provider'] );
			if ( $provider_post ) {
				$context['provider_title'] = $provider_post->post_title;
			}
		}

		// Add user information.
		if ( ! empty( $context['user_id'] ) ) {
			$user = get_user_by( 'ID', $context['user_id'] );
			if ( $user ) {
				$context['user_login']        = $user->user_login;
				$context['user_email']        = $user->user_email;
				$context['user_display_name'] = $user->display_name;
			}
		}

		// Format dates.
		$date_format                   = get_option( 'date_format' );
		$time_format                   = get_option( 'time_format' );
		$context['date_formatted']     = is_string( $date_format ) ? date_i18n( $date_format, $context['date'] ) : '';
		$context['slot_formatted']     = is_string( $time_format ) ? date_i18n( $time_format, $context['slot'] ) : '';
		$context['slot_end_formatted'] = is_string( $time_format ) ? date_i18n( $time_format, $context['slot_end'] ) : '';

		return $context;
	}

}

IntegrationsController::register( JetAppointmentsBooking::class );
