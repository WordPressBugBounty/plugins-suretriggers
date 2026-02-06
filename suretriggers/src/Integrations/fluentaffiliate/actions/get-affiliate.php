<?php
/**
 * GetAffiliate.
 * php version 5.6
 *
 * @category GetAffiliate
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
 * GetAffiliate
 *
 * @category GetAffiliate
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetAffiliate extends AutomateAction {

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
	public $action = 'fluentaffiliate_get_affiliate';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Affiliate', 'suretriggers' ),
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
		$user_email   = isset( $selected_options['affiliate_email'] ) ? sanitize_email( $selected_options['affiliate_email'] ) : '';

		$affiliate = null;

		// Try to get affiliate by affiliate ID first.
		if ( ! empty( $affiliate_id ) ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( $affiliate_id );
		} elseif ( ! empty( $user_email ) ) {
			// Get user by email, then get affiliate.
			$wp_user = get_user_by( 'email', $user_email );
			if ( false !== $wp_user ) {
				$affiliate = \FluentAffiliate\App\Models\Affiliate::where( 'user_id', $wp_user->ID )->first();
			}
		}

		// Validate affiliate found.
		if ( ! $affiliate ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate not found.', 'suretriggers' ),
			];
		}

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
				'affiliate_id'     => isset( $affiliate->id ) ? $affiliate->id : '',
				'affiliate_status' => isset( $affiliate->status ) ? $affiliate->status : '',
				'payment_email'    => isset( $affiliate->payment_email ) ? $affiliate->payment_email : '',
				'rate_type'        => isset( $affiliate->rate_type ) ? $affiliate->rate_type : '',
				'commission_rate'  => isset( $affiliate->commission_rate ) ? $affiliate->commission_rate : '',
				'earnings'         => isset( $affiliate->earnings ) ? $affiliate->earnings : 0,
				'paid_earnings'    => isset( $affiliate->paid_earnings ) ? $affiliate->paid_earnings : 0,
				'unpaid_earnings'  => isset( $affiliate->unpaid_earnings ) ? $affiliate->unpaid_earnings : 0,
				'referrals'        => isset( $affiliate->referrals ) ? $affiliate->referrals : 0,
				'visits'           => isset( $affiliate->visits ) ? $affiliate->visits : 0,
				'conversions'      => isset( $affiliate->conversions ) ? $affiliate->conversions : 0,
				'notes'            => isset( $affiliate->notes ) ? $affiliate->notes : '',
				'created_at'       => isset( $affiliate->created_at ) ? $affiliate->created_at : '',
				'updated_at'       => isset( $affiliate->updated_at ) ? $affiliate->updated_at : '',
				'status'           => 'success',
			]
		);

		return $context;
	}

}

GetAffiliate::get_instance();
