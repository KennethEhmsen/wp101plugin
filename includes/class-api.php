<?php
/**
 * API integration with WP101.
 *
 * @package WP101
 */

namespace WP101;

use WP_Error;

class API {

	/**
	 * The user's WP101 API key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * The Singleton instance.
	 *
	 * @var API
	 */
	protected static $instance;

	/**
	 * Base URL for the WP101 plugin API.
	 *
	 * This value can be overridden via the WP101_API_URL constant.
	 *
	 * @var string
	 */
	const API_URL = 'https://wp101plugin.com/api';

	/**
	 * Option key for the site's public API key.
	 *
	 * @var string
	 */
	const PUBLIC_API_KEY_OPTION = 'wp101-public-api-key';

	/**
	 * The User-Agent string that will be passed with API requests.
	 *
	 * @var string
	 */
	const USER_AGENT = 'WP101-Plugin';

	/**
	 * Construct a new instance of the API.
	 */
	protected function __construct() {}

	/**
	 * Prevent the object from being cloned.
	 */
	private function __clone() {}

	/**
	 * Prevent the object from being deserialized.
	 */
	private function __wakeup() {}

	/**
	 * Retrieve the singular instance of the class.
	 *
	 * @return API The API instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new API();
		}

		return self::$instance;
	}

	/**
	 * Retrieve the API key.
	 *
	 * @return string The API key.
	 */
	public function get_api_key() {
		if ( $this->api_key ) {
			return $this->api_key;
		}

		if ( defined( 'WP101_API_KEY' ) ) {
			$this->api_key = WP101_API_KEY;
		} else {
			$this->api_key = get_option( 'wp101_api_key', '' );
		}

		return $this->api_key;
	}

	/**
	 * Retrieve the public API key from WP101.
	 *
	 * Public API keys are generated on a per-domain basis by the WP101 API, and thus are safe for
	 * using client-side without fear of compromising the private key.
	 *
	 * @return string The public API key.
	 */
	public function get_public_api_key() {
		$public_key = get_option( self::PUBLIC_API_KEY_OPTION );

		if ( $public_key ) {
			return $public_key;
		}

		$response = $this->send_request( 'GET', '/account' );

		if ( is_wp_error( $response ) ) {
			return $response;

		} elseif ( ! isset( $response['publicKey'] ) || empty( $response['publicKey'] ) ) {
			return new WP_Error( 'missing-public-key', __( 'Unable to retrieve a valid public key from WP101.' ) );
		}

		$public_key = $response['publicKey'];

		update_option( self::PUBLIC_API_KEY_OPTION, $public_key, false );

		return $public_key;
	}

	/**
	 * Retrieve all available add-ons for WP101.
	 *
	 * @return array An array of all available add-ons.
	 */
	public function get_addons() {
		$response = $this->send_request( 'GET', '/add-ons' );

		if ( is_wp_error( $response ) ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( esc_html( $response->get_error_message() ), E_USER_WARNING );
			// phpcs:enable

			return [
				'addons' => [],
			];
		}

		return $response;
	}

	/**
	 * Retrieve all series available to the user, based on API key.
	 *
	 * @return array An array of all available series and topics.
	 */
	public function get_playlist() {
		$response = $this->send_request( 'GET', '/playlist' );

		if ( is_wp_error( $response ) ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( esc_html( $response->get_error_message() ), E_USER_WARNING );
			// phpcs:enable

			return [
				'series' => [],
			];
		}

		return $response;
	}

	/**
	 * Retrieve a single series by its slug.
	 *
	 * @param string $series The series slug.
	 * @return array|bool The series array for the given slug, or false if the given series was not
	 *                    found in the API-provided playlist.
	 */
	public function get_series( $series ) {
		$playlist = $this->get_playlist();

		// Iterate through the series and their topics to find a match.
		foreach ( (array) $playlist['series'] as $single_series ) {
			if ( $series === $single_series['slug'] ) {
				return $single_series;
			}
		}

		return false;
	}

