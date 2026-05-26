<?php
/**
 * CluevoLms core integrations file
 *
 * @since   1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\CluevoLms;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class CluevoLms
 *
 * @package SureTriggers\Integrations\CluevoLms
 */
class CluevoLms extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'CluevoLms';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'CLUEVO LMS', 'suretriggers' );
		$this->description = __( 'A WordPress LMS with native SCORM support.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/cluevo-lms.png';
		parent::__construct();
	}

	/**
	 * Returns context data for a learning structure item.
	 *
	 * @param int $item_id The CLUEVO tree item ID.
	 * @return array
	 */
	public static function get_item_context( $item_id ) {
		if ( ! function_exists( 'cluevo_get_lms_item_list' ) ) {
			return [ 'cluevo_item_id' => $item_id ];
		}

		$items = cluevo_get_lms_item_list();
		if ( ! is_array( $items ) ) {
			return [ 'cluevo_item_id' => $item_id ];
		}

		foreach ( $items as $item ) {
			if ( (int) $item->item_id === (int) $item_id ) {
				return [
					'cluevo_item_id'   => $item->item_id,
					'cluevo_item_name' => $item->name,
					'cluevo_item_path' => $item->path,
				];
			}
		}

		return [ 'cluevo_item_id' => $item_id ];
	}

	/**
	 * Returns standard WP user context.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array
	 */
	public static function get_user_context( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user instanceof \WP_User ) {
			return [];
		}

		return [
			'user_id'      => $user->ID,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'user_login'   => $user->user_login,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
		];
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'Cluevo' );
	}
}

IntegrationsController::register( CluevoLms::class );
