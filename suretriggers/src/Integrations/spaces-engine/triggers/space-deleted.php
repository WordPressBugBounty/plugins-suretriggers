<?php
/**
 * SpaceDeleted.
 * php version 5.6
 *
 * @category SpaceDeleted
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

if ( ! class_exists( 'SpaceDeleted' ) ) :

	/**
	 * SpaceDeleted
	 *
	 * @category SpaceDeleted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class SpaceDeleted {

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
		public $trigger = 'spaces_engine_space_deleted';

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
				'label'         => __( 'Space deleted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'before_delete_post',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 * @return void
		 */
		public function trigger_listener( $post_id, $post ) {
			if ( 'wpe_wpspace' !== $post->post_type ) {
				return;
			}

			$user_id = (int) $post->post_author;
			$context = WordPress::get_post_context( $post_id );
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
	SpaceDeleted::get_instance();

endif;
