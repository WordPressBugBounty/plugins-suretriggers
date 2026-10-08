<?php
/**
 * WordPress core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\WordPress;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class WordPress
 *
 * @package SureTriggers\Integrations\Wordpress
 */
class WordPress extends Integrations {


	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'WordPress';


	/**
	 * Get user context data.
	 *
	 * @param int $id ID.
	 *
	 * @return array
	 */
	public static function get_user_context( $id ) {

		$user    = get_userdata( $id );
		$context = [];
		if ( ! $user ) {
			return $context;
		}
		$context['wp_user_id']      = $user->ID;
		$context['user_login']      = $user->user_login;
		$context['display_name']    = $user->display_name;
		$context['user_firstname']  = $user->user_firstname;
		$context['user_lastname']   = $user->user_lastname;
		$context['user_email']      = $user->user_email;
		$context['user_registered'] = $user->user_registered;
		$context['user_role']       = $user->roles;
		return $context;
	}

	/**
	 * Get sample user context data.
	 *
	 * @return string[]
	 */
	public static function get_sample_user_context() {
		return [
			'wp_user_id'      => '1',
			'user_login'      => 'john_doe',
			'display_name'    => 'John Doe',
			'user_firstname'  => 'John',
			'user_lastname'   => 'Doe',
			'user_email'      => 'johnd@gmail.com',
			'user_registered' => '2024-06-18 09:47:58',
			'user_role'       => 'active',
		];
	}

	/**
	 * Get post context data.
	 *
	 * @param int $id ID.
	 *
	 * @return array
	 */
	public static function get_post_context( $id ) {
		$post_data = (array) get_post( $id );
		
		// Add permalink to post context.
		if ( ! empty( $post_data ) && isset( $post_data['ID'] ) ) {
			$post_data['permalink'] = get_permalink( $post_data['ID'] );
		}
		
		return $post_data;
	}

	/**
	 * Gets the post meta
	 *
	 * @param int $id ID.
	 *
	 * @return mixed
	 */
	public static function get_post_meta( $id ) {
		return get_post_meta( $id );
	}

	/**
	 * Validating the Email
	 *
	 * @param string $email email.
	 * @return object{valid: bool, multiple: bool}
	 */
	public static function validate_email( $email ) {
		$result = [
			'valid'    => true,
			'multiple' => false,
		];

		if ( str_contains( $email, ',' ) ) {
			$email_list = explode( ',', $email );

			foreach ( $email_list as $single_email ) {
				if ( ! is_email( trim( $single_email ) ) ) {
					$result['valid']    = false;
					$result['multiple'] = true;

					break;
				}
			}
		} else {
			if ( ! is_email( trim( $email ) ) ) {
				$result['valid'] = false;
			}
		}

		return (object) $result;
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return true;
	}


	/**
	 * Maximum number of email attachments downloaded per action run.
	 *
	 * @var int
	 */
	const MAX_ATTACHMENTS = 5;

	/**
	 * Maximum combined size of downloaded email attachments, in bytes (25 MB).
	 *
	 * @var int
	 */
	const MAX_ATTACHMENTS_BYTES = 26214400;

	/**
	 * Download attachment URLs to temporary local files for wp_mail().
	 *
	 * Each file is streamed into its own temp directory under its original
	 * (sanitized) file name, so the recipient sees e.g. `report.pdf` rather than
	 * a `.tmp` name. The response size is capped while streaming, and
	 * wp_safe_remote_get() rejects internal/reserved-IP hosts (also across
	 * redirects), guarding against SSRF via this field.
	 *
	 * @param string|array $attachment_urls Comma-separated URLs, or an array of URLs.
	 * @return array{attachments: string[], skipped: int} Local file paths to attach (pass to cleanup_attachments() afterwards), and how many URLs were skipped.
	 */
	public static function download_attachments( $attachment_urls ) {
		$attachments = [];
		$skipped     = 0;
		$total_bytes = 0;

		if ( is_string( $attachment_urls ) ) {
			$attachment_urls = array_map( 'trim', explode( ',', $attachment_urls ) );
		}

		if ( empty( $attachment_urls ) || ! is_array( $attachment_urls ) ) {
			return [
				'attachments' => $attachments,
				'skipped'     => $skipped,
			];
		}

		foreach ( $attachment_urls as $url ) {
			$raw_url = is_string( $url ) ? trim( $url ) : '';

			if ( '' === $raw_url ) {
				continue;
			}

			$url = esc_url_raw( $raw_url );

			$remaining = self::MAX_ATTACHMENTS_BYTES - $total_bytes;

			if ( '' === $url || count( $attachments ) >= self::MAX_ATTACHMENTS || $remaining <= 0 || ! wp_http_validate_url( $url ) ) {
				$skipped++;
				continue;
			}

			$dir = trailingslashit( get_temp_dir() ) . 'st-' . wp_generate_password( 12, false );
			if ( ! wp_mkdir_p( $dir ) ) {
				$skipped++;
				continue;
			}

			$name = sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
			$file = $dir . '/' . ( '' !== $name ? $name : 'attachment' );

			$response = wp_safe_remote_get(
				$url,
				[
					'timeout'             => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- Attachments come from arbitrary user-supplied hosts.
					'redirection'         => 3,
					'stream'              => true,
					'filename'            => $file,
					'limit_response_size' => $remaining + 1,
				]
			);

			$size = file_exists( $file ) ? (int) filesize( $file ) : 0;

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || $size <= 0 || $size > $remaining ) {
				self::cleanup_attachments( [ $file ] );
				$skipped++;
				continue;
			}

			$attachments[] = $file;
			$total_bytes  += $size;
		}

		return [
			'attachments' => $attachments,
			'skipped'     => $skipped,
		];
	}

	/**
	 * Delete files created by download_attachments(), along with their temp directories.
	 *
	 * @param string[] $files Local file paths.
	 * @return void
	 */
	public static function cleanup_attachments( $files ) {
		foreach ( (array) $files as $file ) {
			wp_delete_file( $file );

			$dir = dirname( $file );
			if ( 0 === strpos( basename( $dir ), 'st-' ) && is_dir( $dir ) ) {
				rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
			}
		}
	}

}

IntegrationsController::register( WordPress::class );
