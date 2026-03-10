<?php
/**
 * FindUserGroupLeaderGroups.
 * php version 5.6
 *
 * @category FindUserGroupLeaderGroups
 * @package  SureTriggers
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\LearnDash\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\LearnDash\LearnDash;
use SureTriggers\Traits\SingletonLoader;

/**
 * FindUserGroupLeaderGroups
 *
 * @category FindUserGroupLeaderGroups
 * @package  SureTriggers
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class FindUserGroupLeaderGroups extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'LearnDash';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'learndash_find_user_group_leader_groups';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( "Find User's Group Leader Groups", 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user ID.
	 * @param int   $automation_id automation ID.
	 * @param array $fields template fields.
	 * @param array $selected_options saved template data.
	 *
	 * @return bool|array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$user_email = $selected_options['wp_user_email'];

		if ( is_email( $user_email ) ) {
			$user = get_user_by( 'email', $user_email );
			if ( $user ) {
				$user_id = $user->ID;
			} else {
				return [
					'status'   => esc_attr__( 'Error', 'suretriggers' ),
					'response' => esc_attr__( 'User not found with specified email address.', 'suretriggers' ),
				];
			}
		} else {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Please enter valid email address.', 'suretriggers' ),
			];
		}

		if ( ! function_exists( 'learndash_get_administrators_group_ids' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'LearnDash function not available', 'suretriggers' ),
			];
		}

		$user_groups = learndash_get_administrators_group_ids( $user_id, true );

		if ( empty( $user_groups ) ) {
			return [
				'message' => __( 'User is not a Group Leader of any group', 'suretriggers' ),
			];
		}

		$group_data = [];

		foreach ( $user_groups as $group_id ) {
			$group_data[] = [
				'group_id'   => $group_id,
				'group_name' => get_the_title( $group_id ),
			];
		}

		$user_data = LearnDash::get_user_pluggable_data( $user_id );

		return [
			'user'                     => $user_data,
			'groups'                   => $group_data,
			'group_leader_group_count' => count( $group_data ),
		];
	}
}

FindUserGroupLeaderGroups::get_instance();
