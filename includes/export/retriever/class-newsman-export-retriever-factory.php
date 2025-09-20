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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client Export Retriever Factory
 *
 * @class Newsman_Export_Retriever_Factory
 */
class Newsman_Export_Retriever_Factory {
	/**
	 * Create retriever instance
	 *
	 * @param string $class_name Class name of retriever.
	 * @param array  $data Data to pass in retriever constructor.
	 * @return Newsman_Export_Retriever_Interface
	 * @throws \InvalidArgumentException Invalid retriever class.
	 */
	public function create( $class_name, $data = array() ) {
		if ( ! class_exists( $class_name ) ) {
			throw new \InvalidArgumentException( esc_html( 'Type "' . $class_name . '" does not exist.' ) );
		}

		$instance = new $class_name( $data );

		if ( ! $instance instanceof Newsman_Export_Retriever_Interface ) {
			throw new \InvalidArgumentException(
				esc_html( 'Type "' . $class_name . '" is not instance on ' . Newsman_Export_Retriever_Interface::class )
			);
		}

		return $instance;
	}
}
