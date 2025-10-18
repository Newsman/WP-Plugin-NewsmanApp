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
$condition_tunnel_script = 'false';
$resources_base_url      = '';
$tracking_base_url       = '';
if ( $this->remarketing_config->use_proxy() ) {
	$condition_tunnel_script = 'true';
	$resources_base_url = esc_js ( esc_html( $this->get_resources_url() ) );
	$tracking_base_url = esc_js ( esc_html( $this->get_tracking_url() ) );
}

$nzm_config_js = $this->get_config_js();

$scriptString = strtr(
	$this->remarketing_config->get_script_js(),
	array(
		'{{nzmConfigJs}}' => $nzm_config_js,
		'{{conditionTunnelScript}}' => $condition_tunnel_script,
		'{{resourcesBaseUrl}}' => $resources_base_url,
		'{{trackingBaseUrl}}' => $tracking_base_url,
		'{{remarketingId}}' => esc_js ( esc_html( $this->remarketing_config->get_id() ) ),
		'{{trackingScriptUrl}}' => esc_js ( esc_html( $this->get_script_final_url() ) ),
	)
);
?>
<script<?php esc_js( esc_html( $this->get_script_tag_additional_attributes() ) ); ?>>
<?php echo $scriptString; ?>
<?php
$anonymize_ip_script = '';
if ( $this->remarketing_config->is_anonymize_ip()) {
	$anonymize_ip_script = $this->remarketing_config->get_js_track_run_func() . "('set', 'anonymizeIp', true);";
}

$this->display_no_track_script();
if ( ! empty( $anonymize_ip_script )) {
	echo $anonymize_ip_script;
}

if ( $this->is_woo_commerce_exist() ) {
	$currency_code = $this->get_currency_code();
	$currency_code = esc_js( esc_html( $currency_code ) );
	echo $this->remarketing_config->get_js_track_run_func() . "( 'set', 'currencyCode', '" . $currency_code . "' );";
}
?>
</script>
