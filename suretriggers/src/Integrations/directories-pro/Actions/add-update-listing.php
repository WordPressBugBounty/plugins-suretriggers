<?php
/**
 * AddUpdateListing.
 * php version 5.6
 *
 * @category AddUpdateListing
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.26
 */

namespace SureTriggers\Integrations\DirectoriesPro\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\DirectoriesPro\DirectoriesPro;
use SureTriggers\Traits\SingletonLoader;

/**
 * AddUpdateListing
 *
 * @category AddUpdateListing
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.26
 */
class AddUpdateListing extends AutomateAction {

	use SingletonLoader;

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'DirectoriesPro';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'directories_pro_add_update_listing';

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add/Update Listing', 'suretriggers' ),
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
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'drts' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Directories Pro is not installed or activated.', 'suretriggers' ),
			];
		}

		$listing_id = ! empty( $selected_options['listing_id'] ) ? absint( $selected_options['listing_id'] ) : 0;

		if ( $listing_id ) {
			return $this->update_listing( $listing_id, $selected_options );
		}

		return $this->add_listing( $selected_options, absint( $user_id ) );
	}

	/**
	 * Create a new listing.
	 *
	 * @param array $selected_options selectedOptions.
	 * @param int   $user_id The automation's associated WP user ID, used as the default author.
	 * @return array
	 */
	private function add_listing( $selected_options, $user_id ) {
		$post_type = ! empty( $selected_options['post_type'] ) ? sanitize_key( $selected_options['post_type'] ) : '';

		if ( ! $post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Listing directory (post type) is required.', 'suretriggers' ),
			];
		}

		$bundle = DirectoriesPro::get_bundle( $post_type );
		if ( ! DirectoriesPro::is_listing_bundle( $bundle ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'The given post type is not a valid Directories Pro listing directory.', 'suretriggers' ),
			];
		}

		$status = ! empty( $selected_options['status'] ) ? sanitize_key( $selected_options['status'] ) : 'pending';
		if ( ! in_array( $status, get_post_stati(), true ) ) {
			$status = 'pending';
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => $post_type,
				'post_title'   => ! empty( $selected_options['title'] ) ? sanitize_text_field( $selected_options['title'] ) : '',
				'post_content' => ! empty( $selected_options['content'] ) ? wp_kses_post( $selected_options['content'] ) : '',
				'post_status'  => $status,
				'post_author'  => ! empty( $selected_options['author_id'] ) ? absint( $selected_options['author_id'] ) : $user_id,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return [
				'status'  => 'error',
				'message' => $post_id->get_error_message(),
			];
		}

		$post = get_post( $post_id );

		return DirectoriesPro::build_entity_context( $post, $bundle );
	}

	/**
	 * Update an existing listing.
	 *
	 * @param int   $listing_id Listing post ID.
	 * @param array $selected_options selectedOptions.
	 * @return array
	 */
	private function update_listing( $listing_id, $selected_options ) {
		$post = get_post( $listing_id );
		if ( ! $post ) {
			return [
				'status'  => 'error',
				'message' => __( 'Listing not found.', 'suretriggers' ),
			];
		}

		$bundle = DirectoriesPro::get_bundle( $post->post_type );
		if ( ! DirectoriesPro::is_listing_bundle( $bundle ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'The given ID does not belong to a Directories Pro listing.', 'suretriggers' ),
			];
		}

		$postarr = [ 'ID' => $listing_id ];
		if ( isset( $selected_options['title'] ) && '' !== $selected_options['title'] ) {
			$postarr['post_title'] = sanitize_text_field( $selected_options['title'] );
		}
		if ( isset( $selected_options['content'] ) && '' !== $selected_options['content'] ) {
			$postarr['post_content'] = wp_kses_post( $selected_options['content'] );
		}
		if ( ! empty( $selected_options['status'] ) ) {
			$status = sanitize_key( $selected_options['status'] );
			if ( in_array( $status, get_post_stati(), true ) ) {
				$postarr['post_status'] = $status;
			}
		}
		if ( ! empty( $selected_options['author_id'] ) ) {
			$postarr['post_author'] = absint( $selected_options['author_id'] );
		}

		$updated = wp_update_post( $postarr, true );

		if ( is_wp_error( $updated ) ) {
			return [
				'status'  => 'error',
				'message' => $updated->get_error_message(),
			];
		}

		$post = get_post( $listing_id );

		return DirectoriesPro::build_entity_context( $post, $bundle );
	}
}

AddUpdateListing::get_instance();
