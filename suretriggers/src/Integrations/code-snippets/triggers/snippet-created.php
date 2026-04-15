<?php
/**
 * CodeSnippetsSnippetCreated.
 * php version 5.6
 *
 * @category CodeSnippetsSnippetCreated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\CodeSnippets\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'CodeSnippetsSnippetCreated' ) ) :

	/**
	 * CodeSnippetsSnippetCreated
	 *
	 * @category CodeSnippetsSnippetCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class CodeSnippetsSnippetCreated {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'CodeSnippets';


		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'code_snippets_snippet_created';

		use SingletonLoader;


		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Snippet Created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'code_snippets/create_snippet',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;

		}

		/**
		 * Trigger listener
		 *
		 * @param object $snippet Snippet object.
		 * @param string $table_name Table name.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $snippet, $table_name ) {
			if ( ! is_object( $snippet ) ) {
				return;
			}

			$context = self::get_snippet_context( $snippet );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}

		/**
		 * Get snippet context data.
		 *
		 * @param object $snippet Snippet object.
		 * @return array
		 */
		public static function get_snippet_context( $snippet ) {
			$context = [];

			if ( isset( $snippet->id ) ) {
				$context['snippet_id'] = absint( $snippet->id );
			}
			if ( isset( $snippet->name ) ) {
				$context['snippet_name'] = sanitize_text_field( $snippet->name );
			}
			if ( isset( $snippet->desc ) ) {
				$context['snippet_description'] = wp_kses_post( $snippet->desc );
			}
			if ( isset( $snippet->code ) ) {
				$context['snippet_code'] = $snippet->code;
			}
			if ( isset( $snippet->tags ) ) {
				$tags                    = is_array( $snippet->tags ) ? $snippet->tags : [ $snippet->tags ];
				$context['snippet_tags'] = sanitize_text_field( implode( ', ', $tags ) );
			}
			if ( isset( $snippet->scope ) ) {
				$context['snippet_scope'] = sanitize_text_field( $snippet->scope );
			}
			if ( isset( $snippet->priority ) ) {
				$context['snippet_priority'] = absint( $snippet->priority );
			}
			if ( isset( $snippet->active ) ) {
				$context['snippet_active'] = $snippet->active;
			}
			if ( isset( $snippet->modified ) ) {
				$context['snippet_modified'] = sanitize_text_field( $snippet->modified );
			}

			return $context;
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	CodeSnippetsSnippetCreated::get_instance();

endif;
