<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes"/>
<title>Newsletter</title>
<style type="text/css">
		table{border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;}
		@media screen and (max-width: 600px)
		{
			table
			{
				width: 100% !important;
				max-width: 100% !important;
				float: none !important;
				clear: both !important;
			}
			table[id=logo],table[id=newstitle], table[id=logo] td, table[id=newstitle] td
			{
				width: 100% !important;
				max-width: 100% !important;
				text-align: center !important;
				padding: 0px !important;
				height: 70px !important;
			}
			td[id=splash], td[id=splash] img
			{
				width: 100% !important;
				max-width: :;% !important;
			}
			table[class=textl] td
			{
				text-align: left;
				padding-left: 20px;
				padding-right: 20px;
			}
			table[class=imgl] td
			{
				padding-top: 5px;
				padding-bottom: 10px;
			}
		}
</style>
</head>
<body style="margin: 0px; padding: 0px; background-color: #182c47;">
<table width="600" align="center" cellpadding="0" cellspacing="0" border="0" style="padding: 0px; width: 600px; background-color: #FFFFFF;">
<tr>
	<td valign="top" style="padding: 0px;">
		<table width="100%" align="center" border="0" cellpadding="0" cellspacing="0" style="padding: 0px; width: 100%; background-color: #FFFFFF; color: #c2272b; height: 110px;">
		<tr>
			<td valign="middle" style="padding: 0px;">
				<table id="logo" align="left" width="460" border="0" cellpadding="0" cellspacing="0" style="padding: 0px; background-color: #FFFFFF; height: 110px; width: 460px;">
				<tr>
					<td align="left" valign="middle" style="height: 110px; padding-left: 20px; font-family: Arial, Helvetica, sans-serif; font-size: 24px; font-weight: normal; line-height: 28px; color: #000000;">
						 LOGO NAME
					</td>
				</tr>
				</table>
				<table id="newstitle" align="right" width="120" border="0" cellpadding="0" cellspacing="0" style="width: 120px; padding: 0px; background-color: #FFFFFF; height: 110px;">
				<tr>
					<td align="left" valign="middle" style="padding-right: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 20px; color: #000000; height: 110px;">
						 Newsletter nr. 5
					</td>
				</tr>
				</table>
			</td>
		</tr>
		</table>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 100%; padding: 0px;">
		<tr>
			<td id="splash" align="center" style="padding: 0px;">
				<img src="<?php echo $template_dir . 'second/46013.jpg';?>" border="0" style="display: block;"/>
			</td>
		</tr>
		</table>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 100%; padding: 0px;">
		<tr>
			<td style="padding: 0px; height: 20px; line-height: 20px; font-size: 20px; background-color: #FFFFFF;">
				 &nbsp;
			</td>
		</tr>
		</table>
		<?php if( isset( $posts ) ){?>
		<?php foreach( $posts as $key =>
		 $post ){?>
		<table width="580" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 580px; padding: 0px;">
		<tr>
			<td valign="top" style="padding: 0px;">
				<table class="textl" align="right" width="270" cellpadding="0" cellspacing="0" border="0" style="width: 270px; padding: 0px; border: none;">
				<tr>
					<td style="padding-top: 5px; font-family: Arial, Helvetica, sans-serif; font-size: 18px; line-height: 22px; color: #000000; font-style: italic; font-weight: bold;">
						<?php $post->
						 post_title;?>
					</td>
				</tr>
				<tr>
					<td style="padding-top: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 18px; color: #828282; font-style: italic; font-weight: normal;">
						<?php echo substr($post->
						 post_content, 0 ,150)."...";?>
					</td>
				</tr>
				</table>
				<?php if( isset( $post->
				 image ) ){?>
				<table class="imgl" align="left" width="290" cellpadding="0" cellspacing="0" border="0" style="width: 290px; padding: 0px; border: none;">
				<tr>
					<td valign="top" align="center">
						<a href="<?php $post->guid;?>"> <img src="<?php echo $post->image;?>" border="0" style="vertical-align: middle;max-width:290px" /> </a>
					</td>
				</tr>
				</table>
				<?php }?>
			</td>
		</tr>
		</table>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 100%; padding: 0px;">
		<tr>
			<td style="padding: 0px; height: 20px; line-height: 20px; font-size: 20px; background-color: #FFFFFF;">
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
		<table cellpadding="0" cellspacing="0" width="600" border="0" style="padding: 0px; width: 600px;">
		<tr>
			<td style="background-color: #182c47; text-align: center; height: 50px; color: #FFFFFF; font-family: Arial, Helvetica, sans-serif; font-size:12px;">
				<newsman:footer noblock="1" width="600" color="#FFFFFF" bgcolor="#182c47" fontname="Arial, sans-serif" fontsize="11" align="center">
				</newsman:footer>
			</td>
		</tr>
		</table>
	</td>
</tr>
</table>
</body>
</html>