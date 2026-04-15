<?php
/**
 * RedirectionToggleRedirect.
 * php version 5.6
 *
 * @category RedirectionToggleRedirect
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
 * RedirectionToggleRedirect
 *
 * @category RedirectionToggleRedirect
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RedirectionToggleRedirect extends AutomateAction {

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
	public $action = 'rd_toggle_redirect';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Enable or Disable Redirect', 'suretriggers' ),
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

		$redirect_id = isset( $selected_options['redirect_id'] ) ? absint( $selected_options['redirect_id'] ) : 0;
		$toggle      = isset( $selected_options['toggle'] ) ? sanitize_text_field( $selected_options['toggle'] ) : '';

		if ( empty( $redirect_id ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Redirect ID is required.', 'suretriggers' ),
			];
		}

		if ( ! in_array( $toggle, [ 'enable', 'disable' ], true ) ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Toggle value must be "enable" or "disable".', 'suretriggers' ),
			];
		}

		$redirect = \Red_Item::get_by_id( $redirect_id );
		if ( false === $redirect ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Redirect not found with the specified ID.', 'suretriggers' ),
			];
		}

		if ( 'enable' === $toggle ) {
			$redirect->enable();
		} else {
			$redirect->disable();
		}

		$updated_redirect = \Red_Item::get_by_id( $redirect_id );
		if ( false === $updated_redirect ) {
			return [
				'status'   => esc_attr__( 'Error', 'suretriggers' ),
				'response' => esc_attr__( 'Failed to retrieve updated redirect.', 'suretriggers' ),
			];
		}

		return [
			'status'   => esc_attr__( 'Success', 'suretriggers' ),
			'response' => 'enable' === $toggle
				? esc_attr__( 'Redirect enabled successfully.', 'suretriggers' )
				: esc_attr__( 'Redirect disabled successfully.', 'suretriggers' ),
			'redirect' => Redirection::get_redirect_context( $updated_redirect ),
		];
	}
}

RedirectionToggleRedirect::get_instance();
