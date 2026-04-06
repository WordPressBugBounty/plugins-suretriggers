<?php
/**
 * NewCCTItem.
 * php version 5.6
 *
 * @category NewCCTItem
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetEngineCCT\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'NewCCTItem' ) ) :

	/**
	 * NewCCTItem
	 *
	 * @category NewCCTItem
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class NewCCTItem {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'JetEngineCCT';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'jet_engine_new_cct_item';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
			add_action( 'init', [ $this, 'register_hooks' ], 99 );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'New CCT Item', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => [],
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Register hooks for all registered CCTs dynamically.
		 * JetEngine CCT hooks are slug-specific: jet-engine/custom-content-types/created-item/{slug}
		 *
		 * @return void
		 */
		public function register_hooks() {
			if ( ! class_exists( '\Jet_Engine\Modules\Custom_Content_Types\Module' ) ) {
				return;
			}

			$content_types = \Jet_Engine\Modules\Custom_Content_Types\Module::instance()->manager->get_content_types();

			if ( empty( $content_types ) || ! is_array( $content_types ) ) {
				return;
			}
			
			foreach ( $content_types as $slug => $factory ) {
				add_action(
					'jet-engine/custom-content-types/created-item/' . $slug,
					[ $this, 'trigger_listener' ],
					10,
					3
				);
			}
		}

		/**
		 * Trigger listener for CCT item creation.
		 *
		 * @param array  $item Item data.
		 * @param int    $item_id Item ID.
		 * @param object $item_handler Item handler instance.
		 * @return void
		 */
		public function trigger_listener( $item, $item_id, $item_handler ) {
			if ( empty( $item ) || empty( $item_id ) ) {
				return;
			}

			$cct_slug = '';

			if ( is_object( $item_handler ) && method_exists( $item_handler, 'get_factory' ) ) {
				$factory = $item_handler->get_factory();
				if ( is_object( $factory ) && method_exists( $factory, 'get_arg' ) ) {
					$cct_slug = $factory->get_arg( 'slug' );
				}
			}

			$context                = [];
			$context['cct_slug']    = $cct_slug;
			$context['cct_item_id'] = $item_id;

			if ( is_array( $item ) ) {
				foreach ( $item as $key => $value ) {
					$context[ $key ] = maybe_unserialize( $value );
				}
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	NewCCTItem::get_instance();

endif;
