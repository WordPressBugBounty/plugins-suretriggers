<?php
/**
 * SendEmail.
 * php version 5.6
 *
 * @category SendEmail
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Voxel\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * SendEmail
 *
 * @category SendEmail
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SendEmail extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Voxel';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'voxel_send_email';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Send Email', 'suretriggers' ),
			'action'   => 'voxel_send_email',
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
	 * @throws Exception Exception.
	 * 
	 * @return bool|array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$message   = $selected_options['message'];
		$subject   = $selected_options['subject'];
		$recipient = $selected_options['wp_user_email'];

		if ( ! class_exists( 'Voxel\Queues\Async_Email' ) ) {
			return false;
		}

		if ( ! is_email( $recipient ) ) {
			return [
				'status'  => 'error',
				'message' => 'Please enter valid email address.',
			];
		}

		$downloaded = WordPress::download_attachments( isset( $selected_options['attachment_url'] ) ? $selected_options['attachment_url'] : '' );

		if ( ! empty( $downloaded['attachments'] ) ) {
			// Voxel's Async_Email queue does not forward attachments to wp_mail(), so send synchronously instead.
			$headers = [ 'Content-type: text/html; charset=UTF-8' ];
			$body    = function_exists( 'Voxel\email_template' ) ? \Voxel\email_template( $message ) : $message;
			$email   = wp_mail( $recipient, $subject, $body, $headers, $downloaded['attachments'] ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail

			WordPress::cleanup_attachments( $downloaded['attachments'] );
		} else {
			$args  = [
				'emails' => [
					[
						'recipient' => $recipient,
						'subject'   => $subject,
						'message'   => $message,
						'headers'   => [
							'Content-type: text/html;',
						],
					],
				],
			];
			$email = \Voxel\Queues\Async_Email::instance()->data( $args )->dispatch();
		}

		if ( ! $email ) {
			return [
				'status'  => 'error',
				'message' => 'Email not sent',
			];
		}

		$response = [
			'success' => true,
			'message' => esc_attr__( 'Email sent successfully', 'suretriggers' ),
		];

		if ( $downloaded['skipped'] > 0 ) {
			$response['attachments_skipped'] = $downloaded['skipped'];
		}

		return $response;
	}

}

SendEmail::get_instance();
