<?php
/**
 * SureTriggers Outgoing Requests Page.
 * php version 5.6
 *
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

use SureTriggers\Controllers\WebhookRequestsController;
global $wpdb;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * SureTriggersWebhookRequestsTable - List table for Webhook requests.
 *
 * @category SureTriggersWebhookRequestsTable
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 *
 * @psalm-suppress UndefinedTrait
 */
class SureTriggersWebhookRequestsTable extends WP_List_Table {

	/**
	 * Webhook Requests List Table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Initialise data.
	 *
	 * @param string $table_name Table Name.
	 */
	public function __construct( $table_name ) {
		parent::__construct(
			[
				'singular' => 'webhook_request',
				'plural'   => 'webhook_requests',
				'ajax'     => false,
			]
		);

		$this->table_name = $table_name;
	}

	/**
	 * Table Classes.
	 *
	 * @return array
	 */
	protected function table_classes() {
		return [ 'wp-list-table', 'widefat', 'striped' ];
	}

	/**
	 * Table Display.
	 *
	 * @return void
	 */
	public function display() {
		$this->display_tablenav( 'top' );
		?>
		<table class="<?php echo esc_attr( implode( ' ', $this->table_classes() ) ); ?>">
			<thead>
				<?php $this->print_column_headers(); ?>
			</thead>
			<tbody id="the-list" data-wp-lists="list:<?php echo esc_attr( $this->_args['singular'] ); ?>">
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>
			<tfoot>
				<?php $this->print_column_headers( false ); ?>
			</tfoot>
		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Get Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'              => '<input type="checkbox" />',
			'id'              => __( 'ID', 'suretriggers' ),
			'response_code'   => __( 'Response Code', 'suretriggers' ),
			'status'          => __( 'Status', 'suretriggers' ),
			'trigger_event'   => __( 'Trigger Event', 'suretriggers' ),
			'error_info'      => __( 'Error Info', 'suretriggers' ),
			'created_at'      => __( 'Created At', 'suretriggers' ),
			'request_actions' => __( 'Actions', 'suretriggers' ),
		];
	}

	/**
	 * Get Sortable Columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'id'         => [ 'id', true ],
			'created_at' => [ 'created_at', false ],
		];
	}

	/**
	 * Checkbox column for bulk actions.
	 *
	 * @param array $item Item.
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		$id = absint( $item['id'] );
		return '<input id="cb-select-' . $id . '" type="checkbox" name="st_bulk_ids[]" value="' . $id . '" />'
			. '<label for="cb-select-' . $id . '"><span class="screen-reader-text">'
			. sprintf( esc_html__( 'Select request %s', 'suretriggers' ), $id )
			. '</span></label>';
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'bulk_retry'  => __( 'Retry Selected', 'suretriggers' ),
			'bulk_delete' => __( 'Delete Selected', 'suretriggers' ),
		];
	}

	/**
	 * Column actions — Retry/Rerun and View Response buttons.
	 *
	 * @param array $item Item.
	 *
	 * @return string
	 */
	public function column_request_actions( $item ) {
		$id     = absint( $item['id'] );
		$output = '';

		if ( 'failed' === $item['status'] ) {
			$output .= '<button type="button" class="button button-primary st-retry-btn" onclick="stRetryRequest(' . $id . ')">' . esc_html__( 'Retry', 'suretriggers' ) . '</button> ';
		} elseif ( 'success' === $item['status'] ) {
			$output .= '<button type="button" class="button st-retry-btn" onclick="stRetryRequest(' . $id . ')">' . esc_html__( 'Rerun', 'suretriggers' ) . '</button> ';
		}

		$output .= '<button type="button" class="button st-view-response-btn" onclick="stViewResponse(' . $id . ')">' . esc_html__( 'View Response', 'suretriggers' ) . '</button>';

		return $output;
	}

	/**
	 * Column trigger event.
	 *
	 * @param array $item Item.
	 *
	 * @return mixed|string
	 */
	public function column_trigger_event( $item ) {
		$data = $item['request_data'];
		$data = json_decode( $data, true );
		if ( is_array( $data ) && isset( $data['body']['trigger'] ) ) {
			return $data['body']['trigger'];
		}
		return '';
	}

	/**
	 * Prepare Items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [ $columns, [], $sortable ];

		if ( isset( $_POST['suretriggers_requests_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( $_POST['suretriggers_requests_nonce'] ), 'suretriggers_requests_nonce_action' )
			&& current_user_can( 'manage_options' )
		) {
			// Handle single retry/rerun.
			if ( ! empty( $_POST['retry_st_request'] ) && isset( $_POST['st_retry_id'] ) ) {
				$id = absint( $_POST['st_retry_id'] );
				if ( $id ) {
					WebhookRequestsController::suretriggers_retry_trigger_request( $id );
				}
			}

			// Handle bulk actions.
			$bulk_action = isset( $_POST['st_bulk_action'] ) ? sanitize_text_field( $_POST['st_bulk_action'] ) : '';
			if ( '' !== $bulk_action && isset( $_POST['st_bulk_ids'] ) ) {
				$bulk_ids = array_map( 'absint', (array) $_POST['st_bulk_ids'] );
				$bulk_ids = array_filter( $bulk_ids );

				if ( ! empty( $bulk_ids ) ) {
					if ( 'bulk_retry' === $bulk_action ) {
						foreach ( $bulk_ids as $bulk_id ) {
							WebhookRequestsController::suretriggers_retry_trigger_request( $bulk_id );
						}
					} elseif ( 'bulk_delete' === $bulk_action ) {
						foreach ( $bulk_ids as $delete_id ) {
							$wpdb->delete(
								$this->table_name,
								[ 'id' => $delete_id ],
								[ '%d' ]
							);
						}
					}
				}
			}
		}

		$status_filter   = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( $_REQUEST['status_filter'] ) : '';
		$date_from       = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( $_REQUEST['date_from'] ) : '';
		$date_to         = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( $_REQUEST['date_to'] ) : '';
		$allowed_orderby = [ 'id', 'response_code', 'status', 'error_info', 'created_at' ];
		$orderby_input   = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'id';
		$orderby         = in_array( $orderby_input, $allowed_orderby, true ) ? $orderby_input : 'id';
		$order           = isset( $_REQUEST['order'] ) && 'DESC' === strtoupper( sanitize_text_field( $_REQUEST['order'] ) ) ? 'DESC' : 'ASC';
		$per_page        = $this->get_items_per_page( 'webhook_requests_per_page', 10 );
		$current_page    = $this->get_pagenum();

		$offset = ( $current_page - 1 ) * $per_page;

		// Build WHERE clause — pre-prepare each condition individually.
		$where_prepared = '1=1';

		if ( '' !== $status_filter ) {
			$allowed_statuses = [ 'success', 'failed', 'pending' ];
			if ( in_array( $status_filter, $allowed_statuses, true ) ) {
				$where_prepared .= $wpdb->prepare( ' AND status = %s', $status_filter );
			}
		}

		if ( '' !== $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where_prepared .= $wpdb->prepare( ' AND created_at >= %s', $date_from . ' 00:00:00' );
		}

		if ( '' !== $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where_prepared .= $wpdb->prepare( ' AND created_at <= %s', $date_to . ' 23:59:59' );
		}

		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}suretriggers_webhook_requests WHERE " . $where_prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where_prepared is built from individual $wpdb->prepare() calls above.

		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT id, response_code, request_data, status, error_info, created_at FROM {$wpdb->prefix}suretriggers_webhook_requests WHERE " . $where_prepared . ' ORDER BY ' . esc_sql( $orderby ) . ' ' . esc_sql( $order ) . ' LIMIT %d OFFSET %d', $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where_prepared is built from individual $wpdb->prepare() calls above, $orderby/$order are allowlist-validated.

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);
	}

	/**
	 * Column Default.
	 *
	 * @param array  $item Item.
	 * @param string $column_name Column Name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Extra table navigation — filters.
	 *
	 * @param string $which Which.
	 *
	 * @return void
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		if ( isset( $_REQUEST['suretriggers_requests_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['suretriggers_requests_nonce'] ), 'suretriggers_requests_nonce_action' ) ) {
			return;
		}

		$status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( $_REQUEST['status_filter'] ) : '';
		$date_from     = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( $_REQUEST['date_from'] ) : '';
		$date_to       = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( $_REQUEST['date_to'] ) : '';
		?>
		<div class="alignleft actions st-filters-row">
			<select name="status_filter">
				<option value=""><?php esc_html_e( 'All Requests', 'suretriggers' ); ?></option>
				<option value="success" <?php selected( $status_filter, 'success' ); ?>>
					<?php esc_html_e( 'Success Requests', 'suretriggers' ); ?>
				</option>
				<option value="failed" <?php selected( $status_filter, 'failed' ); ?>>
					<?php esc_html_e( 'Failed Requests', 'suretriggers' ); ?>
				</option>
				<option value="pending" <?php selected( $status_filter, 'pending' ); ?>>
					<?php esc_html_e( 'Pending Requests', 'suretriggers' ); ?>
				</option>
			</select>

			<label for="st-date-from" class="screen-reader-text"><?php esc_html_e( 'From date', 'suretriggers' ); ?></label>
			<input type="date" id="st-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'suretriggers' ); ?>" class="st-date-input" />

			<label for="st-date-to" class="screen-reader-text"><?php esc_html_e( 'To date', 'suretriggers' ); ?></label>
			<input type="date" id="st-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'suretriggers' ); ?>" class="st-date-input" />

			<input type="submit" name="suretriggers_filter_request" id="suretriggers_filter_request" class="button" value="<?php echo esc_attr__( 'Filter', 'suretriggers' ); ?>">
		</div>
		<?php
	}

}
$table_name = WebhookRequestsController::get_table_name();
$list_table = new SureTriggersWebhookRequestsTable( $table_name );
?>
<form id="suretriggers-requests-table-form" method="post">
	<input type="hidden" name="page" value="suretriggers-status" />
	<input type="hidden" name="tab" value="st_outgoing_requests" />
	<input type="hidden" name="st_retry_id" value="">
	<input type="hidden" name="retry_st_request" value="">
	<input type="hidden" name="st_bulk_action" value="">
	<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'suretriggers_tab_nonce' ) ); ?>" />
	<?php
	wp_nonce_field( 'suretriggers_requests_nonce_action', 'suretriggers_requests_nonce' );
	if ( isset( $_REQUEST['suretriggers_requests_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['suretriggers_requests_nonce'] ), 'suretriggers_requests_nonce_action' ) ) {
		if ( isset( $_REQUEST['status_filter'] ) ) {
			echo '<input type="hidden" name="status_filter" value="' . esc_attr( sanitize_text_field( $_REQUEST['status_filter'] ) ) . '">';
		}
	}
	$list_table->prepare_items();
	$list_table->display();
	echo '<div style="margin-top: 10px;">
		<p dir="auto" style="font-style: italic; color: #666; margin-inline-start: 55%;">';
		esc_html_e( 'Note: Successful outgoing requests will be automatically deleted after 30 days, while failed outgoing requests will be automatically deleted after 60 days.', 'suretriggers' );
	echo '</p>
	</div>';
	?>
</form>

<!-- View Response Modal -->
<div id="st-response-modal" class="st-modal-overlay" style="display:none;">
	<div class="st-modal-content">
		<div class="st-modal-header">
			<h3><?php esc_html_e( 'Request & Response Details', 'suretriggers' ); ?></h3>
			<button type="button" class="st-modal-close" aria-label="<?php esc_attr_e( 'Close', 'suretriggers' ); ?>">&times;</button>
		</div>
		<div class="st-modal-body">
			<div class="st-modal-section">
				<h4><?php esc_html_e( 'Status', 'suretriggers' ); ?></h4>
				<p id="st-modal-status"></p>
			</div>
			<div class="st-modal-section">
				<h4><?php esc_html_e( 'Response Code', 'suretriggers' ); ?></h4>
				<p id="st-modal-response-code"></p>
			</div>
			<div class="st-modal-section">
				<h4><?php esc_html_e( 'Error Info', 'suretriggers' ); ?></h4>
				<p id="st-modal-error-info"></p>
			</div>
			<div class="st-modal-section">
				<h4><?php esc_html_e( 'Trigger Event', 'suretriggers' ); ?></h4>
				<p id="st-modal-trigger-event"></p>
			</div>
			<div class="st-modal-section">
				<h4><?php esc_html_e( 'Created At', 'suretriggers' ); ?></h4>
				<p id="st-modal-created-at"></p>
			</div>
			<div class="st-modal-section">
				<h4><?php esc_html_e( 'Request Data', 'suretriggers' ); ?></h4>
				<pre id="st-modal-request-data" class="st-json-display"></pre>
			</div>
		</div>
	</div>
</div>

<?php
$modal_data = [];
if ( is_array( $list_table->items ) ) {
	foreach ( $list_table->items as $row ) {
		$request_data = json_decode( $row['request_data'], true );
		$trigger      = '';
		if ( is_array( $request_data ) && isset( $request_data['body']['trigger'] ) ) {
			$trigger = $request_data['body']['trigger'];
		}
		// Strip sensitive auth/header fields before exposing to page source.
		$safe_request_data = $request_data;
		if ( is_array( $safe_request_data ) ) {
			unset( $safe_request_data['headers'] );
		}

		$modal_data[ $row['id'] ] = [
			'status'        => $row['status'],
			'response_code' => $row['response_code'],
			'error_info'    => $row['error_info'],
			'trigger_event' => $trigger,
			'created_at'    => $row['created_at'],
			'request_data'  => $safe_request_data,
		];
	}
}
?>
<script type="text/javascript">
	var stModalData = <?php echo wp_json_encode( $modal_data ); ?>;
	var stForm = document.getElementById('suretriggers-requests-table-form');

	function stRetryRequest(id) {
		stForm.querySelector('input[name="st_retry_id"]').value = id;
		stForm.querySelector('input[name="retry_st_request"]').value = '1';
		stForm.querySelector('input[name="st_bulk_action"]').value = '';
		stForm.submit();
	}

	function stViewResponse(id) {
		var data = stModalData[id];
		if (!data) { return; }
		document.getElementById('st-modal-status').textContent = data.status;
		document.getElementById('st-modal-response-code').textContent = data.response_code;
		document.getElementById('st-modal-error-info').textContent = data.error_info || '\u2014';
		document.getElementById('st-modal-trigger-event').textContent = data.trigger_event || '\u2014';
		document.getElementById('st-modal-created-at').textContent = data.created_at;
		document.getElementById('st-modal-request-data').textContent = data.request_data ? JSON.stringify(data.request_data, null, 2) : '\u2014';
		document.getElementById('st-response-modal').style.display = '';
	}

	jQuery(document).ready(function($) {
		$('#suretriggers-requests-table-form #_wpnonce').remove();

		$(document).on('click', '#doaction, #doaction2', function(e) {
			e.preventDefault();
			var action = $(this).prev('select').val();
			if ('-1' === action) { return; }
			var checked = $('input[name="st_bulk_ids[]"]:checked');
			if (checked.length === 0) {
				alert('<?php echo esc_js( __( 'Please select at least one request.', 'suretriggers' ) ); ?>');
				return;
			}
			$('input[name="st_bulk_action"]').val(action);
			$('input[name="st_retry_id"]').val('');
			$('input[name="retry_st_request"]').val('');
			$('form#suretriggers-requests-table-form').submit();
		});

		$('#suretriggers_filter_request').on('click', function(e) {
			e.preventDefault();
			$('<input>').attr({ type: 'hidden', name: 'paged', value: '1' }).appendTo('#suretriggers-requests-table-form');
			$('input[name="st_bulk_action"]').val('');
			$('input[name="st_retry_id"]').val('');
			$('input[name="retry_st_request"]').val('');
			$('#suretriggers-requests-table-form').submit();
		});

		$(document).on('click', '.tablenav-pages a', function(e) {
			var paged = $(this).attr('href').match(/paged=(\d+)/);
			if (paged && paged[1]) {
				e.preventDefault();
				$('<input>').attr({ type: 'hidden', name: 'paged', value: paged[1] }).appendTo('#suretriggers-requests-table-form');
				var filterValue = $('select[name="status_filter"]').val();
				if (filterValue) {
					$('<input>').attr({ type: 'hidden', name: 'status_filter', value: filterValue }).appendTo('#suretriggers-requests-table-form');
				}
				var dateFrom = $('#st-date-from').val();
				if (dateFrom) {
					$('<input>').attr({ type: 'hidden', name: 'date_from', value: dateFrom }).appendTo('#suretriggers-requests-table-form');
				}
				var dateTo = $('#st-date-to').val();
				if (dateTo) {
					$('<input>').attr({ type: 'hidden', name: 'date_to', value: dateTo }).appendTo('#suretriggers-requests-table-form');
				}
				$('#suretriggers-requests-table-form').submit();
			}
		});

		$(document).on('click', '.st-modal-close, .st-modal-overlay', function(e) {
			if (e.target === this) { $('#st-response-modal').hide(); }
		});
		$(document).on('keydown', function(e) {
			if (27 === e.keyCode) { $('#st-response-modal').hide(); }
		});
	});
</script>
