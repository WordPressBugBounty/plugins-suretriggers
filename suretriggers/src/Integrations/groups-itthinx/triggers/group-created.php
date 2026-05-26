<?php
/**
 * GroupCreatedItthinx.
 * php version 5.6
 *
 * @category GroupCreatedItthinx
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\GroupsItthinx\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'GroupCreatedItthinx' ) ) :

	/**
	 * GroupCreatedItthinx
	 *
	 * @category GroupCreatedItthinx
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class GroupCreatedItthinx {

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
		public $trigger = 'groups_itthinx_group_created';

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
				'label'         => __( 'Group Created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'groups_created_group',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param mixed $group_id Group ID.
		 * @return void
		 */
		public function trigger_listener( $group_id ) {
			if ( empty( $group_id ) || ! is_numeric( $group_id ) ) {
				return;
			}

			$group_id = (int) $group_id;
			$context  = [
				'group_id' => $group_id,
			];

			if ( class_exists( 'Groups_Group' ) ) {
				$group = \Groups_Group::read( $group_id );
				if ( $group ) {
					$context['group_name']        = isset( $group->name ) ? $group->name : '';
					$context['group_description'] = isset( $group->description ) ? $group->description : '';
					$context['group_datetime']    = isset( $group->datetime ) ? $group->datetime : '';
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
	GroupCreatedItthinx::get_instance();

endif;
