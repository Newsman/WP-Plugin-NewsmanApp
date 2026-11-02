<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing storefront check.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Remarketing;

use Newsman\Util\WooCommerceExist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks from the NewsMAN admin page that the storefront pages print the remarketing scripts.
 *
 * Administrators never get the remarketing scripts on the storefront. The check
 * creates a random one-time token for the current administrator; a storefront
 * request from that administrator carrying the token in the query string gets the
 * scripts as a visitor would. The browser downloads the pages and looks for the
 * scripts in the HTML; the scripts are not executed, so nothing is sent to NewsMAN.
 *
 * @class \Newsman\Remarketing\StorefrontCheck
 */
class StorefrontCheck {
	/**
	 * Query string parameter that carries the token.
	 */
	public const QUERY_PARAM = 'nzm_rm_check';

	/**
	 * AJAX action that starts a check.
	 */
	public const AJAX_ACTION = 'newsman_remarketing_check';

	/**
	 * Nonce action of the AJAX request.
	 */
	public const NONCE_ACTION = 'newsman-remarketing-check';

	/**
	 * Transient prefix, followed by the user ID. Stores the SHA-256 hash of the token.
	 */
	public const TRANSIENT_PREFIX = 'newsman_rm_check_';

	/**
	 * Token lifetime in seconds.
	 */
	public const TOKEN_TTL = 600;

	/**
	 * The standard NewsMAN loader call: ( window, document, "script", "_nzm", "_nzm_config" ).
	 */
	public const PATTERN_TRACKER_LOADER = '[\'"]_nzm[\'"]\s*,\s*[\'"]_nzm_config[\'"]\s*\)';

	/**
	 * The page view event: _nzm.run( 'send', 'pageview' ).
	 */
	public const PATTERN_PAGE_VIEW = '_nzm\.run\(\s*[\'"]send[\'"]\s*,\s*[\'"]pageview[\'"]';

	/**
	 * The product detail event: _nzm.run( 'ec:setAction', 'detail' ).
	 * The cart script also calls ec:addProduct, so the detail action is the reliable sign.
	 */
	public const PATTERN_PRODUCT_DETAIL = '_nzm\.run\(\s*[\'"]ec:setAction[\'"]\s*,\s*[\'"]detail[\'"]';

