<?php
/**
 * WPCafe integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\WPCafe;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger_WPCafe_Integration
 */
class WPCafe extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'WPCafe';

	/**
	 * SureTrigger_WPCafe_Integration constructor.
	 */
	public function __construct() {
		$this->name        = __( 'WPCafe', 'suretriggers' );
		$this->description = __( 'WPCafe is a WordPress plugin for restaurant management, food ordering, and reservation systems.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/wpcafe.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'WPCAFE_VERSION' );
	}
}

IntegrationsController::register( WPCafe::class );
