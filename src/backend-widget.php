<?php

$this->isOauth();

?>


<div class="tabsetImg">
	<a href="https://newsman.com" target="_blank">
		<img src="/wp-content/plugins/newsmanapp/src/img/logo.png" />
	</a>
</div>
<div class="tabset">

	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="newsmanBtn">Newsman</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="syncBtn">Sync</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="remarketingBtn">Remarketing</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="smsBtn">SMS</label>
	<input type="radio" name="tabset" id="" aria-controls="">
	<label for="" id="settingsBtn">Settings</label>
	<input type="radio" name="tabset" id="tabWidget" aria-controls="" checked>
	<label for="tabWidget" id="widgetBtn">Widget</label>
   
  <div class="tab-panels">
    <section id="tabWidget" class="tab-panel">

		<div class="wrap">
		<form method="post" enctype="multipart/form-data">
			<input type="hidden" name="newsman_submit" value="Y"/>
			<h2>Newsman Widget setup</h2>

			<div class="<?php echo (is_array($this->message) && array_key_exists("status", $this->message)) ? $this->message["status"] : ""; ?>"><p><strong><?php echo (is_array($this->message) && array_key_exists("message", $this->message)) ? $this->message["message"] : ""; ?></strong>
				</p></div>			

				<table class="form-table">
				<tr>
					<th scope="row"> Info</th>
					<td>
						<p class="description">
							Log in to your <a target="_blank" href="https://newsman.com">Newsman account</a>: Select List -> Settings -> Subscription forms -> Create/Edit form -> Modal window -> Activate modal window for newsletter subscription -> Select embedded form.
							Copy paste Shortcode `newsman_subscribe_widget` into wordpress pages/posts
						</p>
					</td>
				</tr>
				</table>

			</form>
		</div>

  	</section>  
  </div>  
</div>