<?php
/**
 * AbilitiesController — WordPress Abilities API registration.
 * php version 5.6
 *
 * @category Abilities
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.21
 */

namespace SureTriggers\Abilities;

use SureTriggers\Controllers\EventController;
use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Controllers\OptionController;
use SureTriggers\Controllers\WebhookRequestsController;
use SureTriggers\Models\SaasApiToken;
use SureTriggers\Traits\SingletonLoader;

/**
 * AbilitiesController
 *
 * Registers OttoKit categories and abilities with the WordPress Abilities API
 * (WP 6.9+). All registrations are guarded with function_exists() so the plugin
 * remains compatible with WP 5.4+.
 *
 * Categories:
 *  - ottokit-connection  (connection status, settings, system health)
 *  - ottokit-automation  (triggers, integrations, events, webhook logs)
 *
 * Abilities (8):
 *  - ottokit/get-connection-status
 *  - ottokit/get-settings
 *  - ottokit/get-system-status
 *  - ottokit/get-active-triggers
 *  - ottokit/get-active-integrations
 *  - ottokit/get-all-integrations
 *  - ottokit/get-integration-events
 *  - ottokit/get-webhook-logs
 *
 * @category Abilities
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.21
 */
class AbilitiesController {

	use SingletonLoader;

	/**
	 * Constructor — hooks into Abilities API lifecycle if WP 6.9+ is present.
	 *
	 * @since 1.1.21
	 */
	public function __construct() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	// -------------------------------------------------------------------------
	// Category registration
	// -------------------------------------------------------------------------

