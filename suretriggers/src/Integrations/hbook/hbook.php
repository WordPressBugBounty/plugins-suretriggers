<?php
/**
 * HBook core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\HBook;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class HBook
 *
 * @package SureTriggers\Integrations\HBook
 */
class HBook extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'HBook';

	/**
	 * Reservation statuses HBook understands.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'pending', 'new', 'confirmed', 'cancelled' ];

	/**
	 * Reservation columns that hold JSON and are decoded for the context.
	 *
	 * @var string[]
	 */
	const JSON_COLUMNS = [ 'options', 'fees', 'additional_info', 'payments_logs' ];

	/**
	 * Reservation columns never exposed to automations (payment secrets).
	 *
	 * @var string[]
	 */
	const HIDDEN_COLUMNS = [ 'payment_token', 'payment_info' ];

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'HBook', 'suretriggers' );
		$this->description = __( 'A WordPress booking plugin for hotels, B&Bs, vacation rentals and other accommodation.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/hbook.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( '\HBook' ) && class_exists( '\HbDataBaseActions' );
	}

	/**
	 * Generate a unique reservation reference, mirroring HbUtils::get_alphanum().
	 *
	 * @return string
	 */
	public static function generate_alphanum() {
		if ( ! class_exists( '\HbDataBaseActions' ) ) {
			return '';
		}

		// HBook keeps its own instance private, but the class only wraps $wpdb, so a fresh one behaves identically.
		$db         = new \HbDataBaseActions();
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		do {
			$alphanum = '';
			for ( $i = 0; $i < 8; $i++ ) {
				$alphanum .= $characters[ wp_rand( 0, 35 ) ];
			}
		} while ( $db->get_resa_by_alphanum( $alphanum ) );
		return $alphanum;
	}

	/**
	 * Get a customer's decoded info along with the email.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public static function get_customer_context( $customer_id ) {
		$customer_id = absint( $customer_id );
		if ( ! class_exists( '\HbDataBaseActions' ) || ! $customer_id ) {
			return [];
		}

		$db = new \HbDataBaseActions();

		$customer = $db->get_single( 'customers', $customer_id );
		if ( empty( $customer ) || ! is_array( $customer ) ) {
			return [];
		}

		$info = $db->get_customer_info( $customer_id );

		$context = [
			'customer_id'    => $customer_id,
			'customer_email' => isset( $customer['email'] ) ? $customer['email'] : '',
			'customer_info'  => $info,
			'nb_resa'        => isset( $customer['nb_resa'] ) ? $customer['nb_resa'] : 0,
		];

		foreach ( $info as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$context[ 'customer_' . $key ] = $value;
			}
		}

		return $context;
	}

	/**
	 * Build the context for a reservation.
	 *
	 * @param int $resa_id Reservation ID.
	 * @return array
	 */
	public static function get_reservation_context( $resa_id ) {
		$resa_id = absint( $resa_id );
		if ( ! class_exists( '\HbDataBaseActions' ) || ! $resa_id ) {
			return [];
		}

		$db = new \HbDataBaseActions();

		$resa = $db->get_single( 'resa', $resa_id );
		if ( empty( $resa ) || ! is_array( $resa ) ) {
			return [];
		}

		foreach ( self::HIDDEN_COLUMNS as $column ) {
			unset( $resa[ $column ] );
		}

		foreach ( self::JSON_COLUMNS as $column ) {
			if ( isset( $resa[ $column ] ) && is_string( $resa[ $column ] ) && '' !== $resa[ $column ] ) {
				$decoded = json_decode( $resa[ $column ], true );
				if ( null !== $decoded ) {
					$resa[ $column ] = $decoded;
				}
			}
		}

		$context                = $resa;
		$context['resa_id']     = $resa_id;
		$context['accom_title'] = isset( $resa['accom_id'] ) && is_numeric( $resa['accom_id'] ) ? get_the_title( absint( $resa['accom_id'] ) ) : '';
		$context['nights']      = self::count_nights( $resa );

		$customer_id = isset( $resa['customer_id'] ) && is_numeric( $resa['customer_id'] ) ? absint( $resa['customer_id'] ) : 0;
		if ( $customer_id ) {
			$context = array_merge( $context, self::get_customer_context( $customer_id ) );
		}

		return $context;
	}

	/**
	 * Number of nights between check-in and check-out.
	 *
	 * @param array $resa Reservation row.
	 * @return int
	 */
	private static function count_nights( $resa ) {
		if ( empty( $resa['check_in'] ) || empty( $resa['check_out'] ) ) {
			return 0;
		}
		$check_in  = strtotime( (string) $resa['check_in'] );
		$check_out = strtotime( (string) $resa['check_out'] );
		if ( false === $check_in || false === $check_out || $check_out < $check_in ) {
			return 0;
		}
		return (int) round( ( $check_out - $check_in ) / DAY_IN_SECONDS );
	}

	/**
	 * Get the most recent logged status transition of a reservation.
	 *
	 * HBook fires its update hooks after writing, so the previous status is only
	 * available from its own reservation log.
	 *
	 * @param int $resa_id Reservation ID.
	 * @return array{previous_status: string, status: string}
	 */
	public static function get_status_transition( $resa_id ) {
		$empty = [
			'previous_status' => '',
			'status'          => '',
		];
		if ( ! class_exists( '\HbDataBaseActions' ) ) {
			return $empty;
		}

		$db = new \HbDataBaseActions();

		global $wpdb;
		$logs_table = $db->resa_logs_table;
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT previous_status, status, logged_on FROM {$logs_table} WHERE resa_id = %d AND is_parent = 0 AND event = 'update' ORDER BY id DESC LIMIT 1", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $resa_id )
			),
			ARRAY_A
		);

		if ( empty( $row ) || ! is_array( $row ) ) {
			return $empty;
		}

		// Some HBook updates (e.g. delayed payments) fire the hook without logging a transition; ignore a stale row.
		$logged_at = isset( $row['logged_on'] ) && is_string( $row['logged_on'] ) ? strtotime( $row['logged_on'] . ' UTC' ) : false;
		if ( false === $logged_at || ( time() - $logged_at ) > 30 ) {
			return $empty;
		}

		return [
			'previous_status' => isset( $row['previous_status'] ) ? (string) $row['previous_status'] : '',
			'status'          => isset( $row['status'] ) ? (string) $row['status'] : '',
		];
	}

}

IntegrationsController::register( HBook::class );
