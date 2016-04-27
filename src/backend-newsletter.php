<?php

$list = get_option('newsman_list');
// Check if credentials are valid
try
{
	$available_lists = $this->client->list->all();
} catch (Exception $e)
{
	$this->valid_credential = false;
	$this->setMessageBackend('error', 'Invalid Credentials');
}
?>
<div class="wrap">
	<h2>Newsman Newsletter Setup</h2>

	<div id="nws-message" class="<?php echo $this->message['status'] ?>"><p>
			<strong><?php _e($this->message['message']); ?></strong></p></div>

	<?php if (!$this->valid_credentials)
	{ ?>
		<div class="error"><p><strong><?php _e('Invalid credentials!'); ?></strong></p></div>
	<?php } ?>
	<?php
	$posts = get_posts(array('posts_per_page' => 100, 'orderby' => 'post_date', 'order' => 'DESC', 'post_type' => 'post', 'post_status' => 'publish'));
	?>
	<?php if (isset($list))
	{ ?>
		<table class="form-table">
			<tbody>

			<tr>
				<th scope="row">
					<label for="newsman_newsletter_title">Subject</label>
				</th>
				<td>
					<input type="hidden" value="<?php echo $list; ?>" name="newsman_list"/>
					<input type="text" name="newsman_newsletter_title" style="max-width:531px"/>
					<div class="input-error" style="display: none;">!</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="use_email_template">Template</label>
				</th>
				<td>
					<select id="use_email_template">
						<option value="0" selected> select one</option>
						<?php foreach ($this->getTemplates() as $template)
						{ ?>
							<option value="<?php echo $template['filename'] ?>"><?php echo $template['name'] ?></option>
						<?php } ?>
					</select>
					<div class="input-error" style="display: none;">!</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="">Posts</label>
					<p class="description">To change the order in which the posts are placed in the newsletter. Click on
						a post and drag it in the desired position.</p>
				</th>
				<td>
					<div class="newsletter-posts">
						<ul class="selected">
							<li class="newsletter-posts-header">
								<span>Newsletter Posts</span>
								<span>Action</span>
							</li>

						</ul>
						<ul class="available">
							<li class="newsletter-posts-header">
								<span>Available Blog Posts</span>
								<span>Action</span>
							</li>
							<?php foreach ($posts as $post)
							{ ?>
								<li id="<?php echo $post->ID ?>" data-title="<?php echo $post->post_title ?>">
									<span><?php echo $post->post_title ?></span>
									<span><a href="#">select</a></span>
								</li>
							<?php } ?>
						</ul>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<input type="submit" value="Preview" class="button button-primary" id="newsman-preview-newsletter"
					       data-target="#myModal"/>
					<button type="button" data-dismiss="modal" class="button button-primary newsman-send-newsletter">
						Send Live
					</button>
				</th>
			</tr>
			</tbody>
		</table>
	<?php } ?>
</div>

<div class="modal fade" id="NewsmanModal" tabindex="-1" role="dialog" aria-labelledby="Newsman" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
						aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><?php echo 'Newsletter Subject' ?></h4>
				<button type="button" data-dismiss="modal" class="button button-primary newsman-send-newsletter">Send
					Live
				</button>
			</div>
			<div class="modal-body">
				...
			</div>
		</div>
	</div>
</div>