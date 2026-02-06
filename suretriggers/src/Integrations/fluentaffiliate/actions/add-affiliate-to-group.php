<?php
/**
 * AddAffiliateToGroup.
 * php version 5.6
 *
 * @category AddAffiliateToGroup
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
 * AddAffiliateToGroup
 *
 * @category AddAffiliateToGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class AddAffiliateToGroup extends AutomateAction {

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
	public $action = 'fluentaffiliate_add_affiliate_to_group';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add Affiliate to Group (PRO)', 'suretriggers' ),
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
		if ( ! class_exists( 'FluentAffiliate\App\Models\Affiliate' ) || ! class_exists( 'FluentAffiliate\App\Models\AffiliateGroup' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentAffiliate Pro plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$affiliate_id = isset( $selected_options['affiliate_id'] ) ? absint( $selected_options['affiliate_id'] ) : 0;
		$user_id_opt  = isset( $selected_options['user_id'] ) ? absint( $selected_options['user_id'] ) : 0;
		$user_email   = isset( $selected_options['user_email'] ) ? sanitize_email( $selected_options['user_email'] ) : '';
		$group_id     = isset( $selected_options['group_id'] ) ? absint( $selected_options['group_id'] ) : 0;

		// Validate required fields.
		if ( empty( $group_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Group ID is required.', 'suretriggers' ),
			];
		}

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
		if ( ! $affiliate ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate not found.', 'suretriggers' ),
			];
		}

		// Validate group exists.
		$group = \FluentAffiliate\App\Models\AffiliateGroup::find( $group_id );

		if ( ! $group ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate group not found.', 'suretriggers' ),
			];
		}

		// Check if affiliate is already in this group.
		if ( $affiliate->group_id === $group_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate is already in this group.', 'suretriggers' ),
			];
		}

		// Add affiliate to group.
		try {
			$old_group_id = $affiliate->group_id;

			$affiliate->group_id = $group_id;
			$affiliate->save();

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
					'affiliate_id' => $affiliate->id,
					'old_group_id' => $old_group_id,
					'new_group_id' => $affiliate->group_id,
					'group_name'   => $group->meta_key,
					'rate_type'    => isset( $group->value['rate_type'] ) ? $group->value['rate_type'] : '',
					'rate'         => isset( $group->value['rate'] ) ? $group->value['rate'] : 0,
					'group_status' => isset( $group->value['status'] ) ? $group->value['status'] : '',
					'updated_at'   => isset( $affiliate->updated_at ) ? $affiliate->updated_at : '',
					'status'       => 'success',
				]
			);

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error adding affiliate to group: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

AddAffiliateToGroup::get_instance();
