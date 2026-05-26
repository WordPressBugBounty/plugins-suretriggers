<?php
/**
 * DeleteSpace.
 * php version 5.6
 *
 * @category DeleteSpace
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SpacesEngine\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * DeleteSpace
 *
 * @category DeleteSpace
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class DeleteSpace extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SpacesEngine';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'spaces_engine_delete_space';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete a Space', 'suretriggers' ),
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
	 * @throws Exception Exception.
	 *
	 * @return bool|array|void
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$space_id = isset( $selected_options['space_id'] ) ? absint( $selected_options['space_id'] ) : 0;

		if ( ! $space_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'A valid Space ID is required.', 'suretriggers' ),
			];
		}

		$post = get_post( $space_id );

		if ( ! $post instanceof \WP_Post || 'wpe_wpspace' !== $post->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Space not found.', 'suretriggers' ),
			];
		}

		$author_id = (int) $post->post_author;
		$context   = WordPress::get_post_context( $space_id );
		$context   = array_merge( $context, WordPress::get_user_context( $author_id ) );

		$deleted = wp_delete_post( $space_id, true );

		if ( ! $deleted ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to delete the Space.', 'suretriggers' ),
			];
		}

		$context['deleted'] = true;

		return $context;
	}
}

DeleteSpace::get_instance();
