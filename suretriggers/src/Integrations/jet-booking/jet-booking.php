<?php
/**
 * JetBooking core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\JetBooking;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class JetBooking
 *
 * @package SureTriggers\Integrations\JetBooking
 */
class JetBooking extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'JetBooking';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'JetBooking', 'suretriggers' );
		$this->description = __( 'A WordPress plugin for creating booking functionality for apartments and services with an availability check.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/jet-booking.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\JET_ABAF\Plugin' );
	}

	/**
	 * Get booking data by booking ID.
	 *
	 * @param int $booking_id The booking ID.
	 * @return array The booking data, or an empty array if not found.
	 */
	public static function get_booking( $booking_id ) {
		if ( ! class_exists( '\JET_ABAF\Plugin' ) ) {
			return [];
		}

		$booking = \JET_ABAF\Plugin::instance()->db->get_booking_by( 'booking_id', $booking_id );

		return ! empty( $booking ) && is_array( $booking ) ? $booking : [];
	}

	/**
	 * Get booking context data.
	 *
	 * @param array  $booking    The booking data.
	 * @param string $new_status New booking status (optional).
	 * @param string $old_status Old booking status (optional).
	 * @return array The booking context data.
	 */
	public static function get_booking_context( $booking, $new_status = null, $old_status = null ) {
		if ( empty( $booking ) || ! is_array( $booking ) ) {
			return [];
		}

		$context = $booking;

		if ( null !== $old_status ) {
			$context['old_status'] = $old_status;
		}
		if ( null !== $new_status ) {
			$context['new_status'] = $new_status;
		}

		if ( ! empty( $context['apartment_id'] ) ) {
			$apartment_post = get_post( $context['apartment_id'] );
			if ( $apartment_post ) {
				$context['apartment_title'] = $apartment_post->post_title;
			}
		}

		if ( ! empty( $context['user_id'] ) ) {
			$user = get_user_by( 'ID', $context['user_id'] );
			if ( $user ) {
				$context['user_login']        = $user->user_login;
				$context['user_display_name'] = $user->display_name;
				if ( empty( $context['user_email'] ) ) {
					$context['user_email'] = $user->user_email;
				}
			}
		}

		$date_format = get_option( 'date_format' );

		if ( ! empty( $context['check_in_date'] ) && is_string( $date_format ) ) {
			$context['check_in_date_formatted'] = date_i18n( $date_format, $context['check_in_date'] );
		}

		if ( ! empty( $context['check_out_date'] ) && is_string( $date_format ) ) {
			$context['check_out_date_formatted'] = date_i18n( $date_format, $context['check_out_date'] );
		}

		return $context;
	}

}

IntegrationsController::register( JetBooking::class );
