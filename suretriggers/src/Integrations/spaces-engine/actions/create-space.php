<?php
/**
 * CreateSpace.
 * php version 5.6
 *
 * @category CreateSpace
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
 * CreateSpace
 *
 * @category CreateSpace
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateSpace extends AutomateAction {

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
	public $action = 'spaces_engine_create_space';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create a Space', 'suretriggers' ),
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
		$title        = isset( $selected_options['space_title'] ) ? sanitize_text_field( $selected_options['space_title'] ) : '';
		$description  = isset( $selected_options['space_description'] ) ? wp_kses_post( $selected_options['space_description'] ) : '';
		$author_email = isset( $selected_options['space_author_email'] ) ? sanitize_email( $selected_options['space_author_email'] ) : '';

		if ( empty( $title ) || empty( $description ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Space title and description are required.', 'suretriggers' ),
			];
		}

		$author_id = $user_id;
		if ( ! empty( $author_email ) && is_email( $author_email ) ) {
			$author = get_user_by( 'email', $author_email );
			if ( $author ) {
				$author_id = $author->ID;
			}
		}

		$args = [
			'post_title'   => $title,
			'post_type'    => 'wpe_wpspace',
			'post_status'  => 'publish',
			'post_content' => $description,
			'post_author'  => $author_id,
		];

		$space_id = wp_insert_post( $args, true );

		if ( is_wp_error( $space_id ) ) {
			throw new Exception( $space_id->get_error_message() );
		}

		$space_desc = isset( $selected_options['space_description'] ) ? $selected_options['space_description'] : '';
		add_post_meta( $space_id, 'wpe_wps_short_description', sanitize_text_field( $space_desc ) );

		if ( ! empty( $selected_options['space_category'] ) ) {
			$category = is_array( $selected_options['space_category'] )
				? array_map( 'intval', $selected_options['space_category'] )
				: [ absint( $selected_options['space_category'] ) ];
			wp_set_object_terms( $space_id, $category, 'wp_space_category' );
		}

		$context = WordPress::get_post_context( $space_id );
		$context = array_merge( $context, WordPress::get_user_context( $author_id ) );

		return $context;
	}
}

CreateSpace::get_instance();
