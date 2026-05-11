<?php
/**
 * EventPublished.
 * php version 5.6
 *
 * @category EventPublished
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventOn\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\EventOn\EventOn;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;
use WP_Post;

if ( ! class_exists( 'EventOnEventPublished' ) ) :

	/**
	 * EventOnEventPublished
	 *
	 * @category EventOnEventPublished
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EventOnEventPublished {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'EventOn';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'eon_event_published';

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
				'label'         => __( 'Event Published', 'suretriggers' ),
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
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post Post object.
		 *
		 * @return void
		 */
		public function trigger_listener( $new_status, $old_status, $post ) {
			if ( ! $post instanceof WP_Post ) {
				return;
			}

			if ( 'ajde_events' !== $post->post_type ) {
				return;
			}

			if ( 'publish' !== $new_status || 'publish' === $old_status ) {
				return;
			}

			$context = EventOn::get_event_context( $post->ID );
			if ( empty( $context ) ) {
				return;
			}

			$author_id = (int) $post->post_author;
			if ( ! empty( $author_id ) ) {
				$context = array_merge( WordPress::get_user_context( $author_id ), $context );
			}

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
	EventOnEventPublished::get_instance();

endif;
