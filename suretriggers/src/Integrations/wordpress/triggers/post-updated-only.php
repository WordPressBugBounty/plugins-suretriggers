<?php
/**
 * PostUpdated.
 * php version 5.6
 *
 * @category PostUpdated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Wordpress\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;
use WP_Post;

if ( ! class_exists( 'PostUpdated' ) ) :

	/**
	 * PostUpdated
	 *
	 * @category PostUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 */
	class PostUpdated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'WordPress';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wp_post_updated_only';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'A post is updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'post_updated',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int     $post_id     Post ID.
		 * @param WP_Post $post_after  Post object after the update.
		 * @param WP_Post $post_before Post object before the update.
		 * @return void
		 */
		public function trigger_listener( $post_id, $post_after, $post_before ) {
			if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
				return;
			}

			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
				return;
			}

			if ( 'auto-draft' === $post_after->post_status ) {
				return;
			}

			// Skip if the old status was auto-draft or new — that's a creation, not an update.
			if ( 'auto-draft' === $post_before->post_status || 'new' === $post_before->post_status ) {
				return;
			}

			$user_id              = ap_get_current_user_id();
			$context              = WordPress::get_post_context( $post_id );
			$context['permalink'] = get_permalink( $post_id );

			$featured_image = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post_id ), 'full' );
			if ( ! empty( $featured_image ) && is_array( $featured_image ) ) {
				$context['featured_image'] = $featured_image[0];
			} else {
				$context['featured_image'] = $featured_image;
			}

			$taxonomies = get_object_taxonomies( $post_after, 'objects' );
			if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
					$terms = get_the_terms( $post_id, $taxonomy );
					if ( ! empty( $terms ) && is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$context[ $taxonomy ] = $term->name;
						}
					}
				}
			}

			$context                      = array_merge( $context, WordPress::get_user_context( $user_id ) );
			$context['post']              = $post_id;
			$context['post_type']         = $post_after->post_type;
			$custom_metas                 = get_post_meta( $post_id );
			$context['custom_metas']      = $custom_metas;
			$context['old_post_title']    = $post_before->post_title;
			$context['old_post_content']  = $post_before->post_content;
			$context['old_post_status']   = $post_before->post_status;
			$context['old_post_excerpt']  = $post_before->post_excerpt;
			$context['old_post_date']     = $post_before->post_date;
			$context['old_post_modified'] = $post_before->post_modified;

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	PostUpdated::get_instance();

endif;
