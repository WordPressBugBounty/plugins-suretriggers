<?php
/**
 * StageUpdated.
 * php version 5.6
 *
 * @category StageUpdated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentBoards\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'StageUpdated' ) ) :

	/**
	 * StageUpdated
	 *
	 * @category StageUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class StageUpdated {


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
		public $trigger = 'fbs_stage_updated';

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
				'label'         => __( 'Stage Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'fluent_boards/stage_updated',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int    $board_id           Board ID.
		 * @param array  $updated_stage      Updated stage data.
		 * @param object $stage_before_update Stage before update.
		 * @return void
		 */
		public function trigger_listener( $board_id, $updated_stage, $stage_before_update ) {
			if ( empty( $board_id ) || empty( $updated_stage ) ) {
				return;
			}

			$stage_data     = is_array( $updated_stage ) ? $updated_stage : ( is_object( $updated_stage ) && method_exists( $updated_stage, 'toArray' ) ? $updated_stage->toArray() : $updated_stage );
			$old_stage_data = is_object( $stage_before_update ) && method_exists( $stage_before_update, 'toArray' ) ? $stage_before_update->toArray() : $stage_before_update;

			$context = [
				'board_id'            => $board_id,
				'updated_stage'       => $stage_data,
				'stage_before_update' => $old_stage_data,
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
	StageUpdated::get_instance();

endif;
