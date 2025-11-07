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

use Newsman\Logger;
use Newsman\Remarketing\Config as RemarketingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Export Retriever Products
 *
 * @class \Newsman\Export\Retriever\Products
 */
class Products extends AbstractRetriever implements RetrieverInterface {
	/**
	 * Default batch page size
	 */
	public const DEFAULT_PAGE_SIZE = 1000;

	/**
	 * Additional product attributes
	 *
	 * @var array
	 */
	protected $additional_attributes = array();

	/**
	 * Process products retriever
	 *
	 * @param array    $data Data to filter entities, to save entities, other.
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function process( $data = array(), $blog_id = null ) {
		$attributes = $this->remarketing_config->get_product_attributes();
		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attribute ) {
				if ( taxonomy_exists( $attribute ) ) {
					$this->additional_attributes[] = $attribute;
				}
			}
		}

		if ( isset( $data['product_id'] ) ) {
			if ( empty( $data['product_id'] ) ) {
				return array();
			}

			$this->logger->info(
				/* translators: 1: Product ID, 2: WordPress blog ID */
				sprintf( esc_html__( 'Export product %1$d, store ID %2$s', 'newsman' ), $data['product_id'], $blog_id )
			);

			if ( $this->is_different_blog( $blog_id ) ) {
				switch_to_blog( $blog_id );
			}
			$product = wc_get_product( $data['product_id'] );
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			if ( empty( $product ) ) {
				return array();
			}
			$result = array( $this->process_product( $product, $blog_id ) );

			$this->logger->info(
				/* translators: 1: Product ID, 2: WordPress blog ID */
				sprintf( esc_html__( 'Exported product %1$d, store ID %2$s', 'newsman' ), $data['product_id'], $blog_id )
			);

			return $result;
		}

		$start = ! empty( $data['start'] ) && $data['start'] > 0 ? $data['start'] : 0;
		$limit = empty( $data['limit'] ) ? self::DEFAULT_PAGE_SIZE : $data['limit'];

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Export products %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$start,
				$limit,
				$blog_id
			)
		);

		$args     = array(
			'limit'        => $limit,
			'offset'       => $start,
			'status'       => 'publish',
			'stock_status' => 'instock',
		);
		$args     = apply_filters(
			'newsman_export_retriever_products_process_fetch',
			$args,
			array(
				'data'    => $data,
				'blog_id' => $blog_id,
			)
		);
		$products = wc_get_products( $args );

		if ( empty( $products ) ) {
			if ( $this->is_different_blog( $blog_id ) ) {
				restore_current_blog();
			}
			return array();
		}

		$result = array();
		foreach ( $products as $product ) {
			try {
				$result[] = $this->process_product( $product, $blog_id );
			} catch ( \Exception $e ) {
				$this->logger->log_exception( $e );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Exported products %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$start,
				$limit,
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Process product
	 *
	 * @param \WC_Product $product Product instance.
	 * @param null|int    $blog_id WP blog ID.
	 * @return array
	 */
	public function process_product( $product, $blog_id = null ) {
		$image_id = $product->get_image_id();
		if ( ! empty( $image_id ) ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
		} else {
			$image_url = wc_placeholder_img_src( 'full' );
		}
		$url = get_permalink( $product->get_id() );

		$_price_old = 0;
		if ( empty( $product->get_sale_price() ) ) {
			$_price = $product->get_price();
		} else {
			$_price     = $product->get_sale_price();
			$_price_old = $product->get_regular_price();
		}

		$category_ids = $product->get_category_ids();
		$category     = '';

		foreach ( (array) $category_ids as $category_id ) {
			$category_term = get_term_by( 'id', (int) $category_id, 'product_cat' );
			if ( ! empty( $category_term ) ) {
				$category = $category_term->name;
				break;
			}
		}

		$quantity = (float) $product->get_stock_quantity();
		if ( empty( $quantity ) ) {
			$quantity = null;
		}

		$row = array(
			'id'             => (string) $product->get_id(),
			'category'       => $category,
			'name'           => $product->get_name(),
			'stock_quantity' => $quantity,
			'price'          => (float) $_price,
			'price_old'      => (float) $_price_old,
			'image_url'      => $image_url,
			'url'            => $url,
			'sku'            => $product->get_sku(),
		);

		foreach ( $this->additional_attributes as $attribute_name ) {
			$attribute_value = $product->get_attribute( $attribute_name );
			if ( ! empty( $attribute_value ) ) {
				$row[ $attribute_name ] = $attribute_value;
			} else {
				$row[ $attribute_name ] = '';
			}
		}

		return apply_filters(
			'newsman_export_retriever_products_process_product',
			$row,
			array(
				'product' => $product,
				'blog_id' => $blog_id,
			)
		);
	}
}