	/**
	 * The product list event: _nzm.run( 'ec:addImpression', { ... } ).
	 */
	public const PATTERN_LISTING_IMPRESSION = '_nzm\.run\(\s*[\'"]ec:addImpression[\'"]';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'template_redirect', array( $this, 'disable_page_cache' ), 0 );
	}

	/**
	 * Create a token for the user. A new token replaces the previous one.
	 *
	 * @param int $user_id WP user ID.
	 * @return string
	 */
	public function create_token( $user_id ) {
		$token = wp_generate_password( 32, false );
		set_transient( self::TRANSIENT_PREFIX . (int) $user_id, hash( 'sha256', $token ), self::TOKEN_TTL );
		return $token;
	}

	/**
	 * Whether the current storefront request is a check by the administrator who created the token.
	 *
	 * @return bool
	 */
	public static function is_check_request() {
		if ( is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
		if ( empty( $_GET[ self::QUERY_PARAM ] ) || ! is_string( $_GET[ self::QUERY_PARAM ] ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		$stored = get_transient( self::TRANSIENT_PREFIX . $user_id );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) );
		return hash_equals( $stored, hash( 'sha256', $token ) );
	}

	/**
	 * Keep page caches from storing a check response.
	 *
	 * @return void
	 */
	public function disable_page_cache() {
		if ( ! self::is_check_request() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * AJAX: create a token and return the pages to check.
	 *
	 * @return void
	 */
	public function handle_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to run this check.', 'newsman' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$token = $this->create_token( get_current_user_id() );
		wp_send_json_success( array( 'pages' => $this->get_pages( $token ) ) );
	}

	/**
	 * Pages to check, each with the lookups to run on its HTML.
	 *
	 * @param string $token Token added to the page URLs.
	 * @return array List of [ 'type', 'label', 'url', 'link', 'checks' ] or [ 'type', 'label', 'skipped' ].
	 */
	public function get_pages( $token ) {
		$tracker = $this->get_tracker_lookup();
		$pages   = array(
			$this->build_page(
				'home',
				__( 'Homepage', 'newsman' ),
				home_url( '/' ),
				$token,
				array(
					$tracker,
					array(
						'key'     => 'pageview',
						'label'   => __( 'Page view event', 'newsman' ),
						'type'    => 'regex',
						'pattern' => self::PATTERN_PAGE_VIEW,
					),
				)
			),
		);

		if ( ( new WooCommerceExist() )->exist() ) {
			$product_url = $this->get_product_url();
			$pages[]     = ( '' === $product_url )
				? $this->skipped_page( 'product', __( 'Product page', 'newsman' ), __( 'There is no published product to check.', 'newsman' ) )
				: $this->build_page(
					'product',
					__( 'Product page', 'newsman' ),
					$product_url,
					$token,
					array(
						$tracker,
						array(
							'key'     => 'product_detail',
							'label'   => __( 'Product view event', 'newsman' ),
							'type'    => 'regex',
							'pattern' => self::PATTERN_PRODUCT_DETAIL,
						),
					)
				);

			$category_url = $this->get_category_url();
			$pages[]      = ( '' === $category_url )
				? $this->skipped_page( 'category', __( 'Category page', 'newsman' ), __( 'There is no product category with products to check.', 'newsman' ) )
				: $this->build_page(
					'category',
					__( 'Category page', 'newsman' ),
					$category_url,
					$token,
					array(
						$tracker,
						array(
							'key'     => 'listing_impression',
							'label'   => __( 'Product list event', 'newsman' ),
							'type'    => 'regex',
							'pattern' => self::PATTERN_LISTING_IMPRESSION,
						),
					)
				);
		}

		return apply_filters( 'newsman_remarketing_storefront_check_pages', $pages, $token );
	}

	/**
	 * The tracking script lookup. The saved NewsMAN script carries the site ID
	 * (data-site-id); the page must print that ID. The Remarketing ID setting is
	 * not used for this: it only turns remarketing on, while the script comes from
	 * NewsMAN for the selected list. Without a site ID in the saved script the
	 * standard loader call is searched instead.
	 *
	 * @return array
	 */
	protected function get_tracker_lookup() {
		$lookup  = array(
			'key'   => 'tracker',
			'label' => __( 'NewsMAN tracking script', 'newsman' ),
		);
		$site_id = $this->get_tracking_site_id();
		if ( '' !== $site_id ) {
			$lookup['type']    = 'text';
			$lookup['pattern'] = $site_id;
		} else {
			$lookup['type']    = 'regex';
			$lookup['pattern'] = self::PATTERN_TRACKER_LOADER;
		}
		return $lookup;
	}

	/**
	 * Site ID in the saved NewsMAN tracking script, or an empty string.
	 *
	 * @return string
	 */
	public function get_tracking_site_id() {
		$script = (string) Config::init()->get_script_js();
		if ( preg_match( '/data-site-id[\'"]?\s*,\s*[\'"]([^\'"]+)[\'"]/', $script, $matches ) ) {
			return $matches[1];
		}
		if ( preg_match( '/data-site-id\s*=\s*[\'"]([^\'"]+)[\'"]/', $script, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Build a page entry.
	 *
	 * @param string $type   Page type.
	 * @param string $label  Page label.
	 * @param string $url    Page URL.
	 * @param string $token  Token.
	 * @param array  $checks Lookups.
	 * @return array
	 */
	protected function build_page( $type, $label, $url, $token, $checks ) {
		return array(
			'type'   => $type,
			'label'  => $label,
			'url'    => add_query_arg( self::QUERY_PARAM, rawurlencode( $token ), $url ),
			'link'   => $url,
			'checks' => $checks,
		);
	}

	/**
	 * Build a skipped page entry.
	 *
	 * @param string $type   Page type.
	 * @param string $label  Page label.
	 * @param string $reason Why the page is skipped.
	 * @return array
	 */
	protected function skipped_page( $type, $label, $reason ) {
		return array(
			'type'    => $type,
			'label'   => $label,
			'skipped' => $reason,
		);
	}

	/**
	 * URL of the newest published product shown in the catalog.
	 *
	 * @return string
	 */
	protected function get_product_url() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}
		$ids = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'ids',
			)
		);
		if ( empty( $ids ) ) {
			return '';
		}
		$url = get_permalink( (int) $ids[0] );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * URL of the product category with the most products.
	 *
	 * @return string
	 */
	protected function get_category_url() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 1,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		$url = get_term_link( $terms[0] );
		return is_string( $url ) ? $url : '';
	}
}
