<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman subscribe form block.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * @package NewsmanApp for WordPress
 */

namespace Newsman\Form\Block;

use Newsman\Config;
use Newsman\Subscribe\Lists;
use Newsman\Subscribe\Segments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the newsman/subscribe-form block and renders it on the front end.
 * The form posts to SubscribeProcessor; the list, segment and field settings
 * travel with the form signed by SignedSettings.
 *
 * @class \Newsman\Form\Block\SubscribeForm
 */
class SubscribeForm {
	/**
	 * Block name.
	 */
	public const BLOCK_NAME = 'newsman/subscribe-form';

	/**
	 * Field is not shown.
	 */
	public const FIELD_HIDDEN = 0;

	/**
	 * Field is shown and optional.
	 */
	public const FIELD_OPTIONAL = 1;

	/**
	 * Field is shown and required.
	 */
	public const FIELD_REQUIRED = 2;

	/**
	 * Query parameter with the result code of a submit made without JavaScript.
	 */
	public const RESULT_QUERY_VAR = 'nzm_subscribe';

	/**
	 * Query parameter with the key of the form that was submitted without JavaScript.
	 */
	public const FORM_QUERY_VAR = 'nzm_form';

	/**
	 * Optional fields: field => block attribute suffix.
	 *
	 * @var array
	 */
	protected $optional_fields = array(
		'firstname' => 'Firstname',
		'lastname'  => 'Lastname',
		'phone'     => 'Phone',
	);

	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->config = Config::init();
	}

	/**
	 * Register the block and the editor data. Runs on init.
	 *
	 * @return void
	 */
	public function init_hooks() {
		$block_type = register_block_type(
			NEWSMAN_PLUGIN_DIR . 'src/blocks/subscribe-form',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);

		if ( ! $block_type ) {
			return;
		}

		foreach ( self::get_editor_script_handles( $block_type ) as $handle ) {
			wp_set_script_translations( $handle, 'newsman', NEWSMAN_PLUGIN_DIR . 'languages' );
		}

		// Block styles without a block.json version get the WordPress version; bust caches on plugin updates.
		$style_handles = ! empty( $block_type->style_handles ) ? (array) $block_type->style_handles : (array) $block_type->style;
		foreach ( $style_handles as $handle ) {
			$style = is_string( $handle ) ? wp_styles()->query( $handle ) : false;
			if ( $style ) {
				$style->ver = NEWSMAN_VERSION;
			}
		}

		add_action( 'enqueue_block_editor_assets', array( $this, 'add_editor_data' ) );
	}

	/**
	 * Editor script handles of a registered block type.
	 *
	 * @param \WP_Block_Type $block_type Block type.
	 * @return array
	 */
	public static function get_editor_script_handles( $block_type ) {
		// WordPress 6.1+ has the array, older versions a single handle.
		if ( ! empty( $block_type->editor_script_handles ) ) {
			return (array) $block_type->editor_script_handles;
		}
		if ( ! empty( $block_type->editor_script ) ) {
			return array( $block_type->editor_script );
		}
		return array();
	}

	/**
	 * Pass the lists, segments and default texts to the block editor script.
	 *
	 * @return void
	 */
	public function add_editor_data() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );
		if ( ! $block_type ) {
			return;
		}

		$blog_id    = get_current_blog_id();
		$configured = $this->config->is_enabled_with_api( $blog_id );
		$lists      = $configured ? Lists::get_for_select( $blog_id ) : array();
		$segments   = $configured ? Segments::get_by_list( $blog_id ) : array();
		$sync_list  = (string) $this->config->get_list_id( $blog_id );

		$list_options = array();
		foreach ( $lists as $id => $name ) {
			$list_options[] = array(
				'value' => (string) $id,
				'label' => (string) $name,
			);
		}

		$segment_options = array();
		foreach ( $segments as $list_id => $list_segments ) {
			foreach ( (array) $list_segments as $id => $name ) {
				$segment_options[ (string) $list_id ][] = array(
					'value' => (string) $id,
					'label' => (string) $name,
				);
			}
		}

		$data = array(
			'configured'   => $configured,
			'settingsUrl'  => admin_url( 'admin.php?page=Newsman' ),
			'syncListName' => isset( $lists[ $sync_list ] ) ? (string) $lists[ $sync_list ] : $sync_list,
			'doubleOptin'  => $this->config->is_newsletter_double_optin( $blog_id ),
			'globalFormId' => (string) $this->config->get_newsman_form_id( $blog_id ),
			'lists'        => $list_options,
			'segments'     => (object) $segment_options,
			'defaults'     => $this->get_default_texts( false ),
		);

		foreach ( self::get_editor_script_handles( $block_type ) as $handle ) {
			wp_add_inline_script( $handle, 'window.newsmanSubscribeFormBlock = ' . wp_json_encode( $data ) . ';', 'before' );
		}
	}

	/**
	 * Default texts, translated in the site language.
	 *
	 * @param bool $consent_html Whether the consent text may contain the privacy policy link.
	 * @return array Map of block attribute => text.
	 */
	public function get_default_texts( $consent_html = true ) {
		$privacy_url = $consent_html ? get_privacy_policy_url() : '';
		if ( '' !== $privacy_url ) {
			$consent = sprintf(
				/* translators: %s: Privacy policy page URL */
				__( 'I agree to receive the newsletter and I accept the <a href="%s">privacy policy</a>.', 'newsman' ),
				esc_url( $privacy_url )
			);
		} else {
			$consent = __( 'I agree to receive the newsletter.', 'newsman' );
		}

		$double_optin = $this->config->is_newsletter_double_optin();

		return array(
			'headingText'    => __( 'Subscribe to our newsletter', 'newsman' ),
			'emailLabel'     => __( 'Email', 'newsman' ),
			'firstnameLabel' => __( 'First name', 'newsman' ),
			'lastnameLabel'  => __( 'Last name', 'newsman' ),
			'phoneLabel'     => __( 'Phone', 'newsman' ),
			'consentText'    => $consent,
			'buttonText'     => __( 'Subscribe', 'newsman' ),
			'successMessage' => SubscribeProcessor::get_message( SubscribeProcessor::CODE_SUCCESS, $double_optin ),
		);
	}

	/**
	 * Build the signed settings for the submit handler from the block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	public function get_settings( $attributes ) {
		$fields = array();
		foreach ( $this->optional_fields as $field => $suffix ) {
			$fields[ $field ] = self::FIELD_HIDDEN;
			if ( ! empty( $attributes[ 'show' . $suffix ] ) ) {
				$fields[ $field ] = ! empty( $attributes[ 'require' . $suffix ] ) ? self::FIELD_REQUIRED : self::FIELD_OPTIONAL;
			}
		}

		$list_id    = isset( $attributes['listId'] ) ? trim( (string) $attributes['listId'] ) : '';
		$segment_id = isset( $attributes['segmentId'] ) ? trim( (string) $attributes['segmentId'] ) : '';

		return array(
			'list'    => $list_id,
			'segment' => '' !== $list_id ? $segment_id : '',
			'form_id' => isset( $attributes['confirmationFormId'] ) ? sanitize_text_field( (string) $attributes['confirmationFormId'] ) : '',
			'fields'  => $fields,
			'consent' => ! empty( $attributes['showConsent'] ) ? 1 : 0,
		);
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

		if ( ! $this->config->is_enabled_with_api() ) {
			return '';
		}

		$attributes = is_array( $attributes ) ? $attributes : array();
		$defaults   = $this->get_default_texts();
		$texts      = array();
		foreach ( $defaults as $key => $default ) {
			$value         = isset( $attributes[ $key ] ) ? trim( (string) $attributes[ $key ] ) : '';
			$texts[ $key ] = '' !== $value ? $value : $default;
		}
		$custom_success = isset( $attributes['successMessage'] ) ? trim( (string) $attributes['successMessage'] ) : '';

		$settings = $this->get_settings( $attributes );
		$signed   = SignedSettings::encode( $settings );
		$form_key = substr( $signed['signature'], 0, 12 );
		$id       = wp_unique_id( 'nzm-subscribe-' );

		$fields     = array(
			array(
				'name'         => 'nzm_email',
				'type'         => 'email',
				'label'        => $texts['emailLabel'],
				'required'     => true,
				'autocomplete' => 'email',
				'class'        => 'email',
			),
		);
		$field_meta = array(
			'firstname' => array( 'text', 'given-name' ),
			'lastname'  => array( 'text', 'family-name' ),
			'phone'     => array( 'tel', 'tel' ),
		);
		foreach ( $settings['fields'] as $field => $mode ) {
			if ( self::FIELD_HIDDEN === $mode ) {
				continue;
			}
			$fields[] = array(
				'name'         => 'nzm_' . $field,
				'type'         => $field_meta[ $field ][0],
				'label'        => $texts[ $field . 'Label' ],
				'required'     => self::FIELD_REQUIRED === $mode,
				'autocomplete' => $field_meta[ $field ][1],
				'class'        => $field,
			);
		}

		$view = array(
			'id'              => $id,
			'wrapper'         => get_block_wrapper_attributes( array( 'class' => 'nzm-subscribe-form' ) ),
			'heading'         => ! empty( $attributes['showHeading'] ) ? $texts['headingText'] : '',
			'fields'          => $fields,
			'consent'         => $settings['consent'] ? $texts['consentText'] : '',
			'button'          => $texts['buttonText'],
			'action_url'      => admin_url( 'admin-post.php' ),
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'action'          => SubscribeProcessor::ACTION,
			'payload'         => $signed['payload'],
			'signature'       => $signed['signature'],
			'return_url'      => $this->get_current_url(),
			'success_message' => $custom_success,
			'error_message'   => SubscribeProcessor::get_message( SubscribeProcessor::CODE_ERROR ),
			'result'          => $this->get_no_js_result( $form_key, $custom_success ),
		);

		ob_start();
		include NEWSMAN_PLUGIN_DIR . 'src/blocks/subscribe-form/template.php';
		return (string) ob_get_clean();
	}

	/**
	 * Allowed HTML in the consent text.
	 *
	 * @return array
	 */
	public static function get_consent_allowed_html() {
		return array(
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * Result of a submit made without JavaScript, shown after the redirect back to the page.
	 *
	 * @param string $form_key       Key of the rendered form.
	 * @param string $custom_success Success message set in the block.
	 * @return array|null `[ 'success' => bool, 'message' => string ]` or null.
	 */
	protected function get_no_js_result( $form_key, $custom_success ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only result display after the redirect.
		if ( ! isset( $_GET[ self::RESULT_QUERY_VAR ], $_GET[ self::FORM_QUERY_VAR ] ) ) {
			return null;
		}
		$code = sanitize_key( wp_unslash( $_GET[ self::RESULT_QUERY_VAR ] ) );
		$key  = sanitize_key( wp_unslash( $_GET[ self::FORM_QUERY_VAR ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! hash_equals( $form_key, $key ) ) {
			return null;
		}

		$success = SubscribeProcessor::CODE_SUCCESS === $code;
		$message = $success && '' !== $custom_success
			? $custom_success
			: SubscribeProcessor::get_message( $code, $this->config->is_newsletter_double_optin() );

		return array(
			'success' => $success,
			'message' => $message,
		);
	}

	/**
	 * URL of the page being rendered, used to return after a submit made without JavaScript.
	 *
	 * @return string
	 */
	protected function get_current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$home        = wp_parse_url( home_url( '/' ) );
		if ( '' === $request_uri || '/' !== $request_uri[0] || empty( $home['host'] ) ) {
			return home_url( '/' );
		}

		// REQUEST_URI already holds the subdirectory of the site, so only the origin comes from home_url().
		$origin = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . '://' . $home['host'];
		if ( ! empty( $home['port'] ) ) {
			$origin .= ':' . $home['port'];
		}
		return remove_query_arg( array( self::RESULT_QUERY_VAR, self::FORM_QUERY_VAR ), $origin . $request_uri );
	}
}
