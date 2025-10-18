<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman remarketing class.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Remarketing\Script;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing script tag attributes
 *
 * @class \Newsman\Remarketing\Script\GetAttributes
 */
class GetAttributes {
	/**
	 * Additional script tag attributes.
	 * Can be used by scripts that block cookie, GDPR-compliant scripts.
	 * Example: 'type="text/plain" data-cookie-blocker="lorem-ipsum"'
	 *
	 * @return string
	 */
	public function get() {
		$params = '';
		$params = apply_filters( 'newsman_remarketing_script_track_script_tag_attributes', $params );
		if ( ! empty( $params ) ) {
			$params = ' ' . ltrim( $params, ' ' );
		}
		return $params;
	}
}
