<?php
/**
 * GetAffiliateGroup.
 * php version 5.6
 *
 * @category GetAffiliateGroup
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
 * GetAffiliateGroup
 *
 * @category GetAffiliateGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetAffiliateGroup extends AutomateAction {

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
	public $action = 'fluentaffiliate_get_affiliate_group';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Affiliate Group (PRO)', 'suretriggers' ),
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

		// Check if FluentAffiliate AffiliateGroup model exists.
		if ( ! class_exists( 'FluentAffiliate\App\Models\AffiliateGroup' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentAffiliate Pro plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$group_id = isset( $selected_options['group_id'] ) ? absint( $selected_options['group_id'] ) : 0;

		// Validate required fields.
		if ( empty( $group_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Group ID is required.', 'suretriggers' ),
			];
		}

		// Get the affiliate group.
		$group = \FluentAffiliate\App\Models\AffiliateGroup::find( $group_id );

		if ( ! $group ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate group not found.', 'suretriggers' ),
			];
		}

		// Get affiliate count in this group.
		$affiliate_count = 0;
		if ( is_object( $group ) && method_exists( $group, 'affiliates' ) ) {
			$affiliates_relation = $group->affiliates();
			if ( is_object( $affiliates_relation ) && method_exists( $affiliates_relation, 'count' ) ) {
				$affiliate_count = $affiliates_relation->count();
			}
		}

		// Prepare response data.
		$context = [
			'group_id'        => $group->id,
			'group_name'      => isset( $group->meta_key ) ? $group->meta_key : '',
			'rate_type'       => isset( $group->value['rate_type'] ) ? $group->value['rate_type'] : '',
			'rate'            => isset( $group->value['rate'] ) ? $group->value['rate'] : 0,
			'group_status'    => isset( $group->value['status'] ) ? $group->value['status'] : '',
			'notes'           => isset( $group->value['notes'] ) ? $group->value['notes'] : '',
			'affiliate_count' => $affiliate_count,
			'created_at'      => isset( $group->created_at ) ? $group->created_at : '',
			'updated_at'      => isset( $group->updated_at ) ? $group->updated_at : '',
			'status'          => 'success',
		];

		return $context;
	}

}

GetAffiliateGroup::get_instance();
