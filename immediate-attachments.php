<?php
/*
Plugin Name: Immediate Attachments
Description: Lets your visitors fill in their emailaddress to instantly receive your brochures or other attachments by email.
Version: 0.4
Author: Onexa
Author URI: http://www.onexa.nl
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

define('version','0.2');
define('immatt_dir',plugin_basename( dirname(__FILE__) )); // used as uploads-folder in wp-content/uploads, domain for translation and as foldername for this plugin
define('immatt_url', WP_PLUGIN_URL.'/'.plugin_basename( dirname(__FILE__) ).'/');

/****************************************************/
/*	HOOKS											*/
/****************************************************/
/* add menu-items to admin_menu */
add_action('admin_menu', 'immatt_admin_menu');
add_action('wp_head', 'immatt_header');

//register_activation_hook( __FILE__, 'immatt_activate' );
register_activation_hook( __FILE__, 'install_tables' );

// filter the_content output
add_filter( 'the_content', 'filter_immatt' );
add_filter( 'the_excerpt', 'filter_immatt' );
// add shortcodes
add_shortcode( 'immattachment', 'setupSingle' );
add_shortcode( 'immattall', 'setupForAll' );

/****************************************************/
/*	FUNCTIONS										*/
/****************************************************/
/* localizing */
if ( function_exists( 'load_plugin_textdomain' ) )
{
	load_plugin_textdomain( immatt_dir, WP_PLUGIN_DIR . '/'.immatt_dir.'/lang' );
}

/****************************************************/
/*	function to add table to the db					*/
/****************************************************/
/* install tables */
function install_tables()
{
	global $wpdb, $wp_roles, $current_user;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	if ( !current_user_can('manage_options') ) 
		return;

	if ( is_plugin_active('role-manager/role-manager.php') || is_plugin_active('role-scoper/role-scoper.php') )
	{
		$role = get_role('administrator');
		if( !$role->has_cap('immatt') ) {
			$role->add_cap('immatt');	
		}
	}

	$immatt = $wpdb->prefix."immatt";
	
	if($wpdb->get_var("show tables like '$immatt'") != $immatt) // table doesn't exist yet
	{
		// add charset & collate like wp core
		$charset_collate = '';
	
		if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE $wpdb->collate";
		}
		
		$sql = "CREATE TABLE " . $immatt . " (
			aid BIGINT(20) NOT NULL AUTO_INCREMENT ,
			filename VARCHAR(255) NOT NULL ,
			filetitle VARCHAR(255) NOT NULL ,
			description MEDIUMTEXT NULL ,
			filedate DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			exclude TINYINT NULL DEFAULT '0' ,
			sent BIGINT(20) NOT NULL DEFAULT '0' ,
			PRIMARY KEY aid (aid)
			) $charset_collate;";
		
		dbDelta($sql);
	}
	
	// default values
	$def_val = array(
			'admin_email' => get_option('admin_email'),
			'file_types' => "doc\nexe\ntxt\nzip",
			'unwanted_domains' => __("easterbunny.com\nsantaclaus.com", immatt_dir),
			'email_subject' => __("Your requested attachment(s) from %blogname%", immatt_dir),
			'email_message' => __("Dear Sir/Madam,\n\nWe are happy to send you the attachment(s) that you have requested: %attachment_title%.\n\nBest regards,\n%blogname%", immatt_dir),
			'email_message_html' => __("<p>Dear Sir/Madam,</p>\n<p>We are happy to send you the attachment(s) that you have requested: %attachment_title%.</p>\n<p>Best regards,<br>\n%blogname%</p>", immatt_dir),
			'admin_message' => __("Dear administrator,\n\nThe following attachment(s) %attachment_title% were just requested by (and sent to) %receiver%.\n\nThis message was automatically sent by the Immediate Attachments plugin at %blogname%", immatt_dir)
	); add_option(immatt_dir, $def_val);
}

/****************************************************/
/*	function to add pages to the admin menu			*/
/****************************************************/
/* admin menu */
function immatt_admin_menu()
{
	if ( is_plugin_active('role-manager/role-manager.php') || is_plugin_active('role-scoper/role-scoper.php') ) { $cap = 'immatt'; } else { $cap = 'manage_options'; }
	
	//add_options_page
	add_menu_page(__('Immediate Attachments', immatt_dir), __('Imm. Att.', immatt_dir), $cap, __FILE__, 'immatt_plugin_options', immatt_url.'icon.png');
	add_submenu_page( __FILE__, __('Options', immatt_dir), __('Options', immatt_dir), $cap, __FILE__, 'immatt_plugin_options'); 
	add_submenu_page( __FILE__, __('Attachments', immatt_dir), __('Attachments', immatt_dir), $cap, dirname(__FILE__).'/attachments.php');
}


