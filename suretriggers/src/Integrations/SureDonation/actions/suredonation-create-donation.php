<?php
/**
 * SureDonationCreateDonation action.
 * php version 5.6
 *
 * @category SureDonationCreateDonation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDonation\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * SureDonationCreateDonation
 *
 * @category SureDonationCreateDonation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDonationCreateDonation extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDonation';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'suredonation_create_donation';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Donation', 'suretriggers' ),
			'action'   => 'suredonation_create_donation',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id         User ID.
	 * @param int   $automation_id   Automation ID.
	 * @param array $fields          Fields.
	 * @param array $selected_options Selected options.
	 *
	 * @return array|bool
	 * @throws Exception Error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'SUREDONATION_VER' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDonation plugin is not active.', 'suretriggers' ),
			];
		}

		if ( ! class_exists( 'SureDonation\Inc\Database\Tables\Donations' ) || ! class_exists( 'SureDonation\Inc\Database\Tables\Donors' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDonation classes are not available.', 'suretriggers' ),
			];
		}

		$campaign_id    = ! empty( $selected_options['campaign_id'] ) ? absint( $selected_options['campaign_id'] ) : 0;
		$donor_name     = ! empty( $selected_options['donor_name'] ) ? sanitize_text_field( $selected_options['donor_name'] ) : '';
		$donor_email    = ! empty( $selected_options['donor_email'] ) ? sanitize_email( $selected_options['donor_email'] ) : '';
		$donor_phone    = ! empty( $selected_options['donor_phone'] ) ? sanitize_text_field( $selected_options['donor_phone'] ) : '';
		$amount         = ! empty( $selected_options['amount'] ) ? floatval( $selected_options['amount'] ) : 0.0;
		$payment_status = ! empty( $selected_options['payment_status'] ) ? sanitize_text_field( $selected_options['payment_status'] ) : 'pending';
		$gateway        = ! empty( $selected_options['gateway'] ) ? sanitize_text_field( $selected_options['gateway'] ) : 'manual';
		$transaction_id = ! empty( $selected_options['transaction_id'] ) ? sanitize_text_field( $selected_options['transaction_id'] ) : '';
		$donation_type  = ! empty( $selected_options['donation_type'] ) ? sanitize_text_field( $selected_options['donation_type'] ) : 'one-time';
		$is_anonymous   = ! empty( $selected_options['is_anonymous'] );
		$donor_comment  = ! empty( $selected_options['donor_comment'] ) ? wp_kses_post( $selected_options['donor_comment'] ) : '';
		$currency       = ! empty( $selected_options['currency'] ) ? sanitize_text_field( $selected_options['currency'] ) : 'USD';

		if ( empty( $campaign_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Campaign ID is required.', 'suretriggers' ),
			];
		}

		if ( $amount <= 0 ) {
			return [
				'status'  => 'error',
				'message' => __( 'Amount must be greater than zero.', 'suretriggers' ),
			];
		}

		$valid_statuses = [ 'pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded', 'cancelled' ];
		if ( ! in_array( $payment_status, $valid_statuses, true ) ) {
			$payment_status = 'pending';
		}

		$valid_types = [ 'one-time', 'recurring', 'renewal' ];
		if ( ! in_array( $donation_type, $valid_types, true ) ) {
			$donation_type = 'one-time';
		}

		$donor_id = 0;
		if ( ! empty( $donor_email ) ) {
			$donor_id = \SureDonation\Inc\Database\Tables\Donors::get_or_create( $donor_email, $donor_name, $donor_phone );
			if ( ! $donor_id ) {
				$donor_id = 0;
			}
		}

		$donation_data = [
			'campaign_id'    => $campaign_id,
			'donor_id'       => $donor_id,
			'amount'         => $amount,
			'fees_covered'   => 0,
			'currency'       => strtoupper( $currency ),
			'gateway'        => $gateway,
			'payment_status' => $payment_status,
			'payment_mode'   => 'live',
			'donor_name'     => $donor_name,
			'donor_email'    => $donor_email,
			'donor_phone'    => $donor_phone,
			'is_anonymous'   => $is_anonymous ? 1 : 0,
			'donation_type'  => $donation_type,
			'donor_comment'  => $donor_comment,
			'transaction_id' => $transaction_id,
		];

		$donation_id = \SureDonation\Inc\Database\Tables\Donations::add( $donation_data );

		if ( ! $donation_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create donation.', 'suretriggers' ),
			];
		}

		$donation = \SureDonation\Inc\Database\Tables\Donations::get( absint( $donation_id ) );

		return [
			'status'      => 'success',
			'message'     => __( 'Donation created successfully.', 'suretriggers' ),
			'donation_id' => absint( $donation_id ),
			'donor_id'    => $donor_id,
			'campaign_id' => $campaign_id,
			'amount'      => $amount,
			'currency'    => strtoupper( $currency ),
			'donation'    => is_array( $donation ) ? $donation : [],
		];
	}
}

SureDonationCreateDonation::get_instance();
