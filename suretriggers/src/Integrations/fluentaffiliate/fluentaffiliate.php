<?php
/**
 * FluentAffiliate core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\FluentAffiliate;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class FluentAffiliate
 *
 * @package SureTriggers\Integrations\FluentAffiliate
 */
class FluentAffiliate extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'FluentAffiliate';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'FluentAffiliate', 'suretriggers' );
		$this->description = __( 'Affiliate Management Plugin for WordPress', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/fluentaffiliate.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'FLUENT_AFFILIATE' ) || class_exists( 'FluentAffiliate\App\Models\Affiliate' );
	}

}

IntegrationsController::register( FluentAffiliate::class );
