<?php
/**
 * Plugin URI: https://github.com/Newsman/WP-Plugin-NewsmanApp
 * Title: Newsman subscribe form block template.
 * Author: Newsman
 * Author URI: https://newsman.com
 * License: GPLv2 or later
 *
 * Included by \Newsman\Form\Block\SubscribeForm::render() with $view in scope.
 *
 * @package NewsmanApp for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
	<?php if ( '' !== $view['heading'] ) : ?>
		<h3 class="nzm-subscribe-form__heading"><?php echo esc_html( $view['heading'] ); ?></h3>
	<?php endif; ?>
	<form class="nzm-subscribe-form__form" method="post"
		action="<?php echo esc_url( $view['action_url'] ); ?>"
		data-ajax-url="<?php echo esc_url( $view['ajax_url'] ); ?>"
		data-success-message="<?php echo esc_attr( $view['success_message'] ); ?>"
		data-error-message="<?php echo esc_attr( $view['error_message'] ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $view['action'] ); ?>"/>
		<input type="hidden" name="nzm_settings" value="<?php echo esc_attr( $view['payload'] ); ?>"/>
		<input type="hidden" name="nzm_signature" value="<?php echo esc_attr( $view['signature'] ); ?>"/>
		<input type="hidden" name="nzm_return" value="<?php echo esc_url( $view['return_url'] ); ?>"/>
		<?php foreach ( $view['fields'] as $nzm_field ) : ?>
			<p class="nzm-subscribe-form__field nzm-subscribe-form__field--<?php echo esc_attr( $nzm_field['class'] ); ?>">
				<label for="<?php echo esc_attr( $view['id'] . '-' . $nzm_field['class'] ); ?>"><?php echo esc_html( $nzm_field['label'] ); ?><?php echo $nzm_field['required'] ? ' <span class="nzm-subscribe-form__required" aria-hidden="true">*</span>' : ''; ?></label>
				<input type="<?php echo esc_attr( $nzm_field['type'] ); ?>"
					id="<?php echo esc_attr( $view['id'] . '-' . $nzm_field['class'] ); ?>"
					name="<?php echo esc_attr( $nzm_field['name'] ); ?>"
					autocomplete="<?php echo esc_attr( $nzm_field['autocomplete'] ); ?>"
					<?php echo $nzm_field['required'] ? 'required' : ''; ?>/>
			</p>
		<?php endforeach; ?>
		<p class="nzm-subscribe-form__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $view['id'] . '-website' ); ?>">Website</label>
			<input type="text" id="<?php echo esc_attr( $view['id'] . '-website' ); ?>" name="nzm_website" value="" tabindex="-1" autocomplete="off"/>
		</p>
		<?php if ( '' !== $view['consent'] ) : ?>
			<p class="nzm-subscribe-form__field nzm-subscribe-form__field--consent">
				<label>
					<input type="checkbox" name="nzm_consent" value="1" required/>
					<span><?php echo wp_kses( $view['consent'], \Newsman\Form\Block\SubscribeForm::get_consent_allowed_html() ); ?></span>
				</label>
			</p>
		<?php endif; ?>
		<p class="nzm-subscribe-form__actions">
			<button type="submit" class="wp-block-button__link wp-element-button nzm-subscribe-form__button"><?php echo esc_html( $view['button'] ); ?></button>
		</p>
		<?php if ( null !== $view['result'] ) : ?>
			<div class="nzm-subscribe-form__message <?php echo $view['result']['success'] ? 'is-success' : 'is-error'; ?>" role="status" aria-live="polite"><?php echo esc_html( $view['result']['message'] ); ?></div>
		<?php else : ?>
			<div class="nzm-subscribe-form__message" role="status" aria-live="polite" hidden></div>
		<?php endif; ?>
	</form>
</div>
