<?php

$root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

if (file_exists( $root . '/wp-load.php' )) {
	// WP 2.6
	require_once( $root . '/wp-load.php' );
} else {
	// Before 2.6
	if (!file_exists( $root . '/wp-config.php' ))  {
		echo "Could not find wp-config.php";	
		die;	
	}// stop when wp-config is not there
	require_once( $root . '/wp-config.php' );
}

require_once(ABSPATH.'/wp-admin/admin.php');

// check for rights
if(!current_user_can('edit_posts')) die;

global $wpdb;

?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php _e('Immediate Attachments',upload_dir); ?></title>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/tiny_mce_popup.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/mctabs.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo WP_PLUGIN_URL; ?>/<?php echo upload_dir; ?>/tinymce3/tinymce.js"></script>
	<base target="_self" />
</head>
<body id="link" onLoad="tinyMCEPopup.executeOnLoad('init();');document.body.style.display='';document.getElementById('gallerytag').focus();" style="display: none">
<!-- <form onsubmit="insertLink();return false;" action="#"> -->
	<form name="ImmAtt" action="#">
	<div class="tabs">
		<ul>
			<li id="info_tab" class="current"><span><a href="javascript:mcTabs.displayTab('info_tab','info_panel');" onMouseDown="return false;"><?php _e( 'Insert attachment', upload_dir ); ?></a></span></li>
		</ul>
	</div>
	
	<div class="panel_wrapper">
		<!-- gallery panel -->
		<div id="info_panel" class="panel current">
		<br />
		<table border="0" cellpadding="4" cellspacing="0">
         <tr>
            <td nowrap="nowrap"><label for="attachment"><?php _e( 'Select attachment', upload_dir ); ?></label></td>
            <td><select id="attachment" name="attachment" style="width: 200px">
                <option value="0"><?php _e( 'All', upload_dir); ?></option>
				<?php
                if ( $attachments = $wpdb->get_results(" SELECT * FROM {$wpdb->prefix}immatt ORDER BY aid ASC ", ARRAY_A) )
				{
					foreach ( $attachments as $attachment )
					{
						echo '<option value="'.$attachment['aid'].'">'.$attachment['filetitle'].'</option>'."\n";
					}
				}
				?>
            </select></td>
          </tr>
        </table>
		</div>
	</div>

	<div class="mceActionPanel">
		<div style="float: left">
			<input type="button" id="cancel" name="cancel" value="<?php _e( 'Cancel', upload_dir); ?>" onClick="tinyMCEPopup.close();" />
		</div>

		<div style="float: right">
			<input type="submit" id="insert" name="insert" value="<?php _e( 'Insert', upload_dir); ?>" onClick="insertImmAttLink();" />
		</div>
	</div>
</form>
</body>
</html>