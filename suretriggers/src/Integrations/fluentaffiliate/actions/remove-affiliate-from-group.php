<?php
/**
 * RemoveAffiliateFromGroup.
 * php version 5.6
 *
 * @category RemoveAffiliateFromGroup
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
 * RemoveAffiliateFromGroup
 *
 * @category RemoveAffiliateFromGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RemoveAffiliateFromGroup extends AutomateAction {

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
	public $action = 'fluentaffiliate_remove_affiliate_from_group';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Remove Affiliate from Group (PRO)', 'suretriggers' ),
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
				'message' => __( 'FluentAffiliate Pro plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$affiliate_id = isset( $selected_options['affiliate_id'] ) ? absint( $selected_options['affiliate_id'] ) : 0;
		$user_id_opt  = isset( $selected_options['user_id'] ) ? absint( $selected_options['user_id'] ) : 0;
		$user_email   = isset( $selected_options['user_email'] ) ? sanitize_email( $selected_options['user_email'] ) : '';

		$affiliate = null;

		// Try to get affiliate by affiliate ID first.
		if ( ! empty( $affiliate_id ) ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( $affiliate_id );
		} elseif ( ! empty( $user_id_opt ) ) {
			// Get affiliate by user ID.
			$affiliate = \FluentAffiliate\App\Models\Affiliate::where( 'user_id', $user_id_opt )->first();
		} elseif ( ! empty( $user_email ) ) {
			// Get user by email, then get affiliate.
			$wp_user = get_user_by( 'email', $user_email );
			if ( false !== $wp_user ) {
				$affiliate = \FluentAffiliate\App\Models\Affiliate::where( 'user_id', $wp_user->ID )->first();
			}
		}

		// Validate affiliate found.
		if ( ! $affiliate || ! is_object( $affiliate ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate not found.', 'suretriggers' ),
			];
		}

		// Check if affiliate is in a group.
		if ( empty( $affiliate->group_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate is not in any group.', 'suretriggers' ),
			];
		}

		// Remove affiliate from group.
		try {
			$old_group_id = $affiliate->group_id;

			$affiliate->group_id = null;
			if ( method_exists( $affiliate, 'save' ) ) {
				$affiliate->save();
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
					'affiliate_id'     => isset( $affiliate->id ) ? $affiliate->id : 0,
					'removed_group_id' => $old_group_id,
					'current_group_id' => $affiliate->group_id,
					'affiliate_status' => isset( $affiliate->status ) ? $affiliate->status : '',
					'payment_email'    => isset( $affiliate->payment_email ) ? $affiliate->payment_email : '',
					'updated_at'       => isset( $affiliate->updated_at ) ? $affiliate->updated_at : '',
					'status'           => 'success',
				]
			);

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error removing affiliate from group: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

RemoveAffiliateFromGroup::get_instance();
