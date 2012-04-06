<?php

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

// Now display the options editing screen
echo '<div class="wrap">';

// header
echo "<h2>" . __( 'Attachments', immatt_dir ) . "</h2>";

	// get main options
	$opt_val = get_option( immatt_dir );
	
	// explode allowed extensions
	if ( !explode("\r",$opt_val['file_types']) ) { $legal_extentions = explode(" ",$opt_val['file_types']); } else { $legal_extentions = explode("\r",$opt_val['file_types']); }
	foreach ( $legal_extentions as $key => $value )
	{
		$legal_ext[trim($value)] = trim($value); // trim spaces (if any) from allowed extensions
	}

	// See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( $_POST[immatt_dir.'_save_att'] == 'Y' )
	{
		$file_ext = strtolower(end(explode(".",$_FILES['att_file']['name'])));
		
		if ( !in_array( $file_ext, $legal_ext ) ) // file extension is not allowed
		{
			?><div class="error"><p><strong><?php _e('Attachment could not be uploaded: the extension is not allowed.', immatt_dir ); ?></strong></p></div><?php
		}
		else // file extension is allowed
		{
			// if file can be uploaded
			if ( move_uploaded_file( $_FILES['att_file']['tmp_name'], get_option('upload_path').'/'.immatt_dir.'/'.$_FILES['att_file']['name'] ) )
			{
				$save = $wpdb->query($wpdb->prepare(" INSERT INTO {$wpdb->prefix}immatt (filename,filetitle,description,filedate) VALUES ('".$_FILES['att_file']['name']."','".$_POST['att_title']."','".$_POST['att_desc']."',now()) "));
				// Put an options updated message on the screen
				if ( $save ) { ?><div class="updated"><p><strong><?php _e('Attachment saved.', immatt_dir ); ?></strong></p></div><?php }
				else { // if settings could not be saved to db: delete file
					unlink(get_option('upload_path').'/'.immatt_dir.'/'.$_FILES['att_file']['name']);
					?><div class="error"><p><strong><?php _e('Attachment settings could not be saved.', immatt_dir ); ?></strong></p></div><?php
				}
			}
			else // file could not be uploaded
			{
				?><div class="error"><p><strong><?php _e('Attachment could not be uploaded.', immatt_dir ); ?></strong></p></div><?php
			}
		}
	}

// display error if upload folder(s) doesn't exist
if ( !is_dir( get_option('upload_path').'/'.immatt_dir ) )
{
	echo '<div class="error">';
	// uploads dir
	if ( !is_dir( get_option('upload_path') ) ) { echo '<p>'.__( 'The <strong>uploads</strong> folder doesn\'t exist and could not be created. Please add the folder manually inside <strong>wp-content</strong>.', immatt_dir ).'</p>'; }
	// plugin's upload dir in uploads
	echo '<p>'.__( sprintf( 'The folder <strong>%s</strong> could not be created into the uploads folder.', immatt_dir), immatt_dir ).'</p>';
	echo '</div>';
}

// options form
	
	$max_upload_size = min(letters_to_num(ini_get('post_max_size')), letters_to_num(ini_get('upload_max_filesize')));
	$max_upload = ($max_upload_size/(1024*1024));
?>

<form name="form2" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" enctype="multipart/form-data">
<input type="hidden" name="<?php echo immatt_dir; ?>_save_att" value="Y" />

