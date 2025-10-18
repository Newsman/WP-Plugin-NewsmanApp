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

namespace Newsman\Remarketing\Action;

use Newsman\Remarketing\Config as RemarketingConfig;
use Newsman\Remarketing\Script\GetAttributes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remarketing action abstract
 *
 * @class \Newsman\Remarketing\Action\AbstractAction
 */
class AbstractAction {
	/**
	 * Remarketing config
	 *
	 * @var RemarketingConfig
	 */
	protected $remarketing_config;

	/**
	 * Action data
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->remarketing_config = RemarketingConfig::init();
	}

	/**
	 * Get action JS code with script tag
	 *
	 * @return string
	 */
	public function get_script_js() {
		if ( ! $this->remarketing_config->is_tracking_allowed() ) {
			return '';
		}

		$get_attributes = new GetAttributes();
		$attributes = $get_attributes->get();

		return '<script' . $attributes . '>' . $this->get_js() . '</script>';
	}

	/**
	 * Display JS with script tag
	 *
	 * @return void
	 */
	public function display_script_js() {
		echo $this->get_script_js();
	}

	/**
	 * Get action JS code
	 *
	 * @return string
	 */
	public function get_js() {
		return '';
	}

	/**
	 * Set action data
	 *
	 * @param array $data Action data.
	 * @return void
	 */
	public function set_data( $data )
	{
		$this->data = $data;
	}

	/**
	 * Get action data
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Returns a category JSON line based on product object
	 *
	 * @param \WC_Product $product Product to pull info for.
	 *
	 * @return string Line of JSON.
	 */
	public function get_product_category_line( $product ) {
		$data = array();
		$variation_data = array();
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$variation_data = $product->variation_data;
		} elseif ( $product->is_type( 'variation' ) ) {
			$variation_data = wc_get_product_variation_attributes( $product->get_id() );
		}

		$categories = get_the_terms( $product->get_id(), 'product_cat' );

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$parent_id = $product->parent->id;
			} else {
				$parent_id = $product->get_parent_id();
			}
			$parent_product = wc_get_product( $parent_id );
			$categories     = get_the_terms( $parent_product->get_id(), 'product_cat' );
		}

		if ( $categories ) {
			foreach ( $categories as $category ) {
				$data[] = $category->name;
			}
		}

		$js = "'" . esc_js( join( '/', $data ) ) . "',";
		return apply_filters(
			'newsman_remarketing_action_product_category_js',
			$js,
			array(
				'product' => $product
			)
		);
	}
}
