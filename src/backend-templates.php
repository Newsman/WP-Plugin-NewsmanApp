<div class="wrap">
	<h2>Newsman Templates</h2>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label>Templates list:</label>
			</th>
			<td>
				<select class='newsman-select-list' name='newsman_templates_list' size=5 autocomplete="off">
					<?php foreach ($this->getTemplates() as $template)
					{ ?>
						<option value="<?php echo $template['filename'] ?>"><?php echo $template['name'] ?></option>
					<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label>Template code:</label>
				<dl class="newsman-template-variables">
					<dt>$posts</dt>
					<dd>the array containing all posts</dd>

					<dt>$post->ID</dt>
					<dd>the id of the post</dd>

					<dt>$post->post_author</dt>
					<dd>the author of the post</dd>

					<dt>$post->post_date</dt>
					<dd>the post date</dd>

					<dt>$post->post_content</dt>
					<dd>the post content</dd>

					<dt>$post->post_excerpt</dt>
					<dd>the post excerpt</dd>

					<dt>$post->post_title</dt>
					<dd>the post title</dd>

					<dt>$post->image</dt>
					<dd>the post image</dd>

					<dt>$post->guid</dt>
					<dd>the post link</dd>
				</dl>
			</th>
			<td>
				<textarea name="newsman_template_edit" class="newsman-template-editor" cols="30" rows="10"
				          autocomplete="off"></textarea>
				<div class="save-status">&nbsp;</div>
			</td>
		</tr>
		<tr>
			<th scope="row">
			</th>
			<td>
				<button id="newsman-templates-editor-save" type="button" class="button button-primary">Save changes
				</button>
				<button type="button" id="newsman-templates-editor-preview" class="button button-primary">View</button>
			</td>
		</tr>
	</table>
</div>

<div class="modal fade" id="NewsmanModal" tabindex="-1" role="dialog" aria-labelledby="Newsman" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
						aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel">Newsletter Subject</h4>
			</div>
			<div class="modal-body">
				...
			</div>
		</div>
	</div>
</div>