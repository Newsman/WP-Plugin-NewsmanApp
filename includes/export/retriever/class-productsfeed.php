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
 * Class Export Retriever Products Feed
 *
 * @class \Newsman\Export\Retriever\ProductsFeed
 */
class ProductsFeed extends AbstractRetriever implements RetrieverInterface {
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
	 * All-categories cache per blog ID: [blog_id => [term_id => ['name' => ..., 'parent' => ...]]]
	 *
	 * @var array
	 */
	protected $categories_cache = array();

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

		if ( isset( $data['product_id'] ) && ! is_array( $data['product_id'] ) ) {
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
			$this->load_categories( $blog_id );
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

		$data['default_page_size'] = self::DEFAULT_PAGE_SIZE;

		$processed_params = $this->process_list_parameters( $data, $blog_id );

		if ( $this->is_different_blog( $blog_id ) ) {
			switch_to_blog( $blog_id );
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: Batch start, 2: Batch end, 3: WP blog ID */
				esc_html__( 'Export products %1$d, %2$d, blog ID %3$s', 'newsman' ),
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		$args = array(
			'limit'  => $processed_params['limit'],
			'offset' => $processed_params['start'],
			'status' => 'publish',
		);

		if ( isset( $processed_params['sort'] ) ) {
			$args['orderby'] = $processed_params['sort'];
			$args['order']   = $processed_params['order'];
		}

		foreach ( $processed_params['filters'] as $filter ) {
			$field    = $filter['field'];
			$operator = $this->get_expressions_definition()[ $filter['operator'] ];
			$value    = $filter['value'];

			if ( 'date_created' === $field ) {
				$args['date_created'] = $operator . $value;
			} elseif ( 'date_modified' === $field ) {
				$args['date_modified'] = $operator . $value;
			} elseif ( 'id' === $field ) {
				if ( 'in' === $filter['operator'] ) {
					$args['include']  = (array) $value;
					$args['post__in'] = (array) $value;
				} elseif ( 'nin' === $filter['operator'] ) {
					$args['exclude']      = (array) $value;
					$args['post__not_in'] = (array) $value;
				} else {
					$args['include']  = array( $value );
					$args['post__in'] = array( $value );
				}
			} else {
				if ( ! isset( $args['meta_query'] ) ) {
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					$args['meta_query'] = array();
				}
				$args['meta_query'][] = array(
					'key'     => $field,
					'value'   => $value,
					'compare' => $operator,
				);
			}
		}

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

		$this->load_categories( $blog_id );

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
				$processed_params['start'],
				$processed_params['limit'],
				$blog_id
			)
		);

		if ( $this->is_different_blog( $blog_id ) ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Load all product_cat terms for a blog into a flat cache.
	 *
	 * Returns [term_id => ['name' => string, 'parent' => int]]
	 *
	 * @param null|int $blog_id WP blog ID.
	 * @return array
	 */
	public function load_categories( $blog_id = null ) {
		$cache_key = (int) $blog_id;
		if ( isset( $this->categories_cache[ $cache_key ] ) ) {
			return $this->categories_cache[ $cache_key ];
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 0,
			)
		);

		$map = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$map[ $term->term_id ] = array(
					'name'   => $term->name,
					'parent' => (int) $term->parent,
				);
			}
		}

		$this->categories_cache[ $cache_key ] = $map;
		return $map;
	}

	/**
	 * Build a top-to-bottom breadcrumb path for a category.
	 *
	 * @param int      $category_id Term ID.
	 * @param null|int $blog_id WP blog ID.
	 * @return array List of category names from top-most ancestor to leaf.
	 */
	public function get_category_path( $category_id, $blog_id = null ) {
		$map       = $this->load_categories( $blog_id );
		$path      = array();
		$current   = (int) $category_id;
		$fail_safe = 0;

		while ( isset( $map[ $current ] ) && $fail_safe < 30 ) {
			array_unshift( $path, $map[ $current ]['name'] );
			$parent = $map[ $current ]['parent'];
			if ( 0 === $parent || $parent === $current ) {
				break;
			}
			$current = $parent;
			++$fail_safe;
		}

		return $path;
	}

	/**
	 * Get allowed request parameters
	 *
	 * @return array
	 */
	public function get_where_parameters_mapping() {
		return array(
			'created_at'  => array(
				'field' => 'date_created',
				'type'  => 'string',
			),
			'modified_at' => array(
				'field' => 'date_modified',
				'type'  => 'string',
			),
			'product_id'  => array(
				'field' => 'id',
				'type'  => 'int',
			),
			'product_ids' => array(
				'field'    => 'id',
				'multiple' => true,
				'type'     => 'int',
			),
		);
	}

	/**
	 * Get allowed sort fields
	 *
	 * @return array
	 */
	public function get_allowed_sort_fields() {
		return array(
			'product_id'  => 'id',
			'created_at'  => 'date_created',
			'modified_at' => 'date_modified',
			'name'        => 'name',
		);
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

		$category_ids  = $product->get_category_ids();
		$category      = '';
		$subcategories = '';

		$category_paths = array();
		foreach ( (array) $category_ids as $category_id ) {
			$path = $this->get_category_path( (int) $category_id, $blog_id );
			if ( ! empty( $path ) ) {
				$category_paths[] = $path;
			}
		}

		if ( ! empty( $category_paths ) ) {
			// Use the deepest path (most ancestors) as the primary category.
			usort(
				$category_paths,
				function ( $a, $b ) {
					return count( $b ) - count( $a );
				}
			);
			$category      = end( $category_paths[0] );
			$subcategories = $category_paths;
		}

		$quantity = (float) $product->get_stock_quantity();
		if ( empty( $quantity ) ) {
			$quantity = null;
		}

		$row = array(
			'id'             => (string) $product->get_id(),
			'url'            => $url,
			'name'           => $product->get_name(),
			'image_url'      => $image_url,
			'category'       => $category,
			'subcategories'  => $subcategories,
			'in_stock'       => $quantity > 0 ? '1' : '0',
			'stock_quantity' => $quantity,
			'sku'            => $product->get_sku(),
		);

		if ( empty( $product->get_sale_price() ) ) {
			$row['price'] = $product->get_price();
		} else {
			$row['price_full']     = $product->get_regular_price();
			$row['price_discount'] = $product->get_sale_price();
		}

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
