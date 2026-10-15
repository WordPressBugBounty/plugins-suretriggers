<?php
/**
 * CreateReservation.
 * php version 5.6
 *
 * @category CreateReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\HBook\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\HBook\HBook;

/**
 * CreateReservation
 *
 * @category CreateReservation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateReservation extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'HBook';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'hbook_create_reservation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Reservation', 'suretriggers' ),
			'action'   => 'hbook_create_reservation',
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
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( '\HbDataBaseActions' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'HBook plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$db = new \HbDataBaseActions();

		$accom_id    = isset( $selected_options['accom_id'] ) ? absint( $selected_options['accom_id'] ) : 0;
		$check_in    = isset( $selected_options['check_in'] ) && is_string( $selected_options['check_in'] ) ? sanitize_text_field( $selected_options['check_in'] ) : '';
		$check_out   = isset( $selected_options['check_out'] ) && is_string( $selected_options['check_out'] ) ? sanitize_text_field( $selected_options['check_out'] ) : '';
		$email       = isset( $selected_options['email'] ) && is_string( $selected_options['email'] ) ? sanitize_email( $selected_options['email'] ) : '';
		$status      = isset( $selected_options['status'] ) && is_string( $selected_options['status'] ) && '' !== $selected_options['status'] ? sanitize_key( $selected_options['status'] ) : 'new';
		$adults      = isset( $selected_options['adults'] ) && is_numeric( $selected_options['adults'] ) ? max( 1, absint( $selected_options['adults'] ) ) : 1;
		$children    = isset( $selected_options['children'] ) && is_numeric( $selected_options['children'] ) ? absint( $selected_options['children'] ) : 0;
		$price       = isset( $selected_options['price'] ) && is_numeric( $selected_options['price'] ) ? max( 0, (float) $selected_options['price'] ) : 0;
		$admin_notes = isset( $selected_options['admin_comment'] ) && is_string( $selected_options['admin_comment'] ) ? sanitize_textarea_field( $selected_options['admin_comment'] ) : '';

		if ( empty( $accom_id ) || '' === $check_in || '' === $check_out || ! is_email( $email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Accommodation ID, check-in date, check-out date and a valid customer email are required.', 'suretriggers' ),
			];
		}

		if ( 'hb_accommodation' !== get_post_type( $accom_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid accommodation ID provided.', 'suretriggers' ),
			];
		}

		if ( ! in_array( $status, HBook::STATUSES, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: list of valid statuses */
					__( 'Invalid status. Valid statuses are: %s', 'suretriggers' ),
					implode( ', ', HBook::STATUSES )
				),
			];
		}

		$in_date  = \DateTime::createFromFormat( 'Y-m-d', $check_in );
		$out_date = \DateTime::createFromFormat( 'Y-m-d', $check_out );
		if ( ! $in_date || ! $out_date || $in_date->format( 'Y-m-d' ) !== $check_in || $out_date->format( 'Y-m-d' ) !== $check_out || $out_date <= $in_date ) {
			return [
				'status'  => 'error',
				'message' => __( 'Dates must use the YYYY-MM-DD format and check-out must be after check-in.', 'suretriggers' ),
			];
		}

		$accom_num = $db->get_first_available_accom_num( $accom_id, $check_in, $check_out );
		if ( false === $accom_num || null === $accom_num ) {
			return [
				'status'  => 'error',
				'message' => __( 'The accommodation is not available for the selected dates.', 'suretriggers' ),
			];
		}

		$accom_num = absint( $accom_num );

		// Reuse the customer when the email is already known, as HBook's own booking form does.
		$customer_id = $db->get_customer_id( $email );
		if ( ! $customer_id ) {
			$info = [ 'email' => $email ];
			foreach ( [ 'first_name', 'last_name', 'phone' ] as $field ) {
				if ( isset( $selected_options[ $field ] ) && is_scalar( $selected_options[ $field ] ) ) {
					$info[ $field ] = sanitize_text_field( (string) $selected_options[ $field ] );
				}
			}
			$customer_id = $db->create_customer( $email, $info );
		}
		if ( ! $customer_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create the customer.', 'suretriggers' ),
			];
		}
		$customer_id = absint( $customer_id );

		$resa_info = [
			'check_in'              => $check_in,
			'check_out'             => $check_out,
			'accom_id'              => $accom_id,
			'accom_num'             => $accom_num,
			'adults'                => $adults,
			'children'              => $children,
			'accom_price'           => $price,
			'discount'              => '',
			'price'                 => $price,
			'previous_price'        => 0,
			'deposit'               => 0,
			'paid'                  => 0,
			'payment_gateway'       => '',
			'customer_id'           => $customer_id,
			'status'                => $status,
			'options'               => '',
			'fees'                  => '',
			'admin_comment'         => $admin_notes,
			'coupon'                => '',
			'coupon_value'          => 0,
			'amount_to_pay'         => 0,
			'payment_type'          => '',
			'payment_info'          => '',
			'payment_token'         => '',
			'payment_status'        => '',
			'payment_status_reason' => '',
			'payment_delayed'       => 0,
			'payment_failed'        => 0,
			'payments_logs'         => '',
			'alphanum_id'           => HBook::generate_alphanum(),
			'invoice_counter'       => 0,
			'nb_emails_sent'        => 0,
			'origin'                => 'website',
			'origin_url'            => '',
			'synchro_id'            => '',
			'booking_form_num'      => 0,
			'parent_id'             => 0,
			'custom_info'           => '',
			'additional_info'       => '',
		];

		$resa_id = $db->create_resa( $resa_info );
		if ( ! $resa_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create the reservation.', 'suretriggers' ),
			];
		}
		$resa_id = absint( $resa_id );

		$db->increment_customer_nb_resa( $customer_id );

		// HBook blocks linked accommodation / preparation time once a reservation holds the dates.
		if ( 'new' === $status || 'confirmed' === $status ) {
			$db->automatic_block_accom( $accom_id, $accom_num, $check_in, $check_out, $resa_id );
		}

		return HBook::get_reservation_context( $resa_id );
	}
}

CreateReservation::get_instance();
