<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman embedded form block.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Form\Block;

use Newsman\Form\Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the newsman/embedded-form block: a form built in the Newsman app,
 * rendered exactly like the [newsman_subscribe_widget formid="..."] shortcode.
 *
 * @class \Newsman\Form\Block\EmbeddedForm
 */
class EmbeddedForm {
	/**
	 * Block name.
	 */
	public const BLOCK_NAME = 'newsman/embedded-form';

	/**
	 * Register the block. Runs on init.
	 *
	 * @return void
	 */
	public function init_hooks() {
		$block_type = register_block_type(
			NEWSMAN_PLUGIN_DIR . 'src/blocks/embedded-form',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);

		if ( ! $block_type ) {
			return;
		}

		foreach ( SubscribeForm::get_editor_script_handles( $block_type ) as $handle ) {
			wp_set_script_translations( $handle, 'newsman', NEWSMAN_PLUGIN_DIR . 'languages' );
		}
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (empty, the block is dynamic).
	 * @return string
	 */
	public function render( $attributes, $content = '' ) {
		unset( $content );

		$form_id = isset( $attributes['formId'] ) ? trim( (string) $attributes['formId'] ) : '';
		if ( '' === $form_id ) {
			return '';
		}

		$html = Widget::init()->generate( array( 'formid' => $form_id ) );
		if ( '' === $html ) {
			return '';
		}

		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}
}
