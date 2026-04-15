<?php
/**
 * SureDashAddCommentToPost.
 * php version 5.6
 *
 * @category SureDashAddCommentToPost
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDash\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * SureDashAddCommentToPost
 *
 * @category SureDashAddCommentToPost
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashAddCommentToPost extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDash';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'suredash_add_comment_to_post';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add Comment to Post', 'suretriggers' ),
			'action'   => 'suredash_add_comment_to_post',
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array|bool
	 * @throws Exception Error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'SUREDASHBOARD_FEED_POST_TYPE' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash plugin is not active or properly configured.', 'suretriggers' ),
			];
		}

		$post_id         = ! empty( $selected_options['post_id'] ) ? absint( $selected_options['post_id'] ) : 0;
		$comment_content = ! empty( $selected_options['comment_content'] ) ? $selected_options['comment_content'] : '';

		if ( empty( $post_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Post ID is required.', 'suretriggers' ),
			];
		}

		if ( empty( $comment_content ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Comment content is required.', 'suretriggers' ),
			];
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return [
				'status'  => 'error',
				'message' => __( 'Post not found with the provided ID.', 'suretriggers' ),
			];
		}

		// Resolve comment author from email, automation user, or current user.
		$comment_author       = '';
		$comment_author_email = '';
		$comment_user_id      = 0;

		if ( ! empty( $selected_options['comment_author_email'] ) ) {
			$author_email = sanitize_email( $selected_options['comment_author_email'] );
			$author_user  = get_user_by( 'email', $author_email );

			if ( $author_user ) {
				$comment_author       = sanitize_text_field( $author_user->display_name );
				$comment_author_email = $author_user->user_email;
				$comment_user_id      = $author_user->ID;
			} else {
				$comment_author_email = $author_email;
				$comment_author       = $author_email;
			}
		} else {
			$fallback_user_id = $user_id ? $user_id : get_current_user_id();
			$fallback_user    = $fallback_user_id ? get_user_by( 'id', $fallback_user_id ) : false;

			if ( $fallback_user ) {
				$comment_author       = sanitize_text_field( $fallback_user->display_name );
				$comment_author_email = $fallback_user->user_email;
				$comment_user_id      = $fallback_user->ID;
			}
		}

		if ( empty( $comment_author_email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Comment author email is required. Please provide a valid email or ensure a logged-in user exists.', 'suretriggers' ),
			];
		}

		$comment_data = [
			'comment_post_ID'      => $post_id,
			'comment_content'      => wp_kses_post( $comment_content ),
			'comment_author'       => $comment_author,
			'comment_author_email' => $comment_author_email,
			'user_id'              => $comment_user_id,
			'comment_approved'     => 1,
		];

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			$this->set_error(
				[
					'msg' => __( 'Failed to add comment to the post.', 'suretriggers' ),
				]
			);
			return false;
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment ) {
			return [
				'status'  => 'error',
				'message' => __( 'Comment was created but could not be retrieved.', 'suretriggers' ),
			];
		}

		return [
			'status'               => 'success',
			'message'              => __( 'Comment added to post successfully.', 'suretriggers' ),
			'comment_id'           => $comment->comment_ID,
			'comment_content'      => $comment->comment_content,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_date'         => $comment->comment_date,
			'comment_approved'     => $comment->comment_approved,
			'post_id'              => $post_id,
			'post_title'           => $post->post_title,
			'post_permalink'       => get_permalink( $post_id ),
		];
	}
}

SureDashAddCommentToPost::get_instance();
