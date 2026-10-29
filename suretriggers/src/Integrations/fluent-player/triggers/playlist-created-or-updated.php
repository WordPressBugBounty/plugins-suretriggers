<?php
/**
 * PlaylistCreatedOrUpdated.
 * php version 5.6
 *
 * @category PlaylistCreatedOrUpdated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentPlayer\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\FluentPlayer\FluentPlayer;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'PlaylistCreatedOrUpdated' ) ) :

	/**
	 * PlaylistCreatedOrUpdated
	 *
	 * @category PlaylistCreatedOrUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class PlaylistCreatedOrUpdated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentPlayer';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fluent_player_playlist_created_or_updated';

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
				'label'         => __( 'Playlist Created or Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [ 'save_post_fluent_playlist' ],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int           $post_id post_id.
		 * @param \WP_Post|null $post post.
		 * @param bool          $update update.
		 * @return void
		 */
		public function trigger_listener( $post_id, $post = null, $update = false ) {
			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( $post instanceof \WP_Post && 'auto-draft' === $post->post_status ) ) {
				return;
			}
			$context              = FluentPlayer::get_post_context( absint( $post_id ), 'playlist' );
			$context['is_update'] = (bool) $update;

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
	PlaylistCreatedOrUpdated::get_instance();

endif;
