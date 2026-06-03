<?php
/**
 * Bricksforge core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\Bricksforge;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class Bricksforge
 *
 * @package SureTriggers\Integrations\Bricksforge
 */
class Bricksforge extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'Bricksforge';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Bricksforge', 'suretriggers' );
		$this->description = __( 'Bricksforge is a powerful toolkit for Bricks Builder, offering Pro Forms, animations, and more.', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'Bricksforge\BricksForge' );
	}

}

IntegrationsController::register( Bricksforge::class );
