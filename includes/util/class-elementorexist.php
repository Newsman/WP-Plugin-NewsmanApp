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

namespace Newsman\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor exists
 *
 * The Form widget ships only in Elementor Pro, so we require both core Elementor
 * and Elementor Pro to be loaded for the integration to be wired up.
 *
 * @class \Newsman\Util\ElementorExist
 */
class ElementorExist {
	/**
	 * Whether Elementor (core) and Elementor Pro are both present.
	 *
	 * @return bool
	 */
	public function exist() {
		$core   = did_action( 'elementor/loaded' ) > 0;
		$pro    = defined( 'ELEMENTOR_PRO_VERSION' );
		$exists = $core && $pro;
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_elementor_exist', $exists );
		}
		return $exists;
	}

	/**
	 * Whether only core Elementor is present (no Pro).
	 *
	 * @return bool
	 */
	public function core_exist() {
		return did_action( 'elementor/loaded' ) > 0;
	}

	/**
	 * Check if the current Elementor Pro version is less than a given version.
	 *
	 * @param string $version Version to compare.
	 * @return bool
	 */
	public function is_pro_before( $version ) {
		$pro_version = defined( 'ELEMENTOR_PRO_VERSION' ) ? constant( 'ELEMENTOR_PRO_VERSION' ) : '0.0.0';
		return version_compare( $pro_version, $version, '<' );
	}
}
