<?php
/**
 * EventUpdated.
 * php version 5.6
 *
 * @category EventUpdated
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

if ( ! class_exists( 'EventOnEventUpdated' ) ) :

	/**
	 * EventOnEventUpdated
	 *
	 * @category EventOnEventUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EventOnEventUpdated {

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
		public $trigger = 'eon_event_updated';

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
				'label'         => __( 'Event Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'eventon_save_meta',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 20,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * Fires after EventOn meta has been saved for an event.
		 *
		 * @param array $fields_ar Fields saved.
		 * @param int   $post_id   Event post ID.
		 * @param mixed $event     EventOn event object.
		 *
		 * @return void
		 */
		public function trigger_listener( $fields_ar, $post_id, $event ) {
			unset( $fields_ar, $event );

			if ( ! is_numeric( $post_id ) ) {
				return;
			}

			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				return;
			}

			$post = get_post( $post_id );
			if ( ! $post || 'ajde_events' !== $post->post_type ) {
				return;
			}

			// Skip the first insert — Event Published trigger covers that.
			if ( 'auto-draft' === $post->post_status ) {
				return;
			}

			$context = EventOn::get_event_context( $post_id );
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
	EventOnEventUpdated::get_instance();

endif;
