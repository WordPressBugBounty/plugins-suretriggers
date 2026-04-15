<?php
/**
 * CodeSnippetsSnippetDeactivated.
 * php version 5.6
 *
 * @category CodeSnippetsSnippetDeactivated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\CodeSnippets\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'CodeSnippetsSnippetDeactivated' ) ) :

	/**
	 * CodeSnippetsSnippetDeactivated
	 *
	 * @category CodeSnippetsSnippetDeactivated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class CodeSnippetsSnippetDeactivated {


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
		public $trigger = 'code_snippets_snippet_deactivated';

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
				'label'         => __( 'Snippet Deactivated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'code_snippets/deactivate_snippet',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];
			return $triggers;

		}

		/**
		 * Trigger listener
		 *
		 * @param int  $snippet_id Snippet ID.
		 * @param bool $network Whether the snippet is network-wide.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $snippet_id, $network ) {
			if ( ! function_exists( 'Code_Snippets\get_snippet' ) ) {
				return;
			}

			$snippet = \Code_Snippets\get_snippet( absint( $snippet_id ) );

			if ( ! is_object( $snippet ) ) {
				return;
			}

			$context            = CodeSnippetsSnippetCreated::get_snippet_context( $snippet );
			$context['network'] = $network ? 'yes' : 'no';

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
	CodeSnippetsSnippetDeactivated::get_instance();

endif;
