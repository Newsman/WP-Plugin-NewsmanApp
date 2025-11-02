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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Factory
 *
 * @class \Newsman\Export\Retriever\RetrieverFactory
 */
class RetrieverFactory {
	/**
	 * Create retriever instance
	 *
	 * @param string $class_name Class name of retriever.
	 * @param array  $data Data to pass in retriever constructor.
	 * @return RetrieverInterface
	 * @throws \InvalidArgumentException Invalid retriever class.
	 */
	public function create( $class_name, $data = array() ) {
		if ( ! class_exists( $class_name ) ) {
			throw new \InvalidArgumentException( esc_html( 'Type "' . $class_name . '" does not exist.' ) );
		}

		$instance = new $class_name( $data );

		if ( ! $instance instanceof RetrieverInterface ) {
			throw new \InvalidArgumentException(
				esc_html( 'Type "' . $class_name . '" is not instance on ' . RetrieverInterface::class )
			);
		}

		return $instance;
	}
}
