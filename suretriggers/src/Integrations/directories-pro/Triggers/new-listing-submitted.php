<?php
/**
 * NewListingSubmitted
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

if ( ! class_exists( 'NewListingSubmitted' ) ) :

	/**
	 * NewListingSubmitted
	 *
	 * @category NewListingSubmitted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.26
	 */
	class NewListingSubmitted {

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
		public $trigger = 'directories_pro_new_listing_submitted';

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
				'label'         => __( 'New Listing Submitted', 'suretriggers' ),
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

			// Only the transition out of a placeholder/non-existent state represents a genuine
			// first-time submission. wp-admin creates an 'auto-draft' the instant the "Add New"
			// screen loads (a real $update=false wp_insert_post() call) — without this guard the
			// trigger would fire on that empty placeholder instead of the real, filled-in submission.
			if ( ! in_array( $old_status, [ 'new', 'auto-draft' ], true ) ) {
				return;
			}

			if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
				return;
			}

			$bundle = DirectoriesPro::get_bundle( $post->post_type );
			if ( ! DirectoriesPro::is_listing_bundle( $bundle ) ) {
				return;
			}

			$context = DirectoriesPro::build_entity_context( $post, $bundle );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	NewListingSubmitted::get_instance();

endif;
