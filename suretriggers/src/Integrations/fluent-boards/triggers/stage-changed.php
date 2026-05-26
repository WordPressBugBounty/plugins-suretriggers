<?php
/**
 * StageChanged.
 * php version 5.6
 *
 * @category StageChanged
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentBoards\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'StageChanged' ) ) :

	/**
	 * StageChanged
	 *
	 * @category StageChanged
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class StageChanged {


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
		public $trigger = 'fbs_stage_changed';

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
				'label'         => __( 'Stage Changed', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'fluent_boards/task_stage_updated',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $task Task.
		 * @param int    $old_stage_id Old Status ID.
		 * @return void
		 */
		public function trigger_listener( $task, $old_stage_id ) {
			if ( empty( $task ) || ! is_object( $task ) || empty( $old_stage_id ) ) {
				return;
			}

			if ( method_exists( $task, 'load' ) && class_exists( 'FluentBoardsPro\App\Services\Constant' ) ) {
				$task->load( 'customFields' );
			}

			$context = [
				'task'          => $task,
				'old_stage_id'  => $old_stage_id,
				'custom_fields' => $this->parse_custom_fields( $task ),
			];

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}

		/**
		 * Parse custom fields into a clean key-value format.
		 *
		 * @param object $task Task object with customFields loaded.
		 * @return array
		 */
		private function parse_custom_fields( $task ) {
			if ( ! method_exists( $task, 'relationLoaded' ) || ! $task->relationLoaded( 'customFields' ) ) {
				return [];
			}
			if ( ! method_exists( $task, 'getRelation' ) ) {
				return [];
			}

			$loaded_fields = $task->getRelation( 'customFields' );
			if ( empty( $loaded_fields ) ) {
				return [];
			}

			$result = [];
			foreach ( $loaded_fields as $field ) {
				$raw_pivot      = isset( $field->pivot->settings ) ? maybe_unserialize( $field->pivot->settings ) : [];
				$pivot_settings = is_array( $raw_pivot ) ? $raw_pivot : [];
				$field_settings = isset( $field->settings ) && is_array( $field->settings )
					? $field->settings
					: [];

				$result[] = [
					'id'    => $field->id,
					'title' => $field->title,
					'slug'  => $field->slug,
					'type'  => isset( $field_settings['custom_field_type'] ) ? $field_settings['custom_field_type'] : '',
					'value' => isset( $pivot_settings['value'] ) ? $pivot_settings['value'] : '',
				];
			}

			return $result;
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	StageChanged::get_instance();

endif;
