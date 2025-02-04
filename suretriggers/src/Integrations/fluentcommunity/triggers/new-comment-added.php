<?php
/**
 * NewCommentAdded.
 * php version 5.6
 *
 * @category NewCommentAdded
 * @package  SureTriggers
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentCommunity\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'NewCommentAdded' ) ) :

	/**
	 * NewCommentAdded
	 *
	 * @category NewCommentAdded
	 * @package  SureTriggers
	 * @since    1.0.0
	 */
	class NewCommentAdded {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'FluentCommunity';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'fc_new_comment_added';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			add_action( 'fluent_community/comment_added', [ $this, 'trigger_listener' ], 10, 3 );
		}

		/**
		 * Trigger listener.
		 *
		 * @param object $comment        The newly added comment object.
		 * @param object $feed           The associated feed object.
		 * @param array  $mentioned_users The users mentioned in the comment.
		 * @return void
		 */
		public function trigger_listener( $comment, $feed, $mentioned_users ) {

			if ( ! is_object( $comment ) || ! is_object( $feed ) ) {
				return;
			}
		
			$context = [
				'comment' => $comment,
			];

			// Handle the trigger.
			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	NewCommentAdded::get_instance();

endif;
