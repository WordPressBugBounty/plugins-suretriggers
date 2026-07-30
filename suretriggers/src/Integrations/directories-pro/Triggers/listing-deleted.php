<?php
/**
 * ListingDeleted
 *
 * @package  SureTriggers
 * @category Integration
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.26
 */

namespace SureTriggers\Integrations\DirectoriesPro\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\DirectoriesPro\DirectoriesPro;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'ListingDeleted' ) ) :

	/**
	 * ListingDeleted
	 *
	 * @category ListingDeleted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.26
	 */
	class ListingDeleted {

		use SingletonLoader;

		/**
		 * Integration name.
		 *
		 * @var string
		 */
		public $integration = 'DirectoriesPro';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'directories_pro_listing_deleted';

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register trigger.
		 *
		 * @param array $triggers Registered triggers.
		 * @return array Modified triggers.
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Listing Deleted', 'suretriggers' ),
				'action'        => $this->trigger,
				// wp_trash_post: moved to trash. before_delete_post: permanently deleted
				// (either force-deleted directly, or emptied from trash) — both count as "deleted".
				'common_action' => [ 'wp_trash_post', 'before_delete_post' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int $post_id Post ID.
		 * @return void
		 */
		public function trigger_listener( $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id ) {
				return;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				return;
			}

			$bundle = DirectoriesPro::get_bundle( $post->post_type );
			if ( ! DirectoriesPro::is_listing_bundle( $bundle ) ) {
				return;
			}

			$context = DirectoriesPro::build_entity_context( $post, $bundle );

			// Both hooks fire *before* the post's row is actually updated/removed, so
			// $post->post_status here still reflects the pre-deletion state — override it
			// with the real outcome rather than leaving stale data (e.g. 'publish') in the context.
			if ( 'before_delete_post' === current_action() ) {
				$context['status']        = 'deleted';
				$context['deletion_type'] = 'permanent';
			} else {
				$context['status']        = 'trash';
				$context['deletion_type'] = 'trashed';
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	ListingDeleted::get_instance();

endif;
