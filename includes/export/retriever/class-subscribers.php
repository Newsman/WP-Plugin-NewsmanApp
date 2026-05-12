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

namespace Newsman\Export\Retriever;

use Newsman\Config;
use Newsman\Export\V1\ApiV1Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Subscribers
 *
 * Resolves the active subscriber export source for the current request and delegates
 * to the matching retriever. Priority is: Elementor Pro - CF7 (via Flamingo) -
 * WPForms (Pro entries) - WC - WP. Each step checks its precondition (option on,
 * plugins active, source form is still marked as Newsman + newsletter form); if
 * the precondition fails the chain falls through to the next source. If nothing
 * matches an `ApiV1Exception(3002)` is thrown for API v1 callers, and a legacy
 * exception otherwise.
 *
 * @class \Newsman\Export\Retriever\Subscribers
 */
class Subscribers extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Process subscribers retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 * @throws ApiV1Exception When no subscriber source is enabled in API v1 context.
	 * @throws \Exception When no subscriber source is enabled in legacy context.
	 */
	public function process( $data = array(), $blog_id = null ) {
		$config = Config::init();

		// 1. Elementor Pro form submissions.
		if ( $config->is_elementor_export_subscribers( $blog_id ) && SubscribersElementorPro::is_eligible( $blog_id ) ) {
			$retriever = new SubscribersElementorPro();
			return $retriever->process( $data, $blog_id );
		}

		// 2. Contact Form 7 submissions via Flamingo.
		if ( $config->is_contact_form_7_export_subscribers( $blog_id ) && SubscribersContactForm7::is_eligible( $blog_id ) ) {
			$retriever = new SubscribersContactForm7();
			return $retriever->process( $data, $blog_id );
		}

		// 3. WPForms (Pro) form entries.
		if ( $config->is_wpforms_export_subscribers( $blog_id ) && SubscribersWPForms::is_eligible( $blog_id ) ) {
			$retriever = new SubscribersWPForms();
			return $retriever->process( $data, $blog_id );
		}

		// 4. WooCommerce subscribers (existing).
		if ( $this->remarketing_config->is_export_woocommerce_subscribers( $blog_id ) ) {
			$retriever = new SubscribersWoocommerceFeed();
			return $retriever->process( $data, $blog_id );
		}

		// 4. WordPress users (existing).
		if ( $this->remarketing_config->is_export_wordpress_subscribers( $blog_id ) ) {
			$retriever = new SubscribersWordpressFeed();
			return $retriever->process( $data, $blog_id );
		}

		if ( isset( $data['_v1_filter_fields'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ApiV1Exception( 3002, 'Subscriber export not enabled', 500 );
		}

		throw new \Exception( 'No subscriber export enabled.' );
	}
}
