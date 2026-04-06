<?php
/**
 * RedirectToCustomUrl.
 * php version 5.6
 *
 * @category RedirectToCustomUrl
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentForm\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * RedirectToCustomUrl
 *
 * @category RedirectToCustomUrl
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RedirectToCustomUrl extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentForm';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fluentform_redirect_to_custom_url';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Redirect to Custom URL', 'suretriggers' ),
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
	 * @param array $selected_options selected options.
	 *
	 * @return array
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'FLUENTFORM' ) ) {
			throw new Exception( 'Fluent Forms plugin is not active.' );
		}

		$form_id = isset( $selected_options['form_id'] ) ? absint( $selected_options['form_id'] ) : 0;

		if ( empty( $form_id ) ) {
			return [
				'status'  => 'error',
				'message' => 'Form ID is required.',
			];
		}

		$redirect_url = isset( $selected_options['redirect_url'] ) ? $selected_options['redirect_url'] : '';

		if ( empty( $redirect_url ) ) {
			return [
				'status'  => 'error',
				'message' => 'Redirect URL is required.',
			];
		}

		// Do not esc_url_raw() — URL may contain Fluent Forms placeholders like {submission.id}.
		$redirect_url = sanitize_text_field( $redirect_url );

		$override = [
			'type'         => 'redirect',
			'redirect_url' => $redirect_url,
		];

		if ( isset( $selected_options['redirect_message'] ) && ! empty( $selected_options['redirect_message'] ) ) {
			$override['redirect_message'] = wp_kses_post( $selected_options['redirect_message'] );
		}

		\SureTriggers\Integrations\FluentForm\ottokit_ff_save_override( $form_id, $override );

		return [
			'status'       => 'success',
			'message'      => 'Redirect URL has been set for form.',
			'form_id'      => $form_id,
			'redirect_url' => $redirect_url,
		];
	}
}

RedirectToCustomUrl::get_instance();
