<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<title>Newsletter</title>
<style type="text/css">
            	table {border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;}
      			  @media only screen and (max-width: 600px) {
			        	body { width: 100% !important; max-width: 100% !important; }
			        	body table { width: 100% !important; max-width: 100% !important; clear: both !important; float: none !important; }
			        	td.imgF img { width: 100% !important; max-width: 100% !important; clear: both !important; float: none !important; }
			        	td img.productImgF { width: 100% !important; max-width: 300px  !important; clear: both !important; float: none !important;}
			        	td img.productImg { width: 100% !important; max-width: 300px !important; clear: both !important; float: none !important; padding-top: 35px; }
			        	.tableSocial { max-width: 300px !important; text-align: center !important; }
			        }
</style>
</head>
<body style="margin:0; -webkit-text-size-adjust:none; background-color: #f1f1f1;">
<table align="center" border="0" cellpadding="0" cellspacing="0" style="color:#818080; font: normal 12px Arial, sans-serif; width: 600px;" width="600">
<tr>
	<td>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #ffffff; padding:0" width="600">
		<tr>
			<td valign="top" align="left" style="padding: 0px;">
				<table border="0" align="left" cellpadding="0" cellspacing="0" style="background-color: #ffffff; padding:0" width="80">
				<tr>
					<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 24px; line-height: 28px; font-weight: bold; color: #fdfeff; padding: 15px;">
						<a href="http://www.newsman.ro/"><img src="<?php echo $template_dir . '/first/46074.jpg';?>" style="display: block;" border="0"/></a>
					</td>
				</tr>
				</table>
				<table border="0" align="center" cellpadding="0" cellspacing="0" style="background-color: #ffffff; padding:0" width="230">
				<tr>
					<td valign="middle" align="center" style="padding: 0px; padding-top: 10px;">
						<table class="tableSocial" border="0" align="left" cellpadding="0" cellspacing="0" style="background-color: #ffffff; padding:0" width="25">
						<tr>
							<td valign="top" align="left" style="padding: 15px 5px;">
								<a href="##NEWSMAN:facebook_likebutton##"><img src="<?php echo $template_dir . '/first/46075.jpg';?>" style="display: block;" border="0"/></a>
							</td>
							<td valign="top" align="left" style="padding: 15px 5px;">
								<a href="##NEWSMAN:twitter_sharebutton##"><img src="<?php echo $template_dir . '/first/46076.jpg';?>" style="display: block;" border="0"/></a>
							</td>
							<td valign="top" align="left" style="padding: 15px 5px;">
								<a href="##NEWSMAN:pinterest_sharebutton##"><img src="<?php echo $template_dir . '/first/46077.jpg';?>" style="display: block;" border="0"/></a>
							</td>
							<td valign="top" align="left" style="padding: 15px 5px;">
								<a href="##NEWSMAN:plusone_sharebutton##"><img src="<?php echo $template_dir . '/first/46078.jpg';?>" style="display: block;" border="0"/></a>
							</td>
							<td valign="top" align="left" style="padding: 15px 5px;">
								<img src="<?php echo $template_dir . '/first/46079.jpg';?>" style="display: block;" border="0"/>
							</td>
						</tr>
						</table>
					</td>
				</tr>
				</table>
			</td>
		</tr>
		</table>
		<?php
		if( isset($posts) ) {
			$post = $posts[0];
	?>
		<?php if(isset( $post->
		 image )){?>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td class="imgF" valign="top" align="center" style="padding: 0px;">
				<img src="<?php echo $post->image;?>" style="display: block;max-width:600px" border="0" />
			</td>
		</tr>
		</table>
		<?php }?>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding: 0px; border-bottom: 1px solid #eaeaea;" width="100%">
		<tr>
			<td valign="top" align="left" style="padding: 0px;">
				<table valign="top" border="0" align="left" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="450">
				<tr>
					<td valign="top" align="center" style="text-align: left; font-family: Arial; font-size: 23px; line-height: 26px; font-weight: bold; color: #5e5e63; padding-left: 15px; padding-right: 15px;">
						<?php echo $post->
						 post_title;?>
					</td>
				</tr>
				<tr>
					<td style="height: 5px; line-height: 5px;" height="5">
						 &nbsp;
					</td>
				</tr>
				<tr>
					<td valign="top" align="center" style="text-align: left; font-family: Arial; font-size: 15px; line-height: 20px; font-weight: normal; color: #bfc0c3; padding-left: 15px; padding-right: 15px;">
						<?php echo substr($post->
						 post_content, 0 ,150)."...";?>
					</td>
				</tr>
				</table>
				<table border="0" align="center" cellpadding="0" valign="middle" cellspacing="0" height="100" style="height: 100px; background-color: #fff; padding:0;" width="115">
				<tr>
					<td class="tdSocialImg" valign="middle" align="center" height="40" style="padding: 0px; height: 40px;">
						<a href="<?php echo $post->guid?>"><img class="social_img" src="<?php echo $template_dir . '/first/46081.jpg';?>" border="0"/></a>
					</td>
				</tr>
				</table>
			</td>
		</tr>
		</table>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<?php array_shift($posts);?>
		<table border="0" cellpadding="0" cellspacing="0" align="center" style="background-color: #fff; border-bottom: 1px solid #eaeaea; padding: 0px;" width="600">
		<tr>
			<td valign="top" align="center" style="padding: 0px; padding-left: 10px; padding-right: 10px;">
				<?php
		foreach( $posts as $key =>
				 $post ){ if( $key == 2 ){ break; } ?>
				<table align="left" border="0" cellpadding="0" cellspacing="0" width="290" style="background-color: #fff; padding: 0px;">
				<?php if(isset( $post->
				 image )){?>
				<tr>
					<td class="tdSocialImg" valign="top" align="left" style="padding: 0px; padding-left: 15px; padding-right: 5px;">
						<img class="productImgF" src="<?php echo $post->image;?>" border="0" style="max-width:255px"/>
					</td>
				</tr>
				<?php }?>
				<tr>
					<td style="height: 5px; line-height: 5px;" height="5">
						 &nbsp;
					</td>
				</tr>
				<tr>
					<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 16px; line-height: 20px; font-weight: bold; color: #5e5e63; padding: 0px; padding-left: 15px; padding-right: 5px;">
						<?php echo $post->
						 post_title;?>
					</td>
				</tr>
				<tr>
					<td style="height: 5px; line-height: 5px;" height="5">
						 &nbsp;
					</td>
				</tr>
				<tr>
					<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 14px; line-height: 18px; font-weight: normal; color: #bfc0c3; padding: 0px; padding-left: 15px; padding-right: 5px;">
						<?php echo substr($post->
						 post_content, 0 ,150)."...";?>
					</td>
				</tr>
				<tr>
					<td style="height: 15px; line-height: 15px;" height="15">
						 &nbsp;
					</td>
				</tr>
				<tr>
					<td class="tdSocialImg" valign="middle" align="left" style="padding: 0px; padding-left: 15px;">
						<a href="<?php echo $post->guid;?>"><img class="social_img" src="<?php echo $template_dir . '/first/46081.jpg';?>" border="0"/></a>
					</td>
				</tr>
				</table>
				<?php
		array_shift($posts);
	}
	?>
			</td>
		</tr>
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<?php
		foreach( $posts as $key =>
		 $post ){ if( $key == 1 ){ break; } ?>
		<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fff; border-bottom: 1px solid #eaeaea; padding: 0px;">
		<?php if(isset( $post->
		 image )){?>
		<tr>
			<td class="tdSocialImg" valign="top" align="center" style="padding: 0px; padding-left: 5px; padding-right: 5px;">
				<img class="productImg" src="<?php echo $post->image;?>" border="0" style="max-width:538px"/>
			</td>
		</tr>
		<?php }?>
		<tr>
			<td style="height: 15px; line-height: 15px;" height="15">
				 &nbsp;
			</td>
		</tr>
		<tr>
			<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 16px; line-height: 20px; font-weight: bold; color: #5e5e63; padding: 0px; padding-left: 25px; padding-right: 5px;">
				<?php echo $post->
				 post_title;?>
			</td>
		</tr>
		<tr>
			<td style="height: 15px; line-height: 15px;" height="15">
				 &nbsp;
			</td>
		</tr>
		<tr>
			<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 14px; line-height: 18px; font-weight: normal; color: #bfc0c3; padding: 0px; padding-left: 25px; padding-right: 5px;">
				<?php echo substr($post->
				 post_content, 0 ,150)."...";?>
			</td>
		</tr>
		<tr>
			<td style="height: 15px; line-height: 15px;" height="15">
				 &nbsp;
			</td>
		</tr>
		<tr>
			<td class="tdSocialImg" valign="middle" align="left" style="padding: 0px; padding-left: 25px;">
				<a href="<?php echo $post->guid;?>"><img class="social_img" src="<?php echo $template_dir . '/first/46081.jpg';?>" border="0"/></a>
			</td>
		</tr>
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<?php array_shift($posts) ?>
		<?php }?>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<?php foreach( $posts as $key =>
		 $post ){?>
		<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fff; border-bottom: 1px solid #eaeaea; padding: 0px;">
		<?php if(isset( $post->
		 image )){?>
		<tr>
			<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 16px; line-height: 20px; font-weight: bold; color: #5e5e63; padding: 0px; padding-left: 25px; padding-right: 5px;">
				<?php echo $post->
				 post_title;?>
			</td>
		</tr>
		<?php }?>
		<tr>
			<td style="height: 15px; line-height: 15px;" height="15">
				 &nbsp;
			</td>
		</tr>
		<tr>
			<td valign="top" align="left" style="text-align: left; font-family: Arial; font-size: 14px; line-height: 18px; font-weight: normal; color: #bfc0c3; padding: 0px; padding-left: 25px; padding-right: 5px;">
				<?php echo substr($post->
				 post_content, 0 ,150)."...";?>
			</td>
		</tr>
		<tr>
			<td style="height: 15px; line-height: 15px;" height="15">
				 &nbsp;
			</td>
		</tr>
		<tr>
			<td class="tdSocialImg" valign="middle" align="left" style="padding: 0px; padding-left: 25px;">
				<a href="<?php echo $post->guid;?>"><img class="social_img" src="<?php echo $template_dir . '/first/46081.jpg';?>" border="0"/></a>
			</td>
		</tr>
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0;" width="100%">
		<tr>
			<td style="height: 25px; line-height: 25px;" height="25">
				 &nbsp;
			</td>
		</tr>
		</table>
		<?php }?>
		<?php }?>
		<table border="0" cellpadding="0" cellspacing="0" style="background-color: #fff; padding:0; -ms-border-bottom-left-radius: 5px; -ms-border-bottom-right-radius: 5px; -o-border-bottom-left-radius: 5px; -o-border-bottom-right-radius: 5px; -khtml-border-radius-bottomright: 5px; -khtml-border-radius-bottomleft: 5px; border-bottom-right-radius: 5px; -moz-border-radius-bottomright: 5px; -webkit-border-bottom-right-radius: 5px; border-bottom-left-radius: 5px; -moz-border-radius-bottomleft: 5px; -webkit-border-bottom-left-radius: 5px;" width="100%">
		<tr>
			<td align="left" valign="top" style="padding: 15px; font-family: Arial; font-size: 12px; line-height: 18px; color: #818080;">
				 You’re recieving this newsletter because you bought widgets from us. <br>
				 Not interested anymore? <a style="color: #818080;" href="##NEWSMAN:list_unsubscribe##">Unsubscribe</a>. <br>
				 Having touble viewing this email? <a style="color: #818080;" href="##NEWSMAN:view_online##">View it in your browser</a>.
			</td>
		</tr>
		</table>
	</td>
</tr>
</table>
</body>
</html>