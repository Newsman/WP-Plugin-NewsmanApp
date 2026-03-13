<?php
/**
 * Title: Newsman remarketing tracking script
 *
 * @package NewsmanApp for WordPress
 */

/**
 * Current class for output
 *
 * @var \Newsman\Remarketing\Script\Track $this
 */

$script_js = $this->remarketing_config->get_script_js();
// The script tag is not present in the script, something went wrong.
if ( stripos( $script_js, '<script' ) === false ) {
	return '';
}

$nzm_config_js = '';
if ( $this->is_woo_commerce_exist() ) {
	$nzm_config_js .= "_nzm_config['disable_datalayer'] = 1;";
}
$nzm_config_js .= $this->get_config_js();
?>
<?php
if ( ! empty( $nzm_config_js ) ) :
	?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
	var _nzm_config = _nzm_config || [];
	<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $nzm_config_js;
	?>
</script>
	<?php
endif;
?>
<?php
$script_js = str_replace( '<script', '<script ' . esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ) . ' ', $script_js );
$script_js = apply_filters( 'newsman_remarketing_render_track_script_js', $script_js );
// The script tag is not present in the script, something went wrong.
if ( stripos( $script_js, '<script' ) === false ) {
	return '';
}

$run = $this->remarketing_config->get_js_track_run_func();
?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $script_js;
?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
<?php
$anonymize_ip_script = '';
if ( $this->remarketing_config->is_anonymize_ip() ) {
	$anonymize_ip_script = esc_js( esc_html( $run ) ) . "('set', 'anonymizeIp', true);";
}

$this->display_no_track_script();
if ( ! empty( $anonymize_ip_script ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $anonymize_ip_script;
}

if ( $this->is_woo_commerce_exist() ) {
	$currency_code = $this->get_currency_code();
	$currency_code = esc_js( esc_html( $currency_code ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo esc_js( esc_html( $run ) ) . "( 'set', 'currencyCode', '" . $currency_code . "' );";
}
?>
</script>
