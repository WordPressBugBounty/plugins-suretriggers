<?php
/**
 * SureDonationDonationStatusChanged trigger.
 * php version 5.6
 *
 * @category SureDonationDonationStatusChanged
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDonation\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Controllers\OptionController;
use SureTriggers\Traits\SingletonLoader;

/**
 * SureDonationDonationStatusChanged
 *
 * @category SureDonationDonationStatusChanged
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDonationDonationStatusChanged {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDonation';

	/**
	 * Trigger name.
	 *
	 * @var string
	 */
	public $trigger = 'suredonation_donation_status_changed';

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
	 * Register a trigger.
	 *
	 * @param array $triggers triggers.
	 * @return array
	 */
	public function register( $triggers ) {
		$triggers[ $this->integration ][ $this->trigger ] = [
			'label'         => __( 'Donation Status Changed', 'suretriggers' ),
			'action'        => 'suredonation_donation_status_changed',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 4,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * @param int                  $donation_id Donation ID.
	 * @param string               $new_status  New payment status.
	 * @param string               $old_status  Previous payment status.
	 * @param array<string, mixed> $donation    Donation data array.
	 * @return void
	 */
	public function trigger_listener( $donation_id, $new_status, $old_status, $donation ) {
		if ( empty( $donation_id ) ) {
			return;
		}

		$trigger_data = OptionController::get_option( 'trigger_data' );
		if ( is_array( $trigger_data ) && isset( $trigger_data[ $this->integration ][ $this->trigger ]['selected_options']['new_status'] ) ) {
			$selected_status = $trigger_data[ $this->integration ][ $this->trigger ]['selected_options']['new_status'];
			if ( is_array( $selected_status ) ) {
				$selected_status = isset( $selected_status['value'] ) ? $selected_status['value'] : '';
			}
			if ( ! empty( $selected_status ) && '-1' !== (string) $selected_status && $selected_status !== $new_status ) {
				return;
			}
		}

		if ( ( ! is_array( $donation ) || empty( $donation ) ) && class_exists( 'SureDonation\Inc\Database\Tables\Donations' ) ) {
			$donation = \SureDonation\Inc\Database\Tables\Donations::get( absint( $donation_id ) );
		}

		if ( ! is_array( $donation ) ) {
			$donation = [];
		}

		$campaign_id = isset( $donation['campaign_id'] ) && is_numeric( $donation['campaign_id'] ) ? absint( $donation['campaign_id'] ) : 0;
		$form_id     = isset( $donation['form_id'] ) && is_numeric( $donation['form_id'] ) ? absint( $donation['form_id'] ) : 0;

		$context = [
			'donation_id'         => absint( $donation_id ),
			'old_status'          => sanitize_text_field( $old_status ),
			'new_status'          => sanitize_text_field( $new_status ),
			'campaign_id'         => $campaign_id,
			'campaign_title'      => $campaign_id ? (string) get_the_title( $campaign_id ) : '',
			'form_id'             => $form_id,
			'form_title'          => $form_id ? (string) get_the_title( $form_id ) : '',
			'donor_id'            => isset( $donation['donor_id'] ) && is_numeric( $donation['donor_id'] ) ? absint( $donation['donor_id'] ) : 0,
			'donor_name'          => isset( $donation['donor_name'] ) ? sanitize_text_field( (string) $donation['donor_name'] ) : '',
			'donor_email'         => isset( $donation['donor_email'] ) ? sanitize_email( (string) $donation['donor_email'] ) : '',
			'donor_phone'         => isset( $donation['donor_phone'] ) ? sanitize_text_field( (string) $donation['donor_phone'] ) : '',
			'amount'              => isset( $donation['amount'] ) && is_numeric( $donation['amount'] ) ? (float) $donation['amount'] : 0.0,
			'fees_covered'        => isset( $donation['fees_covered'] ) && is_numeric( $donation['fees_covered'] ) ? (float) $donation['fees_covered'] : 0.0,
			'currency'            => isset( $donation['currency'] ) ? sanitize_text_field( (string) $donation['currency'] ) : 'USD',
			'payment_status'      => sanitize_text_field( $new_status ),
			'payment_mode'        => isset( $donation['payment_mode'] ) ? sanitize_text_field( (string) $donation['payment_mode'] ) : '',
			'gateway'             => isset( $donation['gateway'] ) ? sanitize_text_field( (string) $donation['gateway'] ) : '',
			'transaction_id'      => isset( $donation['transaction_id'] ) ? sanitize_text_field( (string) $donation['transaction_id'] ) : '',
			'donation_type'       => isset( $donation['donation_type'] ) ? sanitize_text_field( (string) $donation['donation_type'] ) : 'one-time',
			'is_anonymous'        => ! empty( $donation['is_anonymous'] ),
			'donor_comment'       => isset( $donation['donor_comment'] ) ? wp_kses_post( (string) $donation['donor_comment'] ) : '',
			'subscription_id'     => isset( $donation['subscription_id'] ) ? sanitize_text_field( (string) $donation['subscription_id'] ) : '',
			'subscription_status' => isset( $donation['subscription_status'] ) ? sanitize_text_field( (string) $donation['subscription_status'] ) : '',
			'created_at'          => isset( $donation['created_at'] ) ? sanitize_text_field( (string) $donation['created_at'] ) : '',
			'updated_at'          => isset( $donation['updated_at'] ) ? sanitize_text_field( (string) $donation['updated_at'] ) : '',
		];

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

SureDonationDonationStatusChanged::get_instance();
