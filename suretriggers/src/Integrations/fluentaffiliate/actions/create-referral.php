<?php
/**
 * CreateReferral.
 * php version 5.6
 *
 * @category CreateReferral
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
 * CreateReferral
 *
 * @category CreateReferral
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateReferral extends AutomateAction {

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
	public $action = 'fluentaffiliate_create_referral';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Referral', 'suretriggers' ),
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

		// Check if FluentAffiliate models exist.
		if ( ! class_exists( 'FluentAffiliate\App\Models\Referral' ) || ! class_exists( 'FluentAffiliate\App\Models\Affiliate' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentAffiliate plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$affiliate_id = isset( $selected_options['affiliate_id'] ) ? absint( $selected_options['affiliate_id'] ) : 0;
		$amount       = isset( $selected_options['amount'] ) ? sanitize_text_field( $selected_options['amount'] ) : '';
		$description  = isset( $selected_options['description'] ) ? sanitize_text_field( $selected_options['description'] ) : '';
		$status       = ! empty( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : 'unpaid';
		$type         = ! empty( $selected_options['type'] ) ? sanitize_text_field( $selected_options['type'] ) : 'sale';

		$affiliate = null;

		// Try to get affiliate by affiliate ID first.
		if ( ! empty( $affiliate_id ) ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( $affiliate_id );
		}

		// Validate affiliate found.
		if ( ! $affiliate ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate not found.', 'suretriggers' ),
			];
		}

		// Validate affiliate is active.
		if ( 'active' !== $affiliate->status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Cannot create a referral for an inactive affiliate.', 'suretriggers' ),
			];
		}

		// Validate required fields.
		if ( empty( $amount ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Amount is required.', 'suretriggers' ),
			];
		}

		// Validate status.
		$allowed_statuses = [ 'unpaid', 'rejected', 'pending' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid status. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_statuses ) ),
			];
		}

		// Validate type.
		$allowed_types = [ 'sale', 'opt_in' ];
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid type. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_types ) ),
			];
		}

		// Create referral.
		try {
			$referral_data = [
				'affiliate_id' => $affiliate->id,
				'description'  => $description,
				'amount'       => ( (int) ( (float) $amount * 100 ) ) / 100,
				'status'       => $status,
				'type'         => $type,
			];

			$referral = \FluentAffiliate\App\Models\Referral::create( $referral_data );

			if ( ! $referral ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to create referral.', 'suretriggers' ),
				];
			}

			// Fire creating event.
			do_action( 'fluent_affiliate/referral_marked_unpaid', $referral ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			// Recount affiliate earnings.
			$affiliate->recountEarnings();

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
					'referral_id'     => isset( $referral->id ) ? $referral->id : '',
					'affiliate_id'    => isset( $referral->affiliate_id ) ? $referral->affiliate_id : '',
					'amount'          => isset( $referral->amount ) ? $referral->amount : 0,
					'description'     => isset( $referral->description ) ? $referral->description : '',
					'referral_status' => isset( $referral->status ) ? $referral->status : '',
					'type'            => isset( $referral->type ) ? $referral->type : '',
					'created_at'      => isset( $referral->created_at ) ? $referral->created_at : '',
					'status'          => 'success',
				]
			);

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error creating referral: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

CreateReferral::get_instance();