<table class="form-table">

	<tr valign="top">
    	<th scope="row"><label for="att_title"><?php _e("Title", immatt_dir ); ?></label></th>
        <td><input type="text" id="att_title" name="att_title" value="" size="30" />
        <br /><span class="setting-description"><?php /*_e("", immatt_dir );*/ ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="att_desc"><?php _e("Description", immatt_dir ); ?></label></th>
        <td><textarea name="att_desc" id="att_desc" rows="3" cols="28"></textarea>
        <br /><span class="setting-description"><?php _e("Description of the attachment.", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="att_file"><?php _e("File", immatt_dir ); ?></label></th>
        <td><input type="file" id="att_file" name="att_file" value="" size="30" />
        <br /><span class="setting-description"><?php echo sprintf(__("The file must be one of these types: %s", immatt_dir), implode(' | ',$legal_extentions) ); ?><br />
        	<?php echo sprintf(__("Max. filesize allowed: %s MB", immatt_dir), $max_upload); ?> </span></td>
    </tr>

</table>

<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Upload attachment', immatt_dir ) ?>" />
<?php /*<input type="submit" name="Submit" value="<?php _e('Update Options', immatt_dir ) ?>" />*/ ?>
</p>
</form>

<hr />


<?php
	if ( isset($_POST[immatt_dir.'_actions']) )
	{
		switch ( strtolower($_POST[immatt_dir.'_actions']) )
		{
			case 'save': // save selected files
				foreach ( $_POST['att_id'] as $key => $value )
				{
					$wpdb->query($wpdb->prepare(" UPDATE {$wpdb->prefix}immatt SET
									filetitle = '{$_POST['att_title'][$value]}',
									description = '{$_POST['att_desc'][$value]}',
									exclude = '{$_POST['att_exclude'][$value]}'
								WHERE aid = '{$value}' "));

				}
			break;
			
			case 'delete': // delete selected files
				foreach ( $_POST['att_id'] as $key => $value )
				{
					if ( $_POST['immatt'][$value] == $value ) // only delete the files that are selected
					{
						if ( $wpdb->query($wpdb->prepare(" DELETE FROM {$wpdb->prefix}immatt WHERE aid = '{$value}' AND filename = '{$_POST['att_filename'][$value]}' ")) )
						{
							unlink(get_option('upload_path').'/'.immatt_dir.'/'.$_POST['att_filename'][$value]); // only delete the file if data in db could be deleted
							?><div class="updated"><p><strong><?php _e( sprintf('Attachment %s has been deleted.', $_POST['att_filename'][$value]), immatt_dir ); ?></strong></p></div><?php
						}
						else
						{
							?><div class="error"><p><strong><?php _e( sprintf('Attachment %s could not be deleted.', $_POST['att_filename'][$value]), immatt_dir ); ?></strong></p></div><?php
						}
					}
				}
			break;
			
			case '':
			default: break;
		}
	}
?>
<script type="text/javascript">
function checkAll(form) {
    for (i = 0, n = form.elements.length; i < n; i++) {
        if(form.elements[i].type == "checkbox" && !(form.elements[i].getAttribute('onclick', 2))) {
            if(form.elements[i].checked == true)
                form.elements[i].checked = false;
            else
                form.elements[i].checked = true;
        }
    }
}
</script>
<form name="imm-att" method="post">
<table class="widefat">
    <thead>
        <tr>
            <th class="check-column" scope="col"><input type="checkbox" onclick="checkAll(document.getElementById('imm-att'));"/></th>
            <th scope="col"><?php _e("ID", immatt_dir ); ?></th>
            <th scope="col"><?php _e("Title", immatt_dir ); ?></th>
            <th scope="col"><?php _e("Description", immatt_dir ); ?></th>
            <th scope="col" align="right"><?php _e("Sent", immatt_dir ); ?></th>
            <th scope="col"><?php _e("File", immatt_dir ); ?></th>
            <th scope="col"><?php _e("Exclude", immatt_dir ); ?><span title="<?php _e('Excluding the attachment makes it impossible to select for visitors, without deleting the file.',immatt_dir);?>">?</span></th>
            <th scope="col"><?php _e("Uploaded", immatt_dir ); ?></th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <th class="check-column" scope="col"><input type="checkbox" onclick="checkAll(document.getElementById('imm-att'));"/></th>
            <th scope="col"><?php _e("ID", immatt_dir ); ?></th>
            <th scope="col"><?php _e("Title", immatt_dir ); ?></th>
            <th scope="col"><?php _e("Description", immatt_dir ); ?></th>
            <th scope="col" align="right"><?php _e("Sent", immatt_dir ); ?></th>
            <th scope="col"><?php _e("File", immatt_dir ); ?></th>
            <th scope="col"><?php _e("Exclude", immatt_dir ); ?><span title="<?php _e('Excluding the attachment makes it impossible to select for visitors, without deleting the file.',immatt_dir);?>">?</span></th>
            <th scope="col"><?php _e("Uploaded", immatt_dir ); ?></th>
        </tr>
    </tfoot>
    <tbody>
    	<?php
			if ( $attachments = $wpdb->get_results(" SELECT * FROM {$wpdb->prefix}immatt ORDER BY aid ASC ", ARRAY_A) )
			{
				foreach ( $attachments as $attachment )
				{
					$aid     = (int) $attachment['aid'];
					$class   = ( $class == 'class="alternate"' ) ? '' : 'class="alternate"';
					$exclude = ( $attachment['exclude'] ) ? 'checked="checked"' : '';
					$date = mysql2date(get_option('date_format'), $attachment['filedate']);
					$time = mysql2date(get_option('time_format'), $attachment['filedate']);
					
					$file_url = ( get_option('upload_url_path') ) ? get_option('upload_url_path').'/'.immatt_dir : get_option('siteurl').'/wp-content/uploads/'.immatt_dir;
		?>
    	<tr valign="top" id="attachment-<?php echo $aid ?>" <?php echo $class ?>>
        	<th scope="row" class="check-column"><input name="att_id[<?php echo $aid ?>]" type="hidden" value="<?php echo $aid ?>" /><input name="immatt[<?php echo $aid ?>]" value="<?php echo $aid; ?>" type="checkbox" /></th>
            <td><strong><?php echo $aid ?></strong></td>
            <td><input type="text" id="att_title" name="att_title[<?php echo $aid ?>]" value="<?php echo $attachment['filetitle']; ?>" size="30" /></td>
            <td><textarea name="att_desc[<?php echo $aid ?>]" id="att_desc" rows="3" cols="28"><?php echo $attachment['description']; ?></textarea></td>
            <td align="center"><strong><?php echo number_format($attachment['sent'], 0, '.', ','); ?></strong></td>
            <td><input type="hidden" name="att_filename[<?php echo $aid ?>]" value="<?php echo $attachment['filename']; ?>" /><a href="<?php echo $file_url.'/'.$attachment['filename']; ?>" target="_blank"><?php echo $attachment['filename']; ?></a></td>
            <td><input name="att_exclude[<?php echo $aid ?>]" type="checkbox" value="1" <?php echo $exclude; ?> /></td>
            <td><?php echo $date.__(' at ',immatt_dir).$time; //get_option('date_format') ?></td>
    	</tr>
        <?php
				} // end foreach
			}
			else
			{
		?>
        <tr valign="top">
            <td colspan="8" align="center"><strong><?php _e('There aren\'t any attachments available yet.', immatt_dir); ?></strong></td>
        </tr>
        <?php
			}
		?>
	</tbody>
</table>

<p class="submit"><label for="actions"><?php _e('With selected:',immatt_dir); ?></label> <select id="actions" name="<?php echo immatt_dir; ?>_actions"><option value="save"><?php _e('- - -',immatt_dir); ?></option><option value="delete"><?php _e('Delete',immatt_dir); ?></option></select>
<input class="button-secondary delete" type="submit" name="<?php echo immatt_dir; ?>_update" value="<?php _e('Update attachments', immatt_dir ) ?>" /></p>

</div>