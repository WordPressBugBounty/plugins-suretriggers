<?php
/**
 * SureDonation core integration file.
 * php version 5.6
 *
 * @category SureDonation
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDonation;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureDonation
 *
 * @package SureTriggers\Integrations\SureDonation
 */
class SureDonation extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'SureDonation';

	/**
	 * SureDonation constructor.
	 */
	public function __construct() {
		$this->name        = __( 'SureDonation', 'suretriggers' );
		$this->description = __( 'A WordPress donation plugin for creating and managing fundraising campaigns.', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'SUREDONATION_VER' );
	}

}

IntegrationsController::register( SureDonation::class );
