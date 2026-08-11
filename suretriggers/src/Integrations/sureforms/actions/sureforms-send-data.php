<?php
/**
 * SureFormsSendData.
 * php version 5.6
 *
 * @category SureFormsSendData
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureForms\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * SureFormsSendData
 *
 * @category SureFormsSendData
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureFormsSendData extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureForms';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'sureforms_send_data';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Send Data', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 * @psalm-suppress UndefinedMethod
	 * 
	 * @throws \Exception Exception.
	 *
	 * @return array|mixed
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$endpoint_url    = isset( $selected_options['endpoint_url'] ) ? esc_url( $selected_options['endpoint_url'] ) : '';
		$sf_data         = isset( $selected_options['sf_data_body'] ) ? $selected_options['sf_data_body'] : '';
		$file_attachment = isset( $selected_options['sf_attachment'] ) ? $selected_options['sf_attachment'] : '';

		// Handling SSRF Attack.
		$host = wp_parse_url( $endpoint_url, PHP_URL_HOST );

		if ( empty( $host ) || $this->is_host_blocked( $host ) ) {
			throw new \Exception( 'Access blocked.' );
		}

		$form_data = [
			'body'       => $sf_data,
			'attachment' => $file_attachment,
		];
		$json_body = wp_json_encode( $form_data );
		if ( false === $json_body ) {
			throw new \Exception( 'Failed to encode form data to JSON.' );
		}

		$args = [
			'method'    => 'POST',
			'headers'   => [
				'Content-Type' => 'application/json',
				'User-Agent'   => 'SureTriggers',
			],
			'sslverify' => true,
			'timeout'   => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			'body'      => $json_body,
		];

		if ( null === $endpoint_url ) {
			return [];
		}
		// Send the HTTP request based on the method. wp_safe_remote_request() re-validates
		// the resolved host (including on redirects) against internal/reserved IP ranges.
		$response = wp_safe_remote_request( $endpoint_url, $args );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			if ( ! empty( $selected_options['test_action'] ) ) {
				return [
					'success' => false,
					'message' => 'Error: ' . $error_message,
				];
			}
			throw new \Exception( 'Request failed: ' . $error_message );
		}
		
		// Check for successful HTTP status codes (200, 201, 204).
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, [ 200, 201, 204 ], true ) ) {
			$error = 'Failed to communicate with the API: ' . $endpoint_url;
			if ( ! empty( $selected_options['test_action'] ) ) {
				return [
					'success' => false,
					'message' => $error,
				];
			}
			throw new \Exception( 'API request failed: ' . wp_remote_retrieve_body( $response ) );
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$result = [ 'response' => wp_remote_retrieve_body( $response ) ];
		}
		return $result;
	}

	/**
	 * Determine whether a host resolves to a private or reserved IP address.
	 *
	 * Resolves the host name (normalizing decimal/octal/hex IP literals first)
	 * and validates every resolved address against private and reserved IP
	 * ranges, rather than string-comparing the host against CIDR literals.
	 *
	 * @param string $host Host name or IP literal parsed from the endpoint URL.
	 * @return bool
	 */
	private function is_host_blocked( $host ) {
		$host = strtolower( trim( $host, '[]' ) );

		if ( 'localhost' === $host ) {
			return true;
		}

		$ips = $this->resolve_host_ips( $host );

		if ( empty( $ips ) ) {
			// Host could not be resolved to any address - fail securely.
			return true;
		}

		foreach ( $ips as $ip ) {
			if ( $this->is_blocked_ip( $ip ) ) {
				return true;
			}

			// Unwrap IPv4-mapped IPv6 addresses (e.g. ::ffff:127.0.0.1) - PHP's
			// range flags don't evaluate the embedded IPv4 address on their own.
			$mapped_ipv4 = $this->extract_mapped_ipv4( $ip );
			if ( null !== $mapped_ipv4 && $this->is_blocked_ip( $mapped_ipv4 ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a single IP address against private and reserved IP ranges.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private function is_blocked_ip( $ip ) {
		return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Extract the embedded IPv4 address from an IPv4-mapped IPv6 address.
	 *
	 * @param string $ip IP address.
	 * @return string|null
	 */
	private function extract_mapped_ipv4( $ip ) {
		$binary = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Generic.PHP.NoSilencedErrors.Discouraged
		if ( false === $binary || 16 !== strlen( $binary ) ) {
			return null;
		}

		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" !== substr( $binary, 0, 12 ) ) {
			return null;
		}

		$ipv4 = inet_ntop( substr( $binary, 12, 4 ) );
		return false !== $ipv4 ? $ipv4 : null;
	}

	/**
	 * Resolve a host name (or IP literal) to its IP address(es).
	 *
	 * @param string $host Host name or IP literal.
	 * @return string[]
	 */
	private function resolve_host_ips( $host ) {
		$normalized = $this->normalize_ip_literal( $host );

		if ( null !== $normalized ) {
			return [ $normalized ];
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}

		$ips = gethostbynamel( $host );
		$ips = false !== $ips ? $ips : [];

		if ( function_exists( 'dns_get_record' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Generic.PHP.NoSilencedErrors.Discouraged
			$records = @dns_get_record( $host, DNS_AAAA );
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = $record['ipv6'];
					}
				}
			}
		}

		return $ips;
	}

	/**
	 * Normalize decimal, octal, and hexadecimal IPv4 literals (e.g. `2130706433`
	 * or `0177.0.0.1`) to dotted-quad form so they can't slip past validation
	 * in a format that never gets resolved via DNS.
	 *
	 * @param string $host Host name or IP literal.
	 * @return string|null
	 */
	private function normalize_ip_literal( $host ) {
		if ( ctype_digit( $host ) && strlen( $host ) <= 10 ) {
			$decimal = (float) $host;
			if ( $decimal >= 0 && $decimal <= 4294967295 ) {
				$ip = long2ip( (int) $decimal );
				return false !== $ip ? $ip : null;
			}
		}

		$parts = explode( '.', $host );
		if ( 4 !== count( $parts ) ) {
			return null;
		}

		$octets = [];
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^(0x[0-9a-f]+|0[0-7]*|[1-9][0-9]*)$/i', $part ) ) {
				return null;
			}
			if ( 0 === strncasecmp( $part, '0x', 2 ) ) {
				$octet = hexdec( $part );
			} elseif ( strlen( $part ) > 1 && '0' === $part[0] ) {
				$octet = octdec( $part );
			} else {
				$octet = (int) $part;
			}
			if ( $octet < 0 || $octet > 255 ) {
				return null;
			}
			$octets[] = $octet;
		}

		return implode( '.', $octets );
	}
}

SureFormsSendData::get_instance();
