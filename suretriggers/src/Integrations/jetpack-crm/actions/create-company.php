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

namespace SureTriggers\Integrations\JetpackCRM\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\JetpackCRM\JetpackCRM;
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
	public $integration = 'JetpackCRM';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'jetpack_crm_create_company';

	use SingletonLoader;

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
		$company_details = [];

		foreach ( $selected_options as $label => $value ) {
			if ( 'runningTestAction' === $label ) {
				continue;
			}

			if ( 'tags' === $label ) {
				$company_details['data'][ $label ] = ! empty( $value ) ? [ sanitize_text_field( $value ) ] : [];
			} else {
				$company_details['data'][ $label ] = ! empty( $value ) ? sanitize_text_field( $value ) : '';
			}
		}

		global $zbs;
		$company_id = $zbs->DAL->companies->addUpdateCompany( $company_details ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( ! $company_id ) {
			return [
				'status'  => 'error',
				'message' => 'Something went wrong while creating company.',
			];
		}

		return JetpackCRM::get_company_context( $company_id );
	}

}

CreateCompany::get_instance();
