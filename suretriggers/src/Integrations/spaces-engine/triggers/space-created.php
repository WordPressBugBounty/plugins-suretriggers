<?php
/**
 * SpaceCreated.
 * php version 5.6
 *
 * @category SpaceCreated
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

if ( ! class_exists( 'SpaceCreated' ) ) :

	/**
	 * SpaceCreated
	 *
	 * @category SpaceCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class SpaceCreated {

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
		public $trigger = 'spaces_engine_space_created';

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
				'label'         => __( 'Space created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'transition_post_status',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param string   $new_status New post status.
		 * @param string   $old_status Old post status.
		 * @param \WP_Post $post       Post object.
		 * @return void
		 */
		public function trigger_listener( $new_status, $old_status, $post ) {
			if ( 'wpe_wpspace' !== $post->post_type ) {
				return;
			}

			if ( 'publish' !== $new_status || 'publish' === $old_status ) {
				return;
			}

			$post_id = $post->ID;
			$user_id = (int) $post->post_author;
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
	SpaceCreated::get_instance();

endif;
