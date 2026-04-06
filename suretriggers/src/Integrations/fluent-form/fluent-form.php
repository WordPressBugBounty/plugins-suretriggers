<?php
/**
 * Fluent Form core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\FluentForm;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Controllers\RestController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\FluentForm
 */
class FluentForm extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'FluentForm';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Fluent Form', 'suretriggers' );
		$this->description = __( 'Fluent Form is a WordPress Form Builder.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/fluentform.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'FLUENTFORM' );
	}

}

IntegrationsController::register( FluentForm::class );

/**
 * Valid form behavior values for Fluent Forms confirmation.
 */
const OTTOKIT_FF_VALID_FORM_BEHAVIORS = [ 'hide_form', 'reset_form' ];

/**
 * Save a confirmation override for a specific form.
 *
 * Uses per-form option keys to avoid race conditions.
 *
 * @param int   $form_id  The form ID.
 * @param array $override The override config.
 *
 * @return void
 */
function ottokit_ff_save_override( $form_id, $override ) {
	update_option( 'ottokit_ff_override_' . $form_id, $override, false );
}

/**
 * Get the confirmation override for a specific form.
 *
 * @param int $form_id The form ID.
 *
 * @return array|false The override config, or false if not set.
 */
function ottokit_ff_get_override( $form_id ) {
	$override = get_option( 'ottokit_ff_override_' . $form_id, false );
	if ( is_array( $override ) ) {
		return $override;
	}
	return false;
}

/**
 * Delete the confirmation override for a specific form.
 *
 * @param int $form_id The form ID.
 *
 * @return void
 */
function ottokit_ff_delete_override( $form_id ) {
	delete_option( 'ottokit_ff_override_' . $form_id );
}

/**
 * Modify form confirmation settings before the response is built.
 *
 * Hooks into fluentform/before_submission_confirmation which fires
 * right before getReturnData() reads $form->settings['confirmation'].
 * The $form object is passed by reference so modifying it here
 * directly affects the response sent to the user.
 *
 * Override is consumed once and then deleted (one-shot behavior).
 *
 * Supports Fluent Forms ShortCode placeholders in messages:
 * - {inputs.field_name} - submitted field value
 * - {submission.serial_number} - entry serial number
 * - {submission.id} - entry ID
 * - {wp.user_name} - current user name
 *
 * @param int    $insert_id The submission entry ID.
 * @param array  $form_data The submitted form data.
 * @param object $form      The form object (passed by reference as object).
 *
 * @return void
 */
function ottokit_apply_ff_confirmation_override( $insert_id, $form_data, $form ) {
	if ( ! isset( $form->id ) ) {
		return;
	}

	$form_id  = (int) $form->id;
	$override = ottokit_ff_get_override( $form_id );

	if ( false === $override || empty( $override['type'] ) ) {
		return;
	}

	// One-shot: delete immediately so the next submission uses default behavior.
	ottokit_ff_delete_override( $form_id );

	// Fluent Forms $form is a stdClass-like model with dynamic properties.
	// Ensure settings array exists on the form object.
	if ( ! property_exists( $form, 'settings' ) || ! is_array( $form->settings ) ) {
		$form->settings = []; // @phpstan-ignore property.notFound
	}

	$settings = is_array( $form->settings ) ? $form->settings : [];

	if ( ! isset( $settings['confirmation'] ) || ! is_array( $settings['confirmation'] ) ) {
		$settings['confirmation'] = [];
	}

	if ( 'custom_message' === $override['type'] && ! empty( $override['message'] ) ) {
		// Message supports Fluent Forms shortcodes like {inputs.name}, {submission.serial_number}
		// which will be parsed by ShortCodeParser in getReturnData().
		$settings['confirmation']['redirectTo']           = 'samePage';
		$settings['confirmation']['messageToShow']        = $override['message'];
		$settings['confirmation']['samePageFormBehavior'] = isset( $override['form_behavior'] )
			? $override['form_behavior']
			: 'hide_form';
	} elseif ( 'redirect' === $override['type'] && ! empty( $override['redirect_url'] ) ) {
		// URL may contain Fluent Forms shortcodes like {inputs.email}, {submission.id}
		// which ShortCodeParser resolves — do not sanitize with esc_url_raw() here
		// as it would strip curly-brace placeholders.
		$settings['confirmation']['redirectTo'] = 'customUrl';
		$settings['confirmation']['customUrl']  = $override['redirect_url'];

		if ( ! empty( $override['redirect_message'] ) ) {
			$settings['confirmation']['redirectMessage'] = $override['redirect_message'];
		}
	}

	$form->settings = $settings;
}

add_action( 'fluentform/before_submission_confirmation', __NAMESPACE__ . '\ottokit_apply_ff_confirmation_override', 10, 3 );
add_action( 'fluentform_before_submission_confirmation', __NAMESPACE__ . '\ottokit_apply_ff_confirmation_override', 10, 3 );

