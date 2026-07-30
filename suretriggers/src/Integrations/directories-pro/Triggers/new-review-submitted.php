<?php
/**
 * NewReviewSubmitted
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
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'NewReviewSubmitted' ) ) :

	/**
	 * NewReviewSubmitted
	 *
	 * @category NewReviewSubmitted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.26
	 */
	class NewReviewSubmitted {

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
		public $trigger = 'directories_pro_new_review_submitted';

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
				'label'         => __( 'New Review Submitted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'transition_post_status',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param string   $new_status New post status.
		 * @param string   $old_status Old post status.
		 * @param \WP_Post $post       Post object.
		 * @return void
		 */
		public function trigger_listener( $new_status, $old_status, $post ) {
			if ( empty( $post ) || 'auto-draft' === $new_status || 'trash' === $new_status ) {
				return;
			}

			// See NewListingSubmitted::trigger_listener() for why this guards on old_status
			// instead of save_post's $update flag (avoids firing on the wp-admin auto-draft).
			if ( ! in_array( $old_status, [ 'new', 'auto-draft' ], true ) ) {
				return;
			}

			if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
				return;
			}

			$bundle = DirectoriesPro::get_bundle( $post->post_type );
			if ( ! DirectoriesPro::is_review_bundle( $bundle ) ) {
				return;
			}

			$user   = WordPress::get_user_context( absint( $post->post_author ) );
			$parent = absint( $post->post_parent );

			$context = array_merge(
				$user,
				[
					'review_id'      => $post->ID,
					'review_title'   => $post->post_title,
					'review_content' => $post->post_content,
					'status'         => $post->post_status,
					'listing_id'     => $parent,
					'listing_title'  => $parent ? get_the_title( $parent ) : '',
					'listing_url'    => $parent ? get_permalink( $parent ) : '',
				]
			);

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	NewReviewSubmitted::get_instance();

endif;
