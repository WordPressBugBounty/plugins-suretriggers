<?php
/**
 * ListingPublished
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

if ( ! class_exists( 'ListingPublished' ) ) :

	/**
	 * ListingPublished
	 *
	 * @category ListingPublished
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.26
	 */
	class ListingPublished {

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
		public $trigger = 'directories_pro_listing_published';

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
				'label'         => __( 'Listing Published', 'suretriggers' ),
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
			if ( 'publish' !== $new_status || 'publish' === $old_status || empty( $post ) ) {
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

	ListingPublished::get_instance();

endif;
