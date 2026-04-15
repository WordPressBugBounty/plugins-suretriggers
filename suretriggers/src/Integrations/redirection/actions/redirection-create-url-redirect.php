<?php
/**
 * RedirectionCreateUrlRedirect.
 * php version 5.6
 *
 * @category RedirectionCreateUrlRedirect
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Redirection\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\Redirection\Redirection;
use SureTriggers\Traits\SingletonLoader;

/**
 * RedirectionCreateUrlRedirect
 *
 * @category RedirectionCreateUrlRedirect
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RedirectionCreateUrlRedirect extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Redirection';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'rd_create_url_redirect';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create URL Redirect', 'suretriggers' ),
			'action'   => $this->action,
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
	 * @throws Exception Throws exception.
	 *
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'Red_Item' ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Redirection plugin is not active.', 'suretriggers' ),
			];
		}

		$source_url  = isset( $selected_options['source_url'] ) ? sanitize_text_field( $selected_options['source_url'] ) : '';
		$target_url  = isset( $selected_options['target_url'] ) ? sanitize_text_field( $selected_options['target_url'] ) : '';
		$action_code = isset( $selected_options['action_code'] ) ? absint( $selected_options['action_code'] ) : 301;
		$title       = isset( $selected_options['title'] ) ? sanitize_text_field( $selected_options['title'] ) : '';
		$regex       = isset( $selected_options['regex'] ) && 'true' === $selected_options['regex'] ? 1 : 0;

		// Block dangerous URL protocols.
		$dangerous_protocol = '/^\s*(javascript|data|vbscript)\s*:/i';
		if ( preg_match( $dangerous_protocol, $source_url ) || preg_match( $dangerous_protocol, $target_url ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Invalid URL protocol.', 'suretriggers' ),
			];
		}

		// Validate HTTP status code.
		$allowed_codes = [ 301, 302, 303, 304, 307, 308, 400, 403, 404, 410 ];
		if ( ! in_array( $action_code, $allowed_codes, true ) ) {
			$action_code = 301;
		}

		if ( empty( $source_url ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Source URL is required.', 'suretriggers' ),
			];
		}

		if ( empty( $target_url ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Target URL is required.', 'suretriggers' ),
			];
		}

		$group_id = Redirection::get_default_group_id();
		if ( false === $group_id ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'No redirect groups found. Please create a group in the Redirection plugin first.', 'suretriggers' ),
			];
		}

		$redirect = \Red_Item::create(
			[
				'url'         => $source_url,
				'action_data' => [
					'url' => $target_url,
				],
				'action_type' => 'url',
				'action_code' => $action_code,
				'match_type'  => 'url',
				'title'       => $title,
				'group_id'    => $group_id,
				'regex'       => $regex,
			]
		);

		if ( is_wp_error( $redirect ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => sanitize_text_field( $redirect->get_error_message() ),
			];
		}

		return [
			'status'   => esc_attr__( 'Success', 'suretriggers' ),
			'response' => esc_attr__( 'URL redirect created successfully.', 'suretriggers' ),
			'redirect' => Redirection::get_redirect_context( $redirect ),
		];
	}
}

RedirectionCreateUrlRedirect::get_instance();
