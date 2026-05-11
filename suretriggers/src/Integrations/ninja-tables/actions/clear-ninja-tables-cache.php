<?php
/**
 * ClearNinjaTablesCache.
 * php version 5.6
 *
 * @category ClearNinjaTablesCache
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\NinjaTables\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * ClearNinjaTablesCache
 *
 * @category ClearNinjaTablesCache
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class ClearNinjaTablesCache extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'NinjaTables';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'ninja_tables_clear_cache';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Clear Ninja Tables Cache', 'suretriggers' ),
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
	 * @throws Exception Exception.
	 *
	 * @return bool|array|void
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$table_id = isset( $selected_options['table_id'] ) ? absint( $selected_options['table_id'] ) : 0;

		if ( 0 === $table_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Invalid table ID provided.', 'suretriggers' ),
			];
		}

		$table = get_post( $table_id );

		if ( ! $table || 'ninja-table' !== $table->post_type ) {
			return [
				'status'  => 'error',
				'message' => 'No Ninja Table exists with ID ' . $table_id,
			];
		}

		do_action( 'ninja_tables_after_table_settings_update', $table_id );

		return [
			'status'   => 'success',
			'table_id' => $table_id,
			'message'  => 'Cache cleared successfully for table ID ' . $table_id,
		];
	}
}

ClearNinjaTablesCache::get_instance();
