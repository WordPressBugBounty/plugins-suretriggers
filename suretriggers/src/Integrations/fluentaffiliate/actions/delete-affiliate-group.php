<?php
/**
 * DeleteAffiliateGroup.
 * php version 5.6
 *
 * @category DeleteAffiliateGroup
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
 * DeleteAffiliateGroup
 *
 * @category DeleteAffiliateGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeleteAffiliateGroup extends AutomateAction {

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
	public $action = 'fluentaffiliate_delete_affiliate_group';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Affiliate Group (PRO)', 'suretriggers' ),
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

		// Delete affiliate group.
		try {
			// Store group data before deletion.
			$group_data = [
				'group_id'   => $group->id,
				'group_name' => isset( $group->meta_key ) ? $group->meta_key : '',
				'rate_type'  => isset( $group->value['rate_type'] ) ? $group->value['rate_type'] : '',
				'rate'       => isset( $group->value['rate'] ) ? $group->value['rate'] : 0,
				'status'     => isset( $group->value['status'] ) ? $group->value['status'] : '',
				'notes'      => isset( $group->value['notes'] ) ? $group->value['notes'] : '',
			];

			// Fire before delete hook.
			do_action( 'fluent_affiliate/before_delete_affiliate_group', $group ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			// Delete the group.
			$group->delete();

			// Fire after delete hook.
			do_action( 'fluent_affiliate/after_delete_affiliate_group', $group ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			// Prepare response data.
			$context = array_merge(
				$group_data,
				[
					'deleted_at' => gmdate( 'Y-m-d H:i:s' ),
					'status'     => 'success',
				]
			);

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error deleting affiliate group: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

DeleteAffiliateGroup::get_instance();
