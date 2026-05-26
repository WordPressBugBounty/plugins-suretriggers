<?php
/**
 * AddUserToGroupItthinx.
 * php version 5.6
 *
 * @category AddUserToGroupItthinx
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\GroupsItthinx\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * AddUserToGroupItthinx
 *
 * @category AddUserToGroupItthinx
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class AddUserToGroupItthinx extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'GroupsItthinx';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'groups_itthinx_add_user_to_group';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add User to Group', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id          User ID.
	 * @param int   $automation_id    Automation ID.
	 * @param array $fields           Fields.
	 * @param array $selected_options Selected options.
	 * @psalm-suppress UndefinedMethod
	 *
	 * @return array|bool|void
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'Groups_User_Group' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Groups plugin is not active.', 'suretriggers' ),
			];
		}

		if ( empty( $user_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'User not found.', 'suretriggers' ),
			];
		}

		$group_id = isset( $selected_options['groups_itthinx_group_id'] ) ? absint( $selected_options['groups_itthinx_group_id'] ) : 0;

		if ( empty( $group_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Group ID is required.', 'suretriggers' ),
			];
		}

		$result = \Groups_User_Group::create(
			[
				'user_id'  => $user_id,
				'group_id' => $group_id,
			]
		);

		if ( ! $result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to add user to group. The user may already be a member.', 'suretriggers' ),
			];
		}

		$context = WordPress::get_user_context( $user_id );

		$context['group_id'] = $group_id;

		if ( class_exists( 'Groups_Group' ) ) {
			$group = \Groups_Group::read( $group_id );
			if ( $group ) {
				$context['group_name']        = isset( $group->name ) ? $group->name : '';
				$context['group_description'] = isset( $group->description ) ? $group->description : '';
			}
		}

		return $context;
	}
}

AddUserToGroupItthinx::get_instance();
