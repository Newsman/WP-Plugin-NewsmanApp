<?php

$compliant1 = get_option('newsman_widget_compliant1');
$compliant2 = get_option('newsman_widget_compliant2');

$compliant1url = get_option('newsman_widget_compliant1_url');
$compliant2url = get_option('newsman_widget_compliant2_url');

$name = get_option('newsman_name');
$prename = get_option('newsman_prename');

?>
<div id="newsman_subscribtion_message"></div>
<form method="post" class="newsman-subscription-form">
	<input type="hidden" name="newsman_subscription_submited" value="Y"/>
	<dl>
	<?php if (!empty($name))
		{ ?>
		<dt>
			<label for="newsman_subscription_name">Nume</label>
		</dt>
		<dd>
			<input type="text" name="newsman_subscription_name"/>
		</dd>
		<dt>
			<label for="newsman_subscription_prename">Prenume</label>
		</dt>
		<dd>
			<input type="text" name="newsman_subscription_prename"/>
		</dd>
		<?php } ?>
		<dt>
			<label for="newsman_subscription_email">Email</label>
		</dt>
		<dd>
			<input type="text" name="newsman_subscription_email"/>
		</dd>
		<?php if (!empty($compliant1))
		{ ?>
			<dd style="font-size: 10px;">
				<label for="compliant1"><a href="<?php echo $compliant1url; ?>"><?php echo $compliant1; ?></a></label>
				<input name="compliant1" type="checkbox" id="compliant1"/>
			</dd>
		<?php } ?>
		<?php if (!empty($compliant2))
		{ ?>
			<dd style="font-size: 10px;">
				<label for="compliant2"><a href="<?php echo $compliant2url; ?>"><?php echo $compliant2; ?></a></label>
				<input name="compliant2" type="checkbox" id="compliant2"/>
			</dd>
		<?php } ?>

		<dd>
			<input type="button" id="newsman_widget" value="Subscribe"/>
		</dd>
	</dl>
</form>
<style type="text/css">
	.newsman-subscription-form label {
		display: inline-block;
		padding: 1px 2px;
		font-size: 13px;
	}
</style>