<?php
/**
 * SureFormsCreateFormFromPrompt.
 * php version 5.6
 *
 * @category SureFormsCreateFormFromPrompt
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureForms\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * SureFormsCreateFormFromPrompt
 *
 * Generates the form structure from a plain-text prompt using the SureForms AI
 * form builder, then creates the form with SureForms' `sureforms/create-form`
 * ability. The AI request therefore uses the site's own SureForms AI access
 * (license key, or the email connected in SureForms > AI), and its usage limits.
 *
 * @category SureFormsCreateFormFromPrompt
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureFormsCreateFormFromPrompt extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureForms';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'sureforms_create_form_from_prompt';

	/**
	 * Maximum prompt length, same as the SureForms AI form builder.
	 *
	 * @var int
	 */
	const MAX_PROMPT_LENGTH = 2000;

	/**
	 * Maximum number of fields accepted from the AI response.
	 *
	 * @var int
	 */
	const MAX_FIELDS = 100;

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Form from Prompt', 'suretriggers' ),
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
	 * @psalm-suppress UndefinedMethod
	 *
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$helper_class = '\SRFM\Inc\AI_Form_Builder\AI_Helper';

		if ( ! class_exists( '\SRFM\Plugin_Loader' ) ) {
			return $this->error_response( 'SureForms plugin is not active.' );
		}

		// The create-form ability shipped in SureForms 2.5.2.
		if ( ! class_exists( '\SRFM\Inc\Abilities\Forms\Create_Form' ) || ! class_exists( '\SRFM\Inc\AI_Form_Builder\AI_Helper' ) ) {
			return $this->error_response( 'Please update SureForms to version 2.5.2 or later to use this action.' );
		}

		$ability = new \SRFM\Inc\Abilities\Forms\Create_Form();

		if ( ! is_callable( [ $ability, 'execute_wrapper' ] ) || ! is_callable( [ $helper_class, 'get_chat_completions_response' ] ) ) {
			return $this->error_response( 'Please update SureForms to version 2.5.2 or later to use this action.' );
		}

		$prompt = isset( $selected_options['form_prompt'] ) && is_string( $selected_options['form_prompt'] ) ? trim( sanitize_textarea_field( $selected_options['form_prompt'] ) ) : '';
		if ( '' === $prompt ) {
			return $this->error_response( 'Form prompt is required.' );
		}
		if ( mb_strlen( $prompt ) > self::MAX_PROMPT_LENGTH ) {
			return $this->error_response( 'The prompt is too long. Please keep it under ' . self::MAX_PROMPT_LENGTH . ' characters.' );
		}

		$allowed_statuses = [ 'publish', 'draft', 'private' ];
		$form_status      = isset( $selected_options['form_status'] ) && is_string( $selected_options['form_status'] ) ? sanitize_text_field( $selected_options['form_status'] ) : 'draft';
		if ( ! in_array( $form_status, $allowed_statuses, true ) ) {
			$form_status = 'draft';
		}

		$response = $helper_class::get_chat_completions_response( [ 'query' => $prompt ] );

		if ( ! is_array( $response ) ) {
			return $this->error_response( 'The SureForms AI service returned an invalid response.' );
		}

		if ( ! empty( $response['error'] ) ) {
			$raw = '';
			if ( is_string( $response['error'] ) ) {
				$raw = $response['error'];
			} elseif ( is_array( $response['error'] ) && ! empty( $response['error']['message'] ) && is_string( $response['error']['message'] ) ) {
				$raw = $response['error']['message'];
			}

			// Never pass the raw middleware message on: it can contain infrastructure details.
			$message = '';
			if ( is_callable( [ $helper_class, 'sanitize_ai_error_message' ] ) ) {
				$message = (string) $helper_class::sanitize_ai_error_message( $raw, 'generate/form' );
			}
			if ( '' === $message ) {
				$message = 'The SureForms AI service returned an error.';
			}
			return $this->error_response( $message );
		}

		if ( empty( $response['form'] ) || ! is_array( $response['form'] ) || empty( $response['form']['formFields'] ) || ! is_array( $response['form']['formFields'] ) ) {
			return $this->error_response( 'The AI did not return any form fields. Please refine the prompt and try again.' );
		}

		if ( count( $response['form']['formFields'] ) > self::MAX_FIELDS ) {
			return $this->error_response( 'The AI returned too many fields. Please ask for a smaller form.' );
		}

		// The AI output is untrusted: make sure every field is well-formed and of a supported type.
		$allowed_types = $this->get_allowed_field_types( $ability );
		foreach ( $response['form']['formFields'] as $field ) {
			if ( ! is_array( $field ) || empty( $field['label'] ) || ! is_string( $field['label'] ) || empty( $field['fieldType'] ) || ! is_string( $field['fieldType'] ) ) {
				return $this->error_response( 'The AI returned an invalid field. Please refine the prompt and try again.' );
			}
			if ( ! empty( $allowed_types ) && ! in_array( $field['fieldType'], $allowed_types, true ) ) {
				return $this->error_response( 'The AI returned an unsupported field type. Please refine the prompt and try again.' );
			}
		}

		// An explicit title wins over the AI generated one.
		$form_title = isset( $selected_options['form_title'] ) && is_string( $selected_options['form_title'] ) ? sanitize_text_field( $selected_options['form_title'] ) : '';
		if ( '' === $form_title && isset( $response['form']['formTitle'] ) && is_string( $response['form']['formTitle'] ) ) {
			$form_title = sanitize_text_field( $response['form']['formTitle'] );
		}
		if ( '' === $form_title ) {
			return $this->error_response( 'The AI response is missing a form title. Please enter a Form Title and try again.' );
		}

		// execute_wrapper() also fires SureForms' before/after ability hooks.
		$result = $ability->execute_wrapper(
			[
				'formTitle'  => $form_title,
				'formFields' => $response['form']['formFields'],
				'formStatus' => $form_status,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Field types accepted by the SureForms create-form ability (read from its input schema).
	 *
	 * @param object $ability Create_Form ability instance.
	 * @return array
	 */
	private function get_allowed_field_types( $ability ) {
		if ( ! is_callable( [ $ability, 'get_input_schema' ] ) ) {
			return [];
		}

		$schema = $ability->get_input_schema();
		$types  = is_array( $schema ) && isset( $schema['properties']['formFields']['items']['properties']['fieldType']['enum'] )
			? $schema['properties']['formFields']['items']['properties']['fieldType']['enum']
			: [];

		return is_array( $types ) ? $types : [];
	}

	/**
	 * Build the standard error response returned by actions.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	private function error_response( $message ) {
		return [
			'status'  => 'error',
			'message' => $message,
		];
	}
}

SureFormsCreateFormFromPrompt::get_instance();
