<?php
/**
 * CreateSnippet.
 * php version 5.6
 *
 * @category CreateSnippet
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
 * CreateSnippet
 *
 * @category CreateSnippet
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateSnippet extends AutomateAction {

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
	public $action = 'code_snippets_create_snippet';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create a Snippet', 'suretriggers' ),
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
		if ( ! function_exists( 'Code_Snippets\save_snippet' ) || ! class_exists( 'Code_Snippets\Snippet' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Code Snippets plugin is not active.', 'suretriggers' ),
			];
		}

		$snippet_name = isset( $selected_options['snippet_name'] ) ? sanitize_text_field( $selected_options['snippet_name'] ) : '';

		if ( empty( $snippet_name ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Snippet name is required.', 'suretriggers' ),
			];
		}

		$snippet_code = isset( $selected_options['snippet_code'] ) ? $selected_options['snippet_code'] : '';

		if ( empty( $snippet_code ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Snippet code is required.', 'suretriggers' ),
			];
		}

		$allowed_scopes = [ 'global', 'admin', 'front-end', 'single-use', 'head-content', 'footer-content' ];
		$scope          = isset( $selected_options['snippet_scope'] ) ? sanitize_text_field( $selected_options['snippet_scope'] ) : 'global';

		if ( ! in_array( $scope, $allowed_scopes, true ) ) {
			$scope = 'global';
		}

		$snippet_data = [
			'name'     => $snippet_name,
			'code'     => $snippet_code,
			'desc'     => isset( $selected_options['snippet_description'] ) ? sanitize_textarea_field( $selected_options['snippet_description'] ) : '',
			'scope'    => $scope,
			'tags'     => isset( $selected_options['snippet_tags'] ) ? array_map( 'trim', explode( ',', sanitize_text_field( $selected_options['snippet_tags'] ) ) ) : [],
			'priority' => isset( $selected_options['snippet_priority'] ) ? absint( $selected_options['snippet_priority'] ) : 10,
			'active'   => false,
		];

		$snippet = new \Code_Snippets\Snippet( $snippet_data );
		$result  = \Code_Snippets\save_snippet( $snippet );

		if ( ! is_object( $result ) || empty( $result->id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create snippet.', 'suretriggers' ),
			];
		}

		return [
			'snippet_id'          => absint( $result->id ),
			'snippet_name'        => isset( $result->name ) ? sanitize_text_field( $result->name ) : '',
			'snippet_description' => isset( $result->desc ) ? wp_kses_post( $result->desc ) : '',
			'snippet_code'        => isset( $result->code ) ? $result->code : '',
			'snippet_scope'       => isset( $result->scope ) ? sanitize_text_field( $result->scope ) : '',
			'snippet_tags'        => isset( $result->tags ) && is_array( $result->tags ) ? sanitize_text_field( implode( ', ', $result->tags ) ) : '',
			'snippet_priority'    => isset( $result->priority ) ? absint( $result->priority ) : 10,
			'snippet_active'      => false,
		];
	}
}

CreateSnippet::get_instance();