/* plugin options */
function immatt_plugin_options()
{
	// if the uploads folder itself doesn't exist yet
	if ( !is_dir( get_option('upload_path') ) ) {
		mkdir( get_option('upload_path'), 0777 ); chmod( get_option('upload_path'), 0777 );
	}
	// if the folder for uploading the attachments doesn't exist yet
	if( !is_dir( get_option('upload_path').'/'.immatt_dir ) ) {
		mkdir( get_option('upload_path').'/'.immatt_dir, 0777 ); chmod( get_option('upload_path').'/'.immatt_dir, 0777 );
	}
	
	// default values
	$def_val = array(
						'admin_email' => get_option('admin_email'),
						'file_types' => "doc\nexe\ntxt\nzip",
						'unwanted_domains' => __("easterbunny.com\nsantaclaus.com", immatt_dir),
						'email_subject' => __("Your requested attachment(s) from %blogname%", immatt_dir),
						'email_message' => __("Dear Sir/Madam,\n\nWe are happy to send you the attachment(s) that you have requested: %attachment_title%.\n\nBest regards,\n%blogname%", immatt_dir),
						'email_message_html' => __("<p>Dear Sir/Madam,</p>\n<p>We are happy to send you the attachment(s) that you have requested: %attachment_title%.</p>\n<p>Best regards,<br>\n%blogname%</p>", immatt_dir),
						'admin_message' => __("Dear administrator,\n\nThe following attachment(s) %attachment_title% were just requested by (and sent to) %receiver%.\n\nThis message was automatically sent by the Immediate Attachments plugin at %blogname%", immatt_dir)
					); 
	
// Now display the options editing screen
echo '<div class="wrap">';

// header
echo "<h2>" . __( 'Immediate Attachments', immatt_dir ) . "</h2>";
	
    // Read in existing option value from database
    $opt_val = get_option( immatt_dir );
	
    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( $_POST[immatt_dir.'_save'] == 'Y' )
	{
        // Read their posted value
		$opt_val = $_POST[immatt_dir];
		
        // Save the posted value in the database
        update_option( immatt_dir, $opt_val );

// Put an options updated message on the screen
?><div class="updated"><p><strong><?php _e('Options saved.', immatt_dir ); ?></strong></p></div><?php
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
?>

<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="<?php echo immatt_dir; ?>_save" value="Y" />

<table class="form-table">

	<tr valign="top">
    	<th scope="row"><label for="admin_email"><?php _e("Admin's emailaddress", immatt_dir ); ?></label></th>
        <td><input type="text" id="admin_email" name="<?php echo immatt_dir; ?>[admin_email]" value="<?php if ( !empty($opt_val['admin_email']) ) { echo $opt_val['admin_email']; } else { echo $def_val['admin_email']; } ?>" size="30" />
        <br /><span class="setting-description"><?php _e("This emailaddress will receive a notice when an attachment has been sent.", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="file_types"><?php _e("Allowed extensions", immatt_dir ); ?></label></th>
        <td><textarea name="<?php echo immatt_dir; ?>[file_types]" id="file_types" rows="8" cols="28"><?php if ( !empty($opt_val['file_types']) ) { echo $opt_val['file_types']; } else { echo $def_val['file_types']; } ?></textarea>
        <br /><span class="setting-description"><?php _e("File types that are allowed to be used as attachments.<br />Put each file type on a new line.", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="unwanted_domains"><?php _e("Unwanted email domains", immatt_dir ); ?></label></th>
        <td><textarea name="<?php echo immatt_dir; ?>[unwanted_domains]" id="unwanted_domains" rows="8" cols="28"><?php if ( !empty($opt_val['unwanted_domains']) ) { echo $opt_val['unwanted_domains']; } else { echo $def_val['unwanted_domains']; } ?></textarea>
        <br /><span class="setting-description"><?php _e("Emailaddresses with one of these domainnames cannot request an attachment. Put every domain on a new line.", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="email_subject"><?php _e("Email subject", immatt_dir ); ?></label></th>
        <td><input type="text" id="email_subject" name="<?php echo immatt_dir; ?>[email_subject]" value="<?php if ( !empty($opt_val['email_subject']) ) { echo $opt_val['email_subject']; } else { echo $def_val['email_subject']; } ?>" size="45" />
        <br /><span class="setting-description"><?php _e("The subject of the email that's being sent.", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="email_message"><?php _e("Email message", immatt_dir ); ?></label></th>
        <td><textarea name="<?php echo immatt_dir; ?>[email_message]" id="email_message" rows="8" cols="43"><?php if ( !empty($opt_val['email_message']) ) { echo $opt_val['email_message']; } else { echo $def_val['email_message']; } ?></textarea>
        <br /><span class="setting-description"><?php _e("The message that will be sent.", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="email_message_html"><?php _e("Email message (html)", immatt_dir ); ?></label></th>
        <td><textarea name="<?php echo immatt_dir; ?>[email_message_html]" id="email_message_html" rows="8" cols="43"><?php if ( !empty($opt_val['email_message_html']) ) { echo $opt_val['email_message_html']; } else { echo $def_val['email_message_html']; } ?></textarea>
        <br /><span class="setting-description"><?php _e("The message that will be sent (html).", immatt_dir ); ?></span></td>
    </tr>
    
    <tr valign="top">
    	<th scope="row"><label for="admin_message"><?php _e("Admin message", immatt_dir ); ?></label></th>
        <td><textarea name="<?php echo immatt_dir; ?>[admin_message]" id="admin_message" rows="8" cols="43"><?php if ( !empty($opt_val['admin_message']) ) { echo $opt_val['admin_message']; } else { echo $def_val['admin_message']; } ?></textarea>
        <br /><span class="setting-description"><?php _e("The message that will be sent to the administrator.", immatt_dir ); ?></span></td>
    </tr>

</table>


<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Update Options', immatt_dir ) ?>" />
</p>

</form>
</div>

<?php
} // end immatt_plugin_options


if ( is_admin() )
{
	global $wp_version;
	if ( $wp_version >= '2.5' ) // current WP version is newer (or equal to) v2.5
	{
		if (file_exists(ABSPATH . 'wp-includes/pluggable.php')) { require_once(ABSPATH . 'wp-includes/pluggable.php'); }
		else { require_once(ABSPATH . 'wp-includes/pluggable-functions.php'); }
	
		global $wpdb, $wp_roles;
		if ( in_array('role-manager/role-manager.php', (array) get_option( 'active_plugins')) || in_array('role-scoper/role-scoper.php', (array) get_option( 'active_plugins')) ) { $cap = 'immatt'; } else { $cap = 'manage_options'; }
		if ( current_user_can($cap) )
		{
			#add_filter('submitpost_box', upload_dir;
			// Load tinymce button 
		#	include_once ( WP_PLUGIN_DIR.'/'.immatt_dir.'/tinymce3/tinymce.php' );
		}
	}
} // end if is_admin


/* shortcodes */
function filter_immatt( $content )
{
	if ( stristr( $content, '[immattachment' )) {
		$search = "@(?:<p>)*\s*\[immattachment\s*=\s*(\w+|^\+)\]\s*(?:</p>)*@i";
		if (preg_match_all($search, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$replace = "[immattachment id=\"{$match[1]}\" lightbox=\"{$match[2]}\"]";
				$content = str_replace ($match[0], $replace, $content);
			}
		}
	}
	
	if ( stristr( $content, '[immattall' )) {
		$search = "@(?:<p>)*\s*\[immattall\s*=\s*(\w+|^\+)\]\s*(?:</p>)*@i";
		if (preg_match_all($search, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$replace = "[immattall lightbox=\"{$match[1]}\"]";
				$content = str_replace ($match[0], $replace, $content);
			}
		}
	}
	
	return $content;
}


function immatt_header()
{
	echo "\n".'<!-- '.__('Immediate Attachments',immatt_dir).' '.__('by',immatt_dir).' Onexa - www.onexa.nl -->'."\n";
	wp_register_style( 'immediate-attachments', WP_PLUGIN_URL.'/'.immatt_dir.'/style.css' );
	wp_enqueue_style( 'immediate-attachments' );
}


// setup the form for all attachments
function setupForAll( $atts )
{
	extract(shortcode_atts(array(
		'lightbox' 		=> 'false'
	), $atts));
	
	global $wpdb;
	$postID = get_the_ID();
	$out = '';
	
	// display a form with all attachments in it
	if ( $attachments = $wpdb->get_results( $wpdb->prepare(" SELECT aid,filetitle FROM {$wpdb->prefix}immatt WHERE exclude=0 ORDER BY filename ASC "), ARRAY_A) )
	{
		$out = '<div class="immatt"><form action="'.immatt_url.'sendmail.php" method="post">'."\n";
		
		if ( isset($_GET['immatt']) && ( isset($_GET['aid']) && $_GET['aid'] == 'multiple' ) ) { $out .= reportMessage($_GET['immatt']); }
		
		$out .= '<input type="hidden" name="redirect" value="'.(( is_front_page() ) ? get_bloginfo('siteurl') : get_permalink($postID)).'" />'."\n";
		$out .= '<input type="hidden" name="receive" value="multiple" /><ul>'."\n";
		$out .= '<input type="hidden" name="lightbox" value="'.$lightbox.'" />';
		
		$micro = explode('.',microtime()); $micro = implode('',$micro);
		$micro = explode(' ',$micro); $micro = implode('',$micro);
		
		$out .= '<li><label for="attachment'.$micro.'">'.__('Select attachment(s)',immatt_dir).'</label><select name="attachment[]" id="attachment'.$micro.'" class="immatt-list" multiple="multiple">'."\n";
		foreach ( $attachments as $attachment )
		{
			$out .= '<option value="'.$attachment['aid'].'">'.$attachment['filetitle'].'</option>'."\n";
		}
		$out .= '</select></li>'."\n";
		
		// emailaddress field
		$out .= '<li><label for="receiver'.$micro.'">'.__('Emailaddress',immatt_dir).'</label><input type="text" value="" name="receiver" id="receiver'.$micro.'" class="receiver" /></li>'."\n";
		
		// submit button
		$out .= '<li><input type="submit" value="'.__('Submit',immatt_dir).'" name="sending" class="submit" /></li>'."\n";
		
		$out .= '</ul></form></div>'."\n";
	}
	
	return $out;
}

/* setup form for single attachment */
function setupSingle( $atts )
{
	extract(shortcode_atts(array(
		'id' => '',
		'lightbox' => 'false'
	), $atts));
	
	global $wpdb;
	$postID = get_the_ID();
	$out = '';
	
	// display a form with all attachments in it
	if ( $attachment = $wpdb->get_results( $wpdb->prepare(" SELECT aid,filetitle FROM {$wpdb->prefix}immatt WHERE exclude=0 AND aid={$id}"), ARRAY_A) )
	{
		$out = '<div class="immatt"><form action="'.immatt_url.'/sendmail.php" method="post">'."\n";
		
		if ( isset($_GET['immatt']) && ( isset($_GET['aid']) && $_GET['aid'] == $id ) ) { $out .= reportMessage($_GET['immatt']); }
		
		$micro = explode('.',microtime()); $micro = implode('',$micro);
		$micro = explode(' ',$micro); $micro = implode('',$micro);
		
		$out .= '<input type="hidden" name="redirect" value="'.(( is_front_page() ) ? get_bloginfo('siteurl') : get_permalink($postID)).'" />'."\n";
		$out .= '<input type="hidden" name="receive" value="single" /><ul>'."\n";
		$out .= '<input type="hidden" name="lightbox" value="'.$lightbox.'" />';
		
		// attachment
		$out .= '<li><label>'.__('Receive attachment:',immatt_dir).'</label><strong>'.$attachment[0]['filetitle'].'</strong><input type="hidden" class="single" name="aid" value="'.$attachment[0]['aid'].'" /></li>'."\n";
		
		// emailaddress field
		$out .= '<li><label for="receiver'.$micro.'">'.__('Emailaddress',immatt_dir).'</label><input type="text" value="" name="receiver" id="receiver'.$micro.'" class="receiver" /></li>'."\n";
		
		// submit button
		$out .= '<li><input type="submit" value="'.__('Submit',immatt_dir).'" name="sending" class="submit" /></li>'."\n";
		
		$out .= '</ul></form></div>'."\n";
	}
	
	return $out;
}

// messages
function reportMessage($arg)
{
	switch ( strtolower($arg) )
	{
		// mail was sent succesfully
		case 'mailsent': return '<div class="message"><p>'.__('The attachment(s) you have requested have succesfully been sent.',immatt_dir).'</p></div>';
		break;
		
		// mail could not be sent
		case 'mailfailed': return '<div class="message error"><p>'.__('Unfortunately we were unable to send you the requested attachments.',immatt_dir).'</p></div>';
		break;
		
		// emailaddress is invalid
		case 'addressinvalid': return '<div class="message error"><p>'.__('The emailaddress you have provided is invalid. Please fill in a valid emailaddress.',immatt_dir).'</p></div>';
		break;
		
		// no attachment was selected 
		case 'noattachment': return '<div class="message error"><p>'.__('You must select an attachment.',immatt_dir).'</p></div>';
		break;
		
		// error has occured | hidden field receive wasn't provided
		case 'error': return '<div class="message error"><p>'.__('An unknown error has occured.',immatt_dir).'</p></div>';
		break;
	}
}


if ( !function_exists('letters_to_num') )
{
	function letters_to_num($v){ //This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
		$l = substr($v, -1);
		$ret = substr($v, 0, -1);
		switch(strtoupper($l)){
		case 'P':
			$ret *= 1024;
		case 'T':
			$ret *= 1024;
		case 'G':
			$ret *= 1024;
		case 'M':
			$ret *= 1024;
		case 'K':
			$ret *= 1024;
			break;
		}
		return $ret;
	}
}

?>