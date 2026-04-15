<?php
/**
 * DeactivateSnippet.
 * php version 5.6
 *
 * @category DeactivateSnippet
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\CodeSnippets\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * DeactivateSnippet
 *
 * @category DeactivateSnippet
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeactivateSnippet extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'CodeSnippets';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'code_snippets_deactivate_snippet';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Deactivate a Snippet', 'suretriggers' ),
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
	 *
	 * @return array|void
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'Code_Snippets\deactivate_snippet' ) || ! function_exists( 'Code_Snippets\get_snippet' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Code Snippets plugin is not active.', 'suretriggers' ),
			];
		}

		$snippet_id = isset( $selected_options['snippet_id'] ) ? absint( $selected_options['snippet_id'] ) : 0;

		if ( empty( $snippet_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Snippet ID is required.', 'suretriggers' ),
			];
		}

		$snippet = \Code_Snippets\get_snippet( $snippet_id );

		if ( ! is_object( $snippet ) || empty( $snippet->id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Snippet not found with ID: ', 'suretriggers' ) . $snippet_id,
			];
		}

		$result = \Code_Snippets\deactivate_snippet( $snippet_id );

		if ( ! $result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to deactivate snippet with ID: ', 'suretriggers' ) . $snippet_id,
			];
		}

		return [
			'snippet_id'    => absint( $snippet->id ),
			'snippet_name'  => isset( $snippet->name ) ? sanitize_text_field( $snippet->name ) : '',
			'snippet_scope' => isset( $snippet->scope ) ? sanitize_text_field( $snippet->scope ) : '',
			'status'        => 'deactivated',
		];
	}
}

DeactivateSnippet::get_instance();
