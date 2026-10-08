<?php
/**
 * CreateCompany.
 * php version 5.6
 *
 * @category CreateCompany
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\BitCRM\Actions;

use BitApps\Crm\Services\CompanyService;
use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\BitCRM\BitCRM;
use SureTriggers\Traits\SingletonLoader;

/**
 * CreateCompany
 *
 * @category CreateCompany
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateCompany extends AutomateAction {


	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'BitCRM';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'bit_crm_create_company';

	use SingletonLoader;

	/**
	 * Allow-listed company fields, matching the fields declared in this
	 * action's SaaS-side template. Anything not on this list is dropped
	 * rather than forwarded to Bit CRM, so an automation cannot smuggle in
	 * internal columns (e.g. `owner_id`, `status`, `is_trash`) that Bit
	 * CRM's own validation only wildcards as generic nullable strings.
	 *
	 * @var string[]
	 */
	protected $allowed_fields = [ 'name', 'phone', 'website', 'industry', 'annual_revenue', 'billing_address_line_1', 'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'description' ];

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Company', 'suretriggers' ),
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
	 * @param array $selected_options selected_options.
	 *
	 * @return array
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( '\BitApps\Crm\Services\CompanyService' ) ) {
			return [
				'status'  => 'error',
				'message' => 'Bit CRM plugin is not installed or activated.',
			];
		}

		if ( empty( $selected_options['name'] ) ) {
			return [
				'status'  => 'error',
				'message' => 'Name is required to create a company.',
			];
		}

		$system_defined_fields_values = [];

		foreach ( $this->allowed_fields as $key ) {
			if ( empty( $selected_options[ $key ] ) || ! is_scalar( $selected_options[ $key ] ) ) {
				continue;
			}

			$field_value                          = (string) $selected_options[ $key ];
			$system_defined_fields_values[ $key ] = 'description' === $key ? sanitize_textarea_field( $field_value ) : sanitize_text_field( $field_value );
		}

		$data = [
			'systemDefinedFieldsValues' => $system_defined_fields_values,
			'newTagTitles'              => $this->normalize_tags( isset( $selected_options['tags'] ) ? $selected_options['tags'] : '' ),
		];

		$result = ( new CompanyService() )->store( $data );

		if ( empty( $result['success'] ) ) {
			$error_message = BitCRM::get_first_error_message( isset( $result['errors'] ) ? $result['errors'] : [] );

			return [
				'status'  => 'error',
				'message' => '' !== $error_message ? $error_message : 'Something went wrong while creating the company.',
			];
		}

		return BitCRM::get_entity_context( $result['data'] );
	}

	/**
	 * Normalize a tags field into an array of tag titles.
	 *
	 * Field values can arrive as a single scalar, a comma-separated string,
	 * or an array of `{value, label}` objects.
	 *
	 * @param mixed $value Raw tags field value.
	 * @return array
	 */
	protected function normalize_tags( $value ) {
		if ( empty( $value ) ) {
			return [];
		}

		if ( is_string( $value ) ) {
			$value = array_map( 'trim', explode( ',', $value ) );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$titles = [];

		foreach ( $value as $item ) {
			if ( is_array( $item ) && isset( $item['label'] ) && is_scalar( $item['label'] ) ) {
				$titles[] = $item['label'];
			} elseif ( is_scalar( $item ) && '' !== $item ) {
				$titles[] = $item;
			}
		}

		return array_map(
			function ( $title ) {
				return sanitize_text_field( (string) $title );
			},
			$titles
		);
	}

}

CreateCompany::get_instance();
