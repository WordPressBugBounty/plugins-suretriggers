<?php
/**
 * RedirectionCreateRoleRedirect.
 * php version 5.6
 *
 * @category RedirectionCreateRoleRedirect
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
 * RedirectionCreateRoleRedirect
 *
 * @category RedirectionCreateRoleRedirect
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RedirectionCreateRoleRedirect extends AutomateAction {

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
	public $action = 'rd_create_role_redirect';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Role-Based Redirect', 'suretriggers' ),
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
		$role        = isset( $selected_options['role'] ) ? sanitize_text_field( $selected_options['role'] ) : '';
		$url_from    = isset( $selected_options['url_from'] ) ? sanitize_text_field( $selected_options['url_from'] ) : '';
		$url_notfrom = isset( $selected_options['url_notfrom'] ) ? sanitize_text_field( $selected_options['url_notfrom'] ) : '';
		$action_code = isset( $selected_options['action_code'] ) ? absint( $selected_options['action_code'] ) : 302;
		$title       = isset( $selected_options['title'] ) ? sanitize_text_field( $selected_options['title'] ) : '';

		// Block dangerous URL protocols.
		$dangerous_protocol = '/^\s*(javascript|data|vbscript)\s*:/i';
		if ( preg_match( $dangerous_protocol, $source_url ) || preg_match( $dangerous_protocol, $url_from ) || preg_match( $dangerous_protocol, $url_notfrom ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Invalid URL protocol.', 'suretriggers' ),
			];
		}

		// Validate HTTP status code.
		$allowed_codes = [ 301, 302, 303, 304, 307, 308, 400, 403, 404, 410 ];
		if ( ! in_array( $action_code, $allowed_codes, true ) ) {
			$action_code = 302;
		}

		if ( empty( $source_url ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Source URL is required.', 'suretriggers' ),
			];
		}

		if ( empty( $role ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'User role or capability is required.', 'suretriggers' ),
			];
		}

		if ( empty( $url_from ) && empty( $url_notfrom ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'At least one target URL (matched or unmatched role) is required.', 'suretriggers' ),
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
					'role'        => $role,
					'url_from'    => $url_from,
					'url_notfrom' => $url_notfrom,
				],
				'action_type' => 'url',
				'action_code' => $action_code,
				'match_type'  => 'role',
				'title'       => $title,
				'group_id'    => $group_id,
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
			'response' => esc_attr__( 'Role-based redirect created successfully.', 'suretriggers' ),
			'redirect' => Redirection::get_redirect_context( $redirect ),
		];
	}
}

RedirectionCreateRoleRedirect::get_instance();
