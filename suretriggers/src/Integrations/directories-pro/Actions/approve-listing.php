<?php
/**
 * ApproveListing.
 * php version 5.6
 *
 * @category ApproveListing
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
 * ApproveListing
 *
 * @category ApproveListing
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.26
 */
class ApproveListing extends AutomateAction {

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
	public $action = 'directories_pro_approve_listing';

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Approve Listing', 'suretriggers' ),
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

		if ( 'trash' === $post->post_status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Cannot approve a listing that has been deleted.', 'suretriggers' ),
			];
		}

		$updated = wp_update_post(
			[
				'ID'          => $listing_id,
				'post_status' => 'publish',
			],
			true
		);

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

ApproveListing::get_instance();
