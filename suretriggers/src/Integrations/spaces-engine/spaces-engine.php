<?php
/**
 * SpacesEngine core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\SpacesEngine;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SpacesEngine
 *
 * @package SureTriggers\Integrations\SpacesEngine
 */
class SpacesEngine extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'SpacesEngine';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Spaces Engine', 'suretriggers' );
		$this->description = __( 'Easily create business profiles (Spaces) for BuddyPress and BuddyBoss.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/spaces-engine.svg';
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'SPACES_ENGINE_PLUGIN_VERSION' );
	}

}

IntegrationsController::register( SpacesEngine::class );
