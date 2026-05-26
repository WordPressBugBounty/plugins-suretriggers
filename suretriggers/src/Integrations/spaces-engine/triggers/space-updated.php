<?php
/**
 * SpaceUpdated.
 * php version 5.6
 *
 * @category SpaceUpdated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SpacesEngine\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'SpaceUpdated' ) ) :

	/**
	 * SpaceUpdated
	 *
	 * @category SpaceUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class SpaceUpdated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'SpacesEngine';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'spaces_engine_space_updated';

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
				'label'         => __( 'Space updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'post_updated',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int      $post_id     Post ID.
		 * @param \WP_Post $post_after  Post object after update.
		 * @param \WP_Post $post_before Post object before update.
		 * @return void
		 */
		public function trigger_listener( $post_id, $post_after, $post_before ) {
			if ( 'wpe_wpspace' !== $post_after->post_type ) {
				return;
			}

			if ( 'publish' !== $post_after->post_status || 'publish' !== $post_before->post_status ) {
				return;
			}

			$user_id = (int) $post_after->post_author;
			$context = WordPress::get_post_context( $post_id );
			$terms   = get_the_terms( $post_id, 'wp_space_category' );

			$context['categories'] = ( empty( $terms ) || is_wp_error( $terms ) ) ? [] : wp_list_pluck( $terms, 'name' );

			$context = array_merge( $context, WordPress::get_user_context( $user_id ) );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	SpaceUpdated::get_instance();

endif;