	/**
	 * Register ability categories.
	 *
	 * @return void
	 */
	public function register_categories() {
		wp_register_ability_category(
			'ottokit-connection',
			[
				'label'       => __( 'OttoKit Connection', 'suretriggers' ),
				'description' => __( 'Abilities for checking OttoKit connection status, settings, and system health.', 'suretriggers' ),
			]
		);

		wp_register_ability_category(
			'ottokit-automation',
			[
				'label'       => __( 'OttoKit Automation', 'suretriggers' ),
				'description' => __( 'Abilities for inspecting automation triggers, integrations, events, and webhook logs.', 'suretriggers' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Ability registration
	// -------------------------------------------------------------------------

	/**
	 * Register all OttoKit abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		$this->register_get_connection_status();
		$this->register_get_settings();
		$this->register_get_system_status();
		$this->register_get_active_triggers();
		$this->register_get_active_integrations();
		$this->register_get_all_integrations();
		$this->register_get_integration_events();
		$this->register_get_webhook_logs();
	}

	// -------------------------------------------------------------------------
	// ottokit/get-connection-status
	// -------------------------------------------------------------------------

	/**
	 * Register the get-connection-status ability.
	 *
	 * @return void
	 */
	private function register_get_connection_status() {
		wp_register_ability(
			'ottokit/get-connection-status',
			[
				'label'               => __( 'Get OttoKit connection status', 'suretriggers' ),
				'description'         => __( 'Reads the live OttoKit SaaS connection state from WordPress options. Returns: connected (bool), status string (suretriggers_connection_successful | suretriggers_connection_error | suretriggers_connection_wp_error | empty), the admin email used to connect, and the billing plan ID. Use this first when the user reports automations are not running or the connection appears broken — never assume connection state.', 'suretriggers' ),
				'category'            => 'ottokit-connection',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'connected'       => [
							'type'        => 'boolean',
							'description' => __( 'Whether OttoKit is connected to the SaaS platform.', 'suretriggers' ),
						],
						'status'          => [
							'type'        => 'string',
							'description' => __( 'Verified connection status. One of: suretriggers_connection_successful, suretriggers_connection_error, suretriggers_connection_wp_error, or empty.', 'suretriggers' ),
						],
						'connected_email' => [
							'type'        => [ 'string', 'null' ],
							'description' => __( 'Email address used to connect to OttoKit SaaS. Null if not connected.', 'suretriggers' ),
						],
						'plan_id'         => [
							'type'        => [ 'string', 'null' ],
							'description' => __( 'Current OttoKit plan identifier (e.g. free, pro). Null if unknown.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_connection_status' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'Call this first when the user reports automations are not running or the connection appears broken. Never assume connection state — always read it. Explain each returned field clearly to the user, especially the status string. Suggest re-connecting if status is empty or shows an error.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-connection-status.
	 *
	 * @param array $input Ability input (unused).
	 * @return array
	 */
	public function execute_get_connection_status( $input ) {
		$token           = SaasApiToken::get();
		$connected       = ! empty( $token ) && 'connection-denied' !== $token;
		$status          = get_option( 'suretriggers_verify_connection', '' );
		$connected_email = OptionController::get_option( 'connected_email_key' );
		$plan_data       = get_option( 'suretriggers_lifetime_user_plan_data' );
		$plan_id         = ( is_array( $plan_data ) && isset( $plan_data['plan_id'] ) ) ? $plan_data['plan_id'] : null;

		return [
			'connected'       => $connected,
			'status'          => is_string( $status ) ? $status : '',
			'connected_email' => ( is_string( $connected_email ) && '' !== $connected_email ) ? $connected_email : null,
			'plan_id'         => is_string( $plan_id ) ? $plan_id : null,
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-settings
	// -------------------------------------------------------------------------

	/**
	 * Register the get-settings ability.
	 *
	 * @return void
	 */
	private function register_get_settings() {
		wp_register_ability(
			'ottokit/get-settings',
			[
				'label'               => __( 'Get OttoKit access control settings', 'suretriggers' ),
				'description'         => __( 'Reads the OttoKit access-control settings from WordPress options. Returns: extra user IDs allowed to access the OttoKit dashboard (enabled_users), role slugs with access (enabled_user_roles), and the installation source type. Use this to audit who can access OttoKit or to diagnose access-denied complaints.', 'suretriggers' ),
				'category'            => 'ottokit-connection',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'enabled_users'      => [
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
							'description' => __( 'User IDs that have access to OttoKit (in addition to administrators).', 'suretriggers' ),
						],
						'enabled_user_roles' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => __( 'WordPress user role slugs that have access to OttoKit.', 'suretriggers' ),
						],
						'source_type'        => [
							'type'        => [ 'string', 'null' ],
							'description' => __( 'Source type identifier for this OttoKit installation.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_settings' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'Use this to audit access control. If a user reports they cannot access the OttoKit dashboard, check whether their user ID appears in enabled_users or their role slug appears in enabled_user_roles.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-settings.
	 *
	 * @param array $input Ability input (unused).
	 * @return array
	 */
	public function execute_get_settings( $input ) {
		$enabled_users = get_option( 'suretriggers_enabled_users', [] );
		$enabled_users = is_array( $enabled_users ) ? array_values( array_map( 'absint', $enabled_users ) ) : [];

		$enabled_roles = get_option( 'suretriggers_enabled_user_roles', [] );
		$enabled_roles = is_array( $enabled_roles ) ? array_values( array_map( 'strval', $enabled_roles ) ) : [];

		$source_type = get_option( 'suretriggers_source' );

		return [
			'enabled_users'      => $enabled_users,
			'enabled_user_roles' => $enabled_roles,
			'source_type'        => ( is_string( $source_type ) && '' !== $source_type ) ? $source_type : null,
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-system-status
	// -------------------------------------------------------------------------

	/**
	 * Register the get-system-status ability.
	 *
	 * @return void
	 */
	private function register_get_system_status() {
		wp_register_ability(
			'ottokit/get-system-status',
			[
				'label'               => __( 'Get OttoKit system health status', 'suretriggers' ),
				'description'         => __( 'Performs a full health check of the OttoKit installation. Returns: last SaaS connection status string, whether all three background cron jobs are scheduled (retry-failed-requests every 30 min, cleanup-logs daily, verify-api-connection every 6 hours), whether the webhook log DB table exists, and log counts by status (success, failed, pending). A missing cron or absent DB table indicates a broken installation that needs the plugin deactivated and reactivated.', 'suretriggers' ),
				'category'            => 'ottokit-connection',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'connection_status'      => [
							'type'        => 'string',
							'description' => __( 'Last verified OttoKit connection status string.', 'suretriggers' ),
						],
						'cron_retry_scheduled'   => [
							'type'        => 'boolean',
							'description' => __( 'Whether the failed-request retry cron (every 30 min) is scheduled.', 'suretriggers' ),
						],
						'cron_cleanup_scheduled' => [
							'type'        => 'boolean',
							'description' => __( 'Whether the daily log cleanup cron is scheduled.', 'suretriggers' ),
						],
						'cron_verify_scheduled'  => [
							'type'        => 'boolean',
							'description' => __( 'Whether the 6-hourly connection-verify cron is scheduled.', 'suretriggers' ),
						],
						'webhook_table_exists'   => [
							'type'        => 'boolean',
							'description' => __( 'Whether the suretriggers_webhook_requests DB table exists.', 'suretriggers' ),
						],
						'total_logs'             => [
							'type'        => 'integer',
							'description' => __( 'Total webhook log entries.', 'suretriggers' ),
						],
						'success_logs'           => [
							'type'        => 'integer',
							'description' => __( 'Count of successful webhook log entries.', 'suretriggers' ),
						],
						'failed_logs'            => [
							'type'        => 'integer',
							'description' => __( 'Count of failed webhook log entries.', 'suretriggers' ),
						],
						'pending_logs'           => [
							'type'        => 'integer',
							'description' => __( 'Count of pending webhook log entries.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_system_status' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'Use this to generate a full health report. Highlight any unscheduled cron jobs or a missing webhook table as critical issues. If crons are missing or the DB table is absent, advise the user to deactivate and reactivate the plugin. Explain all fields to the user.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-system-status.
	 *
	 * @param array $input Ability input (unused).
	 * @return array
	 */
	public function execute_get_system_status( $input ) {
		global $wpdb;

		$connection_status = get_option( 'suretriggers_verify_connection', '' );

		$table_name   = WebhookRequestsController::get_table_name();
		$table_exists = (bool) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s LIMIT 1',
				DB_NAME,
				$table_name
			)
		);

		$total_logs   = 0;
		$success_logs = 0;
		$failed_logs  = 0;
		$pending_logs = 0;

		if ( $table_exists ) {
			$total_logs   = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT COUNT(*) FROM {$table_name}" //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			$success_logs = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = %s", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'success'
				)
			);
			$failed_logs  = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = %s", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'failed'
				)
			);
			$pending_logs = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = %s", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'pending'
				)
			);
		}

		return [
			'connection_status'      => is_string( $connection_status ) ? $connection_status : '',
			'cron_retry_scheduled'   => (bool) wp_next_scheduled( 'suretriggers_retry_failed_requests' ),
			'cron_cleanup_scheduled' => (bool) wp_next_scheduled( 'suretriggers_webhook_requests_cleanup_logs' ),
			'cron_verify_scheduled'  => (bool) wp_next_scheduled( 'suretriggers_verify_api_connection' ),
			'webhook_table_exists'   => $table_exists,
			'total_logs'             => $total_logs,
			'success_logs'           => $success_logs,
			'failed_logs'            => $failed_logs,
			'pending_logs'           => $pending_logs,
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-active-triggers
	// -------------------------------------------------------------------------

	/**
	 * Register the get-active-triggers ability.
	 *
	 * @return void
	 */
	private function register_get_active_triggers() {
		wp_register_ability(
			'ottokit/get-active-triggers',
			[
				'label'               => __( 'Get configured automation triggers', 'suretriggers' ),
				'description'         => __( 'Returns all automation triggers currently saved in OttoKit settings. Each entry shows the integration slug (e.g. WooCommerce) and the trigger event identifier. An empty list means no automations have been configured via the OttoKit SaaS dashboard yet. Use this to show the user which events are actively being watched on their site.', 'suretriggers' ),
				'category'            => 'ottokit-automation',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'triggers' => [
							'type'        => 'array',
							'description' => __( 'List of configured trigger automations.', 'suretriggers' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'integration' => [
										'type'        => 'string',
										'description' => __( 'Integration slug (e.g. WooCommerce, FluentCRM).', 'suretriggers' ),
									],
									'trigger'     => [
										'type'        => 'string',
										'description' => __( 'Trigger event identifier.', 'suretriggers' ),
									],
								],
							],
						],
						'count'    => [
							'type'        => 'integer',
							'description' => __( 'Total number of configured triggers.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_active_triggers' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'If the triggers list is empty, tell the user they have not set up any automations via the OttoKit SaaS dashboard yet. Use this before suggesting any setup steps.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-active-triggers.
	 *
	 * @param array $input Ability input (unused).
	 * @return array
	 */
	public function execute_get_active_triggers( $input ) {
		$raw = OptionController::get_option( 'triggers' );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		$triggers = [];
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$triggers[] = [
				'integration' => isset( $item['integration'] ) ? (string) $item['integration'] : '',
				'trigger'     => isset( $item['trigger'] ) ? (string) $item['trigger'] : '',
			];
		}

		return [
			'triggers' => $triggers,
			'count'    => count( $triggers ),
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-active-integrations
	// -------------------------------------------------------------------------

	/**
	 * Register the get-active-integrations ability.
	 *
	 * @return void
	 */
	private function register_get_active_integrations() {
		wp_register_ability(
			'ottokit/get-active-integrations',
			[
				'label'               => __( 'Get active OttoKit integrations', 'suretriggers' ),
				'description'         => __( 'Returns the slugs of all OttoKit-supported integrations whose plugin is currently installed and active on this WordPress site. An integration appears here only if its plugin is both installed and activated. Use this to confirm which plugins OttoKit can trigger or act upon right now.', 'suretriggers' ),
				'category'            => 'ottokit-automation',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'integrations' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => __( 'Integration slugs that are active (plugin installed and enabled).', 'suretriggers' ),
						],
						'count'        => [
							'type'        => 'integer',
							'description' => __( 'Number of active integrations.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_active_integrations' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'If an integration the user expects is missing, its plugin is not installed or not activated. Suggest using ottokit/get-all-integrations with status=inactive to see what OttoKit-supported plugins are missing.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-active-integrations.
	 *
	 * @param array $input Ability input (unused).
	 * @return array
	 */
	public function execute_get_active_integrations( $input ) {
		$integrations = IntegrationsController::get_activated_integrations();
		if ( ! is_array( $integrations ) ) {
			$integrations = [];
		}

		return [
			'integrations' => array_values( $integrations ),
			'count'        => count( $integrations ),
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-all-integrations
	// -------------------------------------------------------------------------

	/**
	 * Register the get-all-integrations ability.
	 *
	 * @return void
	 */
	private function register_get_all_integrations() {
		wp_register_ability(
			'ottokit/get-all-integrations',
			[
				'label'               => __( 'Get all OttoKit integrations with status', 'suretriggers' ),
				'description'         => __( 'Returns all OttoKit-supported integrations with their enabled status. Filter by status: active (plugin installed and running), inactive (plugin missing or deactivated), or all. Always includes active_count and inactive_count totals regardless of the filter. Use status=inactive to help the user discover which OttoKit-supported plugins they could install.', 'suretriggers' ),
				'category'            => 'ottokit-automation',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'status' => [
							'type'        => 'string',
							'enum'        => [ 'all', 'active', 'inactive' ],
							'description' => __( 'Filter integrations by status. Defaults to all.', 'suretriggers' ),
							'default'     => 'all',
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'integrations'   => [
							'type'        => 'array',
							'description' => __( 'List of integrations with their ID and enabled status.', 'suretriggers' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'      => [
										'type'        => 'string',
										'description' => __( 'Integration slug.', 'suretriggers' ),
									],
									'enabled' => [
										'type'        => 'boolean',
										'description' => __( 'Whether the integration plugin is installed and active.', 'suretriggers' ),
									],
								],
							],
						],
						'total'          => [
							'type'        => 'integer',
							'description' => __( 'Total integrations in the filtered result.', 'suretriggers' ),
						],
						'active_count'   => [
							'type'        => 'integer',
							'description' => __( 'Number of active integrations across all supported integrations.', 'suretriggers' ),
						],
						'inactive_count' => [
							'type'        => 'integer',
							'description' => __( 'Number of inactive integrations across all supported integrations.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_all_integrations' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'Use status=active to show available integrations. Use status=inactive to help the user discover OttoKit-supported plugins they could install. Always include active_count and inactive_count in your response.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-all-integrations.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public function execute_get_all_integrations( $input ) {
		$all_classes   = IntegrationsController::get_integrations();
		$status_filter = isset( $input['status'] ) ? (string) $input['status'] : 'all';
		$allowed       = [ 'all', 'active', 'inactive' ];
		if ( ! in_array( $status_filter, $allowed, true ) ) {
			$status_filter = 'all';
		}

		$all_integrations = [];
		$active_count     = 0;
		$inactive_count   = 0;

		foreach ( $all_classes as $id => $class ) {
			$enabled = $class->is_enabled();

			if ( $enabled ) {
				$active_count++;
			} else {
				$inactive_count++;
			}

			if ( 'all' === $status_filter ||
				( 'active' === $status_filter && $enabled ) ||
				( 'inactive' === $status_filter && ! $enabled )
			) {
				$all_integrations[] = [
					'id'      => (string) $id,
					'enabled' => $enabled,
				];
			}
		}

		return [
			'integrations'   => $all_integrations,
			'total'          => count( $all_integrations ),
			'active_count'   => $active_count,
			'inactive_count' => $inactive_count,
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-integration-events
	// -------------------------------------------------------------------------

	/**
	 * Register the get-integration-events ability.
	 *
	 * @return void
	 */
	private function register_get_integration_events() {
		wp_register_ability(
			'ottokit/get-integration-events',
			[
				'label'               => __( 'Get triggers and actions for an integration', 'suretriggers' ),
				'description'         => __( 'Returns all registered triggers and actions for a named OttoKit integration. Triggers fire when an event occurs in the plugin (e.g. a WooCommerce order is placed). Actions perform operations in that plugin (e.g. enrol a user in a course). Requires an exact integration slug — call ottokit/get-active-integrations first to get valid slugs. Returns trigger_count and action_count.', 'suretriggers' ),
				'category'            => 'ottokit-automation',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'integration' ],
					'properties' => [
						'integration' => [
							'type'        => 'string',
							'description' => __( 'Integration slug (e.g. WooCommerce, FluentCRM, WordPress). Use get-active-integrations to list valid IDs.', 'suretriggers' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'integration'   => [
							'type'        => 'string',
							'description' => __( 'The requested integration slug.', 'suretriggers' ),
						],
						'triggers'      => [
							'type'        => 'object',
							'description' => __( 'Registered triggers keyed by trigger identifier.', 'suretriggers' ),
						],
						'actions'       => [
							'type'        => 'object',
							'description' => __( 'Registered actions keyed by action identifier.', 'suretriggers' ),
						],
						'trigger_count' => [
							'type'        => 'integer',
							'description' => __( 'Number of registered triggers for this integration.', 'suretriggers' ),
						],
						'action_count'  => [
							'type'        => 'integer',
							'description' => __( 'Number of registered actions for this integration.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_integration_events' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'Always call ottokit/get-active-integrations first to confirm the integration slug is valid. Integration slugs are case-sensitive. If the integration is not found, show the user the valid slugs from get-active-integrations.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-integration-events.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_get_integration_events( $input ) {
		if ( empty( $input['integration'] ) || ! is_string( $input['integration'] ) ) {
			return new \WP_Error(
				'missing_integration',
				__( 'The integration parameter is required.', 'suretriggers' )
			);
		}

		$integration_id = sanitize_text_field( $input['integration'] );
		$event_ctrl     = EventController::get_instance();

		$triggers = isset( $event_ctrl->triggers[ $integration_id ] ) && is_array( $event_ctrl->triggers[ $integration_id ] )
			? $event_ctrl->triggers[ $integration_id ]
			: [];

		$actions = isset( $event_ctrl->actions[ $integration_id ] ) && is_array( $event_ctrl->actions[ $integration_id ] )
			? $event_ctrl->actions[ $integration_id ]
			: [];

		if ( empty( $triggers ) && empty( $actions ) ) {
			$valid_ids = array_keys( $event_ctrl->triggers );
			return new \WP_Error(
				'integration_not_found',
				sprintf(
					/* translators: %s: integration slug */
					__( 'No events found for integration "%s". Use get-active-integrations to get valid integration IDs.', 'suretriggers' ),
					$integration_id
				),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? [ 'available_integrations' => $valid_ids ] : []
			);
		}

		return [
			'integration'   => $integration_id,
			'triggers'      => $triggers,
			'actions'       => $actions,
			'trigger_count' => count( $triggers ),
			'action_count'  => count( $actions ),
		];
	}

	// -------------------------------------------------------------------------
	// ottokit/get-webhook-logs
	// -------------------------------------------------------------------------

	/**
	 * Register the get-webhook-logs ability.
	 *
	 * @return void
	 */
	private function register_get_webhook_logs() {
		wp_register_ability(
			'ottokit/get-webhook-logs',
			[
				'label'               => __( 'Get webhook execution logs', 'suretriggers' ),
				'description'         => __( 'Returns paginated webhook execution logs from the OttoKit database. Filter by status (success, failed, pending, all), date_after, and date_before. Each entry includes: HTTP response_code, error_info message, retry_attempts count, and timestamps. A 401 or 403 response_code means an authentication problem. Use this to debug failed automation triggers.', 'suretriggers' ),
				'category'            => 'ottokit-automation',
				'permission_callback' => [ $this, 'permission_manage_options' ],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'status'      => [
							'type'        => 'string',
							'enum'        => [ 'all', 'success', 'failed', 'pending' ],
							'description' => __( 'Filter logs by execution status. Defaults to all.', 'suretriggers' ),
							'default'     => 'all',
						],
						'date_after'  => [
							'type'        => 'string',
							'description' => __( 'Return logs created after this datetime (ISO 8601 or MySQL format, e.g. "2026-03-05 00:00:00"). Optional.', 'suretriggers' ),
						],
						'date_before' => [
							'type'        => 'string',
							'description' => __( 'Return logs created before this datetime (ISO 8601 or MySQL format, e.g. "2026-03-06 23:59:59"). Optional.', 'suretriggers' ),
						],
						'per_page'    => [
							'type'        => 'integer',
							'description' => __( 'Number of logs per page. Min 1, max 100. Defaults to 20.', 'suretriggers' ),
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						],
						'page'        => [
							'type'        => 'integer',
							'description' => __( 'Page number (1-indexed). Defaults to 1.', 'suretriggers' ),
							'default'     => 1,
							'minimum'     => 1,
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'logs'        => [
							'type'        => 'array',
							'description' => __( 'Webhook log entries.', 'suretriggers' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'             => [ 'type' => 'integer' ],
									'status'         => [ 'type' => 'string' ],
									'response_code'  => [ 'type' => 'integer' ],
									'error_info'     => [ 'type' => 'string' ],
									'retry_attempts' => [ 'type' => 'integer' ],
									'created_at'     => [ 'type' => 'string' ],
									'updated_at'     => [ 'type' => [ 'string', 'null' ] ],
								],
							],
						],
						'total'       => [
							'type'        => 'integer',
							'description' => __( 'Total matching log entries.', 'suretriggers' ),
						],
						'page'        => [
							'type'        => 'integer',
							'description' => __( 'Current page number.', 'suretriggers' ),
						],
						'per_page'    => [
							'type'        => 'integer',
							'description' => __( 'Logs per page.', 'suretriggers' ),
						],
						'total_pages' => [
							'type'        => 'integer',
							'description' => __( 'Total number of pages.', 'suretriggers' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_get_webhook_logs' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
						'priority'     => 1.0,
						'instructions' => 'When investigating failures, always filter by status=failed. Report the total count, the most recent failure (created_at, error_info, response_code), and retry_attempts. A 401 or 403 response_code means an authentication issue — suggest running ottokit/get-connection-status. Use date_after to limit to recent failures.',
					],
					'mcp'          => [ 'public' => true ],
				],
			]
		);
	}

	/**
	 * Execute get-webhook-logs.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_get_webhook_logs( $input ) {
		global $wpdb;

		$allowed_statuses = [ 'success', 'failed', 'pending' ];
		$status           = isset( $input['status'] ) ? (string) $input['status'] : 'all';
		if ( 'all' !== $status && ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'all';
		}

		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$per_page = max( 1, min( 100, $per_page ) );

		$page   = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * $per_page;

		// Date filters — validate by attempting to parse via strtotime.
		$date_after  = isset( $input['date_after'] ) ? sanitize_text_field( (string) $input['date_after'] ) : '';
		$date_before = isset( $input['date_before'] ) ? sanitize_text_field( (string) $input['date_before'] ) : '';

		if ( '' !== $date_after ) {
			$ts = strtotime( $date_after );
			if ( false === $ts ) {
				$date_after = '';
			} else {
				$date_after = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		if ( '' !== $date_before ) {
			$ts = strtotime( $date_before );
			if ( false === $ts ) {
				$date_before = '';
			} else {
				$date_before = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$table = WebhookRequestsController::get_table_name();

		$total = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE (%s = 'all' OR status = %s) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s)", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$status,
				$date_after,
				$date_after,
				$date_before,
				$date_before
			)
		);
		$rows  = $wpdb->get_results( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, status, response_code, error_info, retry_attempts, created_at, updated_at FROM {$table} WHERE (%s = 'all' OR status = %s) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) ORDER BY id DESC LIMIT %d OFFSET %d", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$status,
				$date_after,
				$date_after,
				$date_before,
				$date_before,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$logs = [];
		foreach ( $rows as $row ) {
			$logs[] = [
				'id'             => (int) $row['id'],
				'status'         => (string) $row['status'],
				'response_code'  => (int) $row['response_code'],
				'error_info'     => (string) $row['error_info'],
				'retry_attempts' => (int) $row['retry_attempts'],
				'created_at'     => (string) $row['created_at'],
				'updated_at'     => isset( $row['updated_at'] ) && ! empty( $row['updated_at'] ) ? (string) $row['updated_at'] : null,
			];
		}

		$total_pages = ( $per_page > 0 ) ? (int) ceil( $total / $per_page ) : 0;

		return [
			'logs'        => $logs,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		];
	}

	// -------------------------------------------------------------------------
	// Public permission callbacks (must be public for add_action compatibility)
	// -------------------------------------------------------------------------

	/**
	 * Public permission callback — manage_options capability.
	 *
	 * @return bool
	 */
	public function permission_manage_options() {
		return current_user_can( 'manage_options' );
	}
}
