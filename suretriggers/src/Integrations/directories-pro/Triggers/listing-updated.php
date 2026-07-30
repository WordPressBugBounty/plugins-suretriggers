<?php
/**
 * ListingUpdated
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

if ( ! class_exists( 'ListingUpdated' ) ) :

	/**
	 * ListingUpdated
	 *
	 * @category ListingUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.26
	 */
	class ListingUpdated {

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
		public $trigger = 'directories_pro_listing_updated';

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
				'label'         => __( 'Listing Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'save_post',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 * @param bool     $update  Whether this is an existing post being updated.
		 * @return void
		 */
		public function trigger_listener( $post_id, $post, $update ) {
			if ( ! $update || empty( $post ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
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

	ListingUpdated::get_instance();

endif;
