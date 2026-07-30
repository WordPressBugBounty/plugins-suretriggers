<?php
/**
 * DeleteListing.
 * php version 5.6
 *
 * @category DeleteListing
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
 * DeleteListing
 *
 * @category DeleteListing
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.26
 */
class DeleteListing extends AutomateAction {

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
	public $action = 'directories_pro_delete_listing';

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Delete Listing', 'suretriggers' ),
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

		$listing_id   = ! empty( $selected_options['listing_id'] ) ? absint( $selected_options['listing_id'] ) : 0;
		$force_delete = ! empty( $selected_options['force_delete'] );

		if ( ! $listing_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Listing ID is required.', 'suretriggers' ),
			];
		}

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

		$context = DirectoriesPro::build_entity_context( $post, $bundle );
		$deleted = $force_delete ? wp_delete_post( $listing_id, true ) : wp_trash_post( $listing_id );

		if ( ! $deleted ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to delete the listing.', 'suretriggers' ),
			];
		}

		return $context;
	}
}

DeleteListing::get_instance();
