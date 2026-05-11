<?php
/**
 * EventCancelled.
 * php version 5.6
 *
 * @category EventCancelled
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

if ( ! class_exists( 'EventOnEventCancelled' ) ) :

	/**
	 * EventOnEventCancelled
	 *
	 * @category EventOnEventCancelled
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class EventOnEventCancelled {

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
		public $trigger = 'eon_event_cancelled';

		use SingletonLoader;

		/**
		 * Previous internal status per post ID, captured before meta is written.
		 *
		 * @var array<int,string>
		 */
		private $previous_status = [];

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );

			// Capture the previous internal status before EventOn writes the new one.
			add_action( 'pre_post_update', [ $this, 'capture_previous_status' ], 10, 1 );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Event Cancelled', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'eventon_save_meta',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 30,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Capture the previous _status value before the event meta is saved.
		 *
		 * @param int $post_id Post ID being updated.
		 * @return void
		 */
		public function capture_previous_status( $post_id ) {
			if ( ! is_numeric( $post_id ) ) {
				return;
			}

			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post || 'ajde_events' !== $post->post_type ) {
				return;
			}

			$previous                          = get_post_meta( $post_id, '_status', true );
			$this->previous_status[ $post_id ] = is_string( $previous ) ? $previous : '';
		}

		/**
		 * Trigger listener.
		 *
		 * Fires after EventOn meta has been saved. Only triggers when the internal
		 * `_status` meta transitioned into `cancelled`.
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
			$post    = get_post( $post_id );
			if ( ! $post || 'ajde_events' !== $post->post_type ) {
				return;
			}

			$current_status = get_post_meta( $post_id, '_status', true );
			if ( 'cancelled' !== $current_status ) {
				return;
			}

			$previous = isset( $this->previous_status[ $post_id ] ) ? $this->previous_status[ $post_id ] : '';
			if ( 'cancelled' === $previous ) {
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
	EventOnEventCancelled::get_instance();

endif;