/**
 * Register REST endpoint to set/get/delete custom confirmation overrides.
 *
 * POST /wp-json/sure-triggers/v1/fluent-form/confirmation-override
 *   Body: { "form_id": 1, "type": "custom_message", "message": "Thanks {inputs.name}!" }
 *   Body: { "form_id": 1, "type": "redirect", "redirect_url": "https://example.com?id={submission.id}" }
 *
 * GET  /wp-json/sure-triggers/v1/fluent-form/confirmation-override?form_id=1
 *
 * DELETE /wp-json/sure-triggers/v1/fluent-form/confirmation-override
 *   Body: { "form_id": 1 }
 *
 * @return void
 */
function ottokit_register_ff_confirmation_endpoint() {
	$auth_callback = [ RestController::get_instance(), 'autheticate_user' ];

	register_rest_route(
		SURE_TRIGGERS_REST_NAMESPACE,
		'fluent-form/confirmation-override',
		[
			[
				'methods'             => 'POST',
				'callback'            => __NAMESPACE__ . '\ottokit_set_ff_confirmation_override',
				'permission_callback' => $auth_callback,
			],
			[
				'methods'             => 'GET',
				'callback'            => __NAMESPACE__ . '\ottokit_get_ff_confirmation_override',
				'permission_callback' => $auth_callback,
			],
			[
				'methods'             => 'DELETE',
				'callback'            => __NAMESPACE__ . '\ottokit_delete_ff_confirmation_override',
				'permission_callback' => $auth_callback,
			],
		]
	);
}

add_action( 'rest_api_init', __NAMESPACE__ . '\ottokit_register_ff_confirmation_endpoint' );

/**
 * POST handler — set a custom confirmation override for a form.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function ottokit_set_ff_confirmation_override( $request ) {
	$form_id = absint( $request->get_param( 'form_id' ) );
	$type    = sanitize_text_field( (string) $request->get_param( 'type' ) );

	if ( empty( $form_id ) ) {
		return new WP_Error( 'missing_form_id', 'form_id is required.', [ 'status' => 400 ] );
	}

	if ( ! in_array( $type, [ 'custom_message', 'redirect' ], true ) ) {
		return new WP_Error( 'invalid_type', 'type must be custom_message or redirect.', [ 'status' => 400 ] );
	}

	$override = [ 'type' => $type ];

	if ( 'custom_message' === $type ) {
		$message = $request->get_param( 'message' );
		if ( empty( $message ) || ! is_string( $message ) ) {
			return new WP_Error( 'missing_message', 'message is required for custom_message type.', [ 'status' => 400 ] );
		}
		$override['message'] = wp_kses_post( $message );

		$form_behavior = sanitize_text_field( (string) $request->get_param( 'form_behavior' ) );
		if ( ! in_array( $form_behavior, OTTOKIT_FF_VALID_FORM_BEHAVIORS, true ) ) {
			$form_behavior = 'hide_form';
		}
		$override['form_behavior'] = $form_behavior;
	} else {
		$redirect_url = $request->get_param( 'redirect_url' );
		if ( empty( $redirect_url ) || ! is_string( $redirect_url ) ) {
			return new WP_Error( 'missing_redirect_url', 'redirect_url is required for redirect type.', [ 'status' => 400 ] );
		}
		// Do not esc_url_raw() — URL may contain Fluent Forms placeholders like {submission.id}.
		$override['redirect_url'] = sanitize_text_field( $redirect_url );

		$redirect_message = $request->get_param( 'redirect_message' );
		if ( is_string( $redirect_message ) && ! empty( $redirect_message ) ) {
			$override['redirect_message'] = wp_kses_post( $redirect_message );
		}
	}

	ottokit_ff_save_override( $form_id, $override );

	return new WP_REST_Response(
		[
			'status'  => 'success',
			'message' => 'Confirmation override set for form ' . $form_id . '.',
			'form_id' => $form_id,
			'config'  => $override,
		],
		200
	);
}

/**
 * GET handler — get the current override config for a form.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function ottokit_get_ff_confirmation_override( $request ) {
	$form_id = absint( $request->get_param( 'form_id' ) );

	if ( empty( $form_id ) ) {
		return new WP_Error( 'missing_form_id', 'form_id is required.', [ 'status' => 400 ] );
	}

	$config = ottokit_ff_get_override( $form_id );

	return new WP_REST_Response(
		[
			'form_id' => $form_id,
			'config'  => false !== $config ? $config : null,
		],
		200
	);
}

/**
 * DELETE handler — remove override config for a form.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function ottokit_delete_ff_confirmation_override( $request ) {
	$form_id = absint( $request->get_param( 'form_id' ) );

	if ( empty( $form_id ) ) {
		return new WP_Error( 'missing_form_id', 'form_id is required.', [ 'status' => 400 ] );
	}

	ottokit_ff_delete_override( $form_id );

	return new WP_REST_Response(
		[
			'status'  => 'success',
			'message' => 'Confirmation override removed for form ' . $form_id . '.',
		],
		200
	);
}
