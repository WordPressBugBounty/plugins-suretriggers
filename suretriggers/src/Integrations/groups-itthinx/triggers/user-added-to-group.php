<?php
/**
 * UserAddedToGroup.
 * php version 5.6
 *
 * @category UserAddedToGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\GroupsItthinx\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'UserAddedToGroupItthinx' ) ) :

	/**
	 * UserAddedToGroupItthinx
	 *
	 * @category UserAddedToGroupItthinx
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UserAddedToGroupItthinx {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'GroupsItthinx';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'groups_itthinx_user_added_to_group';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register trigger.
		 *
		 * @param array $triggers triggers.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'User Added to Group', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'groups_created_user_group',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int $user_id  User ID.
		 * @param int $group_id Group ID.
		 * @return void
		 */
		public function trigger_listener( $user_id, $group_id ) {
			if ( empty( $user_id ) || empty( $group_id ) ) {
				return;
			}

			$context             = WordPress::get_user_context( (int) $user_id );
			$context['group_id'] = (int) $group_id;

			if ( class_exists( 'Groups_Group' ) ) {
				$group = \Groups_Group::read( (int) $group_id );
				if ( $group ) {
					$context['group_name']        = isset( $group->name ) ? $group->name : '';
					$context['group_description'] = isset( $group->description ) ? $group->description : '';
				}
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	UserAddedToGroupItthinx::get_instance();

endif;
