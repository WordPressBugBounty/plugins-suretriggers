<?php
/**
 * New User Approve core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\NewUserApprove;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTriggers\Integrations\NewUserApprove\NewUserApprove
 *
 * @package SureTriggers\Integrations\NewUserApprove
 */
class NewUserApprove extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'NewUserApprove';

	/**
	 * SureTriggers\Integrations\NewUserApprove\NewUserApprove constructor.
	 */
	public function __construct() {
		$this->name        = __( 'New User Approve', 'suretriggers' );
		$this->description = __( 'New User Approve allows admins to approve or deny new user registrations.', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'pw_new_user_approve' );
	}

}

IntegrationsController::register( NewUserApprove::class );
