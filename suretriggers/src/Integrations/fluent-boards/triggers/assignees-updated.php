<?php
/**
 * AssigneesUpdated.
 * php version 5.6
 *
 * @category AssigneesUpdated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentBoards\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'AssigneesUpdated' ) ) :

	/**
	 * AssigneesUpdated
	 *
	 * @category AssigneesUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class AssigneesUpdated {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentBoards';


		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fbs_assignees_updated';

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
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Task Assignees Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'fluent_boards/task_assignee_added',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $task        Task.
		 * @param int    $assignee_id Assignee user ID.
		 * @return void
		 */
		public function trigger_listener( $task, $assignee_id ) {
			if ( empty( $task ) || empty( $assignee_id ) ) {
				return;
			}

			$task_data = is_object( $task ) && method_exists( $task, 'toArray' ) ? $task->toArray() : $task;

			$context = [
				'task'        => $task_data,
				'assignee_id' => $assignee_id,
			];
			
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
	AssigneesUpdated::get_instance();

endif;
