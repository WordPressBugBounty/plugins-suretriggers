<?php
/**
 * UpdatedCCTItem.
 * php version 5.6
 *
 * @category UpdatedCCTItem
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\JetEngineCCT\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'UpdatedCCTItem' ) ) :

	/**
	 * UpdatedCCTItem
	 *
	 * @category UpdatedCCTItem
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UpdatedCCTItem {

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
		public $trigger = 'jet_engine_updated_cct_item';

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
				'label'         => __( 'Updated CCT Item', 'suretriggers' ),
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
		 * JetEngine CCT hooks are slug-specific: jet-engine/custom-content-types/updated-item/{slug}
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
					'jet-engine/custom-content-types/updated-item/' . $slug,
					[ $this, 'trigger_listener' ],
					10,
					3
				);
			}
		}

		/**
		 * Trigger listener for CCT item update.
		 *
		 * @param array  $item Updated item data.
		 * @param array  $prev_item Previous item data.
		 * @param object $item_handler Item handler instance.
		 * @return void
		 */
		public function trigger_listener( $item, $prev_item, $item_handler ) {

			if ( empty( $item ) ) {
				return;
			}

			$cct_slug = '';
			$item_id  = 0;

			if ( is_object( $item_handler ) && method_exists( $item_handler, 'get_factory' ) ) {
				$factory = $item_handler->get_factory();
				if ( is_object( $factory ) && method_exists( $factory, 'get_arg' ) ) {
					$cct_slug = $factory->get_arg( 'slug' );
				}
			}

			if ( is_array( $item ) && isset( $item['_ID'] ) ) {
				$item_id = $item['_ID'];
			}

			$context = [
				'cct_slug'    => $cct_slug,
				'cct_item_id' => $item_id,
			];

			if ( is_array( $item ) ) {
				foreach ( $item as $key => $value ) {
					$context[ $key ] = maybe_unserialize( $value );
				}
			}

			if ( is_array( $prev_item ) ) {
				$previous = [];
				foreach ( $prev_item as $key => $value ) {
					$previous[ $key ] = maybe_unserialize( $value );
				}
				$context['previous_item'] = $previous;
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
	UpdatedCCTItem::get_instance();

endif;
