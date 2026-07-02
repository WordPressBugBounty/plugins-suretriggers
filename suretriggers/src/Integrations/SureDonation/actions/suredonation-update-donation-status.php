<?php
/**
 * SureDonationUpdateDonationStatus action.
 * php version 5.6
 *
 * @category SureDonationUpdateDonationStatus
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
 * SureDonationUpdateDonationStatus
 *
 * @category SureDonationUpdateDonationStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDonationUpdateDonationStatus extends AutomateAction {

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
	public $action = 'suredonation_update_donation_status';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Donation Status', 'suretriggers' ),
			'action'   => 'suredonation_update_donation_status',
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

		if ( ! class_exists( 'SureDonation\Inc\Database\Tables\Donations' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDonation classes are not available.', 'suretriggers' ),
			];
		}

		$donation_id = ! empty( $selected_options['donation_id'] ) ? absint( $selected_options['donation_id'] ) : 0;
		$new_status  = ! empty( $selected_options['payment_status'] ) ? sanitize_text_field( $selected_options['payment_status'] ) : '';

		if ( empty( $donation_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Donation ID is required.', 'suretriggers' ),
			];
		}

		$valid_statuses = [ 'pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded', 'cancelled' ];
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid status. Allowed: pending, processing, completed, failed, refunded, partially_refunded, cancelled.', 'suretriggers' ),
			];
		}

		$donation = \SureDonation\Inc\Database\Tables\Donations::get( $donation_id );
		if ( ! $donation ) {
			return [
				'status'  => 'error',
				'message' => __( 'Donation not found.', 'suretriggers' ),
			];
		}

		$old_status = isset( $donation['payment_status'] ) ? (string) $donation['payment_status'] : '';
		$result     = \SureDonation\Inc\Database\Tables\Donations::update_status( $donation_id, $new_status );

		if ( ! $result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to update donation status.', 'suretriggers' ),
			];
		}

		$updated_donation = \SureDonation\Inc\Database\Tables\Donations::get( $donation_id );

		return [
			'status'      => 'success',
			'message'     => __( 'Donation status updated successfully.', 'suretriggers' ),
			'donation_id' => $donation_id,
			'old_status'  => $old_status,
			'new_status'  => $new_status,
			'donation'    => is_array( $updated_donation ) ? $updated_donation : [],
		];
	}
}

SureDonationUpdateDonationStatus::get_instance();