	/**
	 * Retrieve a single topic by its slug.
	 *
	 * @param string $topic The topic slug.
	 * @return array|bool The topic array for the given slug, or false if the given topic was not
	 *                    found in the API-provided playlist.
	 */
	public function get_topic( $topic ) {
		$playlist = $this->get_playlist();

		// Iterate through the series and their topics to find a match.
		foreach ( (array) $playlist['series'] as $series ) {
			foreach ( $series['topics'] as $single_topic ) {
				if ( $topic === $single_topic['slug'] ) {
					return $single_topic;
				}
			}
		}

		return false;
	}

	/**
	 * Determine if an API key has been set.
	 *
	 * @return bool
	 */
	public function has_api_key() {
		return (bool) $this->get_api_key();
	}

	/**
	 * Determine if the current account has the given capability.
	 *
	 * @param string $cap The capability to check.
	 *
	 * @return bool Whether or not the user's account has the given capability.
	 */
	public function account_can( $cap ) {
		$response = $this->send_request( 'GET', '/account', [], [], 12 * HOUR_IN_SECONDS );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return isset( $response['capabilities'] ) && in_array( $cap, (array) $response['capabilities'], true );
	}

	/**
	 * Build an API request URI.
	 *
	 * @param string $path Optional. The API endpoint. Default is '/'.
	 * @param array  $args Optional. Query string arguments for the URI. Default is empty.
	 * @return string The URI for the API request.
	 */
	protected function build_uri( $path = '/', array $args = [] ) {
		$base = defined( 'WP101_API_URL' ) ? WP101_API_URL : self::API_URL;

		// Ensure the $path has a leading slash.
		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		return add_query_arg( $args, $base . $path );
	}

	/**
	 * Send a request to the WP101 API.
	 *
	 * @param string $method The HTTP method.
	 * @param string $path   The API request path.
	 * @param array  $query  Optional. Query string arguments. Default is empty.
	 * @param array  $args   Optional. Additional HTTP arguments. For a full list of options,
	 *                       see wp_remote_request().
	 * @param int    $cache  Optional. The number of seconds for which the result should be cached.
	 *                       Default is 0 seconds (no caching).
	 *
	 * @return array|WP_Error The HTTP response body or a WP_Error object if something went wrong.
	 */
	protected function send_request( $method, $path, $query = [], $args = [], $cache = 0 ) {
		$uri       = $this->build_uri( $path, $query );
		$args      = wp_parse_args( $args, [
			'timeout'    => 30,
			'user-agent' => self::USER_AGENT,
			'headers'    => [
				'Authorization'    => 'Bearer ' . $this->get_api_key(),
				'Method'           => $method,
				'X-Forwarded-Host' => site_url(),
			],
		] );
		$cache_key = self::generate_cache_key( $uri, $args );
		$cached    = get_transient( $cache_key );

		// Return the cached version, if we have it.
		if ( $cache && $cached ) {
			return $cached;
		}

		$response = wp_remote_request( $uri, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'fail' === $body['status'] ) {
			return new WP_Error(
				'wp101-api',
				__( 'The WP101 API request failed.', 'wp101' ),
				$body['data']
			);
		}

		// Cache the result.
		if ( $cache ) {
			set_transient( $cache_key, $body['data'], $cache );
		}

		return $body['data'];
	}

	/**
	 * Given a URI and arguments, generate a cache key for use with WP101's internal caching system.
	 *
	 * @param string $uri  The API URI, with any query string arguments.
	 * @param array  $args Optional. An array of HTTP arguments used in the request. Default is empty.
	 * @return string A cache key.
	 */
	public static function generate_cache_key( $uri, $args = [] ) {
		return 'wp101_' . substr( md5( $uri . wp_json_encode( $args ) ), 0, 12 );
	}
}