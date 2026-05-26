<?php
/**
 * GroupsItthinx integration class file
 *
 * @package  SureTriggers
 * @since 1.0.0
 */

namespace SureTriggers\Integrations\GroupsItthinx;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class GroupsItthinx
 *
 * @package SureTriggers\Integrations\GroupsItthinx
 */
class GroupsItthinx extends Integrations {

	use SingletonLoader;

	/**
	 * ID of the integration
	 *
	 * @var string
	 */
	protected $id = 'GroupsItthinx';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Groups by itthinx', 'suretriggers' );
		$this->description = __( 'Group-based user membership management and content access control.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/groups-itthinx.svg';

		parent::__construct();
	}

	/**
	 * Check plugin is installed.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'Groups_Group' );
	}
}

IntegrationsController::register( GroupsItthinx::class );
