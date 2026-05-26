<?php
/**
 * UpdateSpace.
 * php version 5.6
 *
 * @category UpdateSpace
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
 * UpdateSpace
 *
 * @category UpdateSpace
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateSpace extends AutomateAction {

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
	public $action = 'spaces_engine_update_space';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update a Space', 'suretriggers' ),
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

		$args = [ 'ID' => $space_id ];

		if ( ! empty( $selected_options['space_title'] ) ) {
			$args['post_title'] = sanitize_text_field( $selected_options['space_title'] );
		}

		if ( isset( $selected_options['space_description'] ) && '' !== $selected_options['space_description'] ) {
			$args['post_content'] = wp_kses_post( $selected_options['space_description'] );
			update_post_meta( $space_id, 'wpe_wps_short_description', sanitize_text_field( $selected_options['space_description'] ) );
		}

		$result = wp_update_post( $args, true );

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		if ( isset( $selected_options['space_category'] ) ) {
			$category = is_array( $selected_options['space_category'] )
				? array_map( 'intval', $selected_options['space_category'] )
				: [ absint( $selected_options['space_category'] ) ];
			wp_set_post_terms( $space_id, $category, 'wp_space_category', false );
		}

		$context   = WordPress::get_post_context( $space_id );
		$author_id = (int) $post->post_author;
		$context   = array_merge( $context, WordPress::get_user_context( $author_id ) );

		return $context;
	}
}

UpdateSpace::get_instance();
