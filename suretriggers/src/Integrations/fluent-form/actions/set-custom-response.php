<?php
/**
 * SetCustomResponse.
 * php version 5.6
 *
 * @category SetCustomResponse
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
 * SetCustomResponse
 *
 * @category SetCustomResponse
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SetCustomResponse extends AutomateAction {

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
	public $action = 'fluentform_set_custom_response';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Set Custom Response', 'suretriggers' ),
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

		$custom_message = isset( $selected_options['custom_message'] ) ? $selected_options['custom_message'] : '';

		if ( empty( $custom_message ) ) {
			return [
				'status'  => 'error',
				'message' => 'Custom response message is required.',
			];
		}

		$form_behavior = isset( $selected_options['form_behavior'] ) ? sanitize_text_field( $selected_options['form_behavior'] ) : 'hide_form';
		if ( ! in_array( $form_behavior, [ 'hide_form', 'reset_form' ], true ) ) {
			$form_behavior = 'hide_form';
		}

		$override = [
			'type'          => 'custom_message',
			'message'       => wp_kses_post( $custom_message ),
			'form_behavior' => $form_behavior,
		];

		\SureTriggers\Integrations\FluentForm\ottokit_ff_save_override( $form_id, $override );

		return [
			'status'        => 'success',
			'message'       => 'Custom response has been set for form.',
			'form_id'       => $form_id,
			'response'      => $override['message'],
			'form_behavior' => $form_behavior,
		];
	}
}

SetCustomResponse::get_instance();
