<?php
/**
 * Title: Newsman my account subscribe to newsletter
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Form\Account\Processor $this
 */

$is_subscribed = $this->get_is_current_user_subscribed();
?>
<h2><?php echo esc_html__( 'Newsletter Subscription', 'newsman' ); ?></h2>

<form method="post" class="woocommerce-form newsletter-subscription-form">
	<?php wp_nonce_field( 'newsman_subscribe_newsletter', 'newsman_nonce' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide newsletter-subscription-form__row">
		<label for="newsman_newsletter_subscribe">
			<input type="checkbox" id="newsman_newsletter_subscribe" name="nzmAccountNewsletter" value="1" <?php echo ( $is_subscribed ? 'checked="checked"' : '' ); ?>>
			<?php echo esc_html__( 'Subscribe to our newsletter', 'newsman' ); ?>
			<input type="hidden" id="newsman_newsletter_previous_subscribe" name="nzmAccountNewsletterPrevious" value="<?php echo ( $is_subscribed ? '1"' : '0' ); ?>">
		</label>
	</p>

	<p>
		<button type="submit" class="woocommerce-Button button wp-element-button newsman-subscribe-button" name="newsman_newsletter_submit"><?php echo esc_html__( 'Subscribe', 'newsman' ); ?></button>
	</p>
</form>
