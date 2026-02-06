<?php
/**
 * UpdateAffiliateStatus.
 * php version 5.6
 *
 * @category UpdateAffiliateStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentAffiliate\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * UpdateAffiliateStatus
 *
 * @category UpdateAffiliateStatus
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateAffiliateStatus extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentAffiliate';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fluentaffiliate_update_affiliate_status';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Affiliate Status', 'suretriggers' ),
			'action'   => $this->action,
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
	 * @return array|void
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		// Check if FluentAffiliate Affiliate model exists.
		if ( ! class_exists( 'FluentAffiliate\App\Models\Affiliate' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentAffiliate plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$affiliate_id = isset( $selected_options['affiliate_id'] ) ? absint( $selected_options['affiliate_id'] ) : 0;
		$new_status   = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';

		// Validate new status.
		if ( empty( $new_status ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Status is required.', 'suretriggers' ),
			];
		}

		// Validate status value.
		$allowed_statuses = [ 'active', 'pending', 'inactive' ];
		if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid status. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_statuses ) ),
			];
		}

		// Get affiliate by affiliate ID.
		if ( empty( $affiliate_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate ID is required.', 'suretriggers' ),
			];
		}

		$affiliate = \FluentAffiliate\App\Models\Affiliate::find( $affiliate_id );

		// Validate affiliate found.
		if ( ! $affiliate ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate not found.', 'suretriggers' ),
			];
		}

		// Check if status is already the same.
		if ( $affiliate->status === $new_status ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Affiliate status is already %s.', 'suretriggers' ), $new_status ),
			];
		}

		// Store old status for hook.
		$old_status = $affiliate->status;

		// Update affiliate status.
		try {
			$affiliate->status = $new_status;
			$affiliate->save();

			// Fire status change hook.
			do_action( 'fluent_affiliate/affiliate_status_to_' . $new_status, $affiliate, $old_status ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			// Get WordPress user context.
			$user_data = [];
			if ( isset( $affiliate->user_id ) ) {
				$wp_user = get_user_by( 'ID', $affiliate->user_id );
				if ( false !== $wp_user ) {
					$user_data = [
						'user_id'      => $wp_user->ID,
						'user_login'   => $wp_user->user_login,
						'user_email'   => $wp_user->user_email,
						'first_name'   => $wp_user->first_name,
						'last_name'    => $wp_user->last_name,
						'display_name' => $wp_user->display_name,
					];
				}
			}

			// Prepare response data.
			$context = array_merge(
				$user_data,
				[
					'affiliate_id'    => $affiliate->id,
					'old_status'      => $old_status,
					'new_status'      => $affiliate->status,
					'payment_email'   => isset( $affiliate->payment_email ) ? $affiliate->payment_email : '',
					'rate_type'       => isset( $affiliate->rate_type ) ? $affiliate->rate_type : '',
					'commission_rate' => isset( $affiliate->commission_rate ) ? $affiliate->commission_rate : '',
					'updated_at'      => isset( $affiliate->updated_at ) ? $affiliate->updated_at : '',
					'status'          => 'success',
				]
			);

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error updating affiliate status: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

UpdateAffiliateStatus::get_instance();
