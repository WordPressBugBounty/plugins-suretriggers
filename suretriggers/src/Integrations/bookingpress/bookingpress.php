<?php
/**
 * BookingPress core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\BookingPress;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class BookingPress
 *
 * @package SureTriggers\Integrations\BookingPress
 */
class BookingPress extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'BookingPress';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'BookingPress', 'suretriggers' );
		$this->description = __( 'BookingPress is an appointment booking plugin for WordPress that lets you create services, manage bookings, and handle payments.', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'BOOKINGPRESS_VERSION' );
	}

	/**
	 * Get appointment context data from the database.
	 *
	 * @param int $appointment_id The bookingpress_appointment_booking_id.
	 * @return array
	 */
	public static function get_appointment_context( $appointment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bookingpress_appointment_bookings';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql( $table ) . ' WHERE bookingpress_appointment_booking_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$appointment_id
			),
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return [];
		}

		$status_labels = [
			'1' => __( 'Approved', 'suretriggers' ),
			'2' => __( 'Pending', 'suretriggers' ),
			'3' => __( 'Canceled', 'suretriggers' ),
			'4' => __( 'Rejected', 'suretriggers' ),
		];

		$status_key                      = (string) $row['bookingpress_appointment_status'];
		$row['appointment_status_label'] = isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : $status_key;

		return $row;
	}

}

IntegrationsController::register( BookingPress::class );
