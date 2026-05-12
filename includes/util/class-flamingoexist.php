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
 * Flamingo exists.
 *
 * Detects whether the Flamingo plugin is loaded. Flamingo persists Contact Form 7
 * submissions in the `flamingo_inbound` CPT — without it CF7 submissions cannot be
 * retrieved post-hoc for the subscriber.list export.
 *
 * @class \Newsman\Util\FlamingoExist
 */
class FlamingoExist {
	/**
	 * Whether Flamingo is present.
	 *
	 * @return bool
	 */
	public function exist() {
		$exists = class_exists( '\Flamingo_Inbound_Message' );
		if ( function_exists( 'apply_filters' ) ) {
			$exists = apply_filters( 'newsman_flamingo_exist', $exists );
		}
		return $exists;
	}
}
