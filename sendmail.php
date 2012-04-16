<?php

//error_reporting(E_ALL);
//ini_set('error_reporting','E_ALL');

// set referer
//$referer = isset( $_POST[ 'HTTP_REFERER' ] ) ? $_SERVER[ 'HTTP_REFERER' ] : '/';
$referer = isset($_POST['redirect']) ? $_POST['redirect'] : get_option('siteurl');

// let's load WordPress
$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once( $root . '/wp-load.php' );

if ( isset( $_POST['receive'] ) )
{	
	// Read in existing option value from database
    $opt_val = get_option( upload_dir );
	
	$host = explode('@', $_POST['receiver']);
	$unwanted_domains = explode("\n",$opt_val['unwanted_domains']);
	
	if ( ( filter_var($_POST['receiver'], FILTER_VALIDATE_EMAIL) ) && ( true == checkmail($_POST['receiver']) ) && ( !in_array($host[1], $unwanted_domains) ) )
	{
		require(dirname(__FILE__).'/phpmailer/class.phpmailer.php'); // requiring phpmailer class
		
		switch( strtolower($_POST['receive']) )
		{
			case 'single': // form for 1 attachment has been used
				
				$file = $wpdb->get_results(" SELECT filename,filetitle,description FROM {$wpdb->prefix}immatt WHERE exclude=0 AND aid={$_POST['aid']} ", ARRAY_A);
				
				$attachment	= get_option('upload_path').'/'.upload_dir.'/'.$file[0]['filename'];
				$new_name	= $file[0]['filetitle'].'.'.get_file_extension($file[0]['filename']);
								
				$mail			= new PHPMailer();
				$mail->Mailer	= 'mail';
				$mail->From		= $opt_val['admin_email'];
				$mail->FromName	= html_entity_decode(get_option('blogname'));
				
				$search		= array(
									'%attachment_title%',
									'%attachment_desc%',
									'%attachment_info%',
									'%blogname%'
								);
				$replace	= array(
									$file[0]['filetitle'],
									$file[0]['description'],
									$file[0]['filetitle'].' - '.$file[0]['description'],
									get_option('blogname')
								);
				
				$mail->Subject	= html_entity_decode(str_replace( $search, $replace, $opt_val['email_subject'] ));
				$mail->Body		= str_replace( $search, $replace, $opt_val['email_message_html'] );
				$mail->AltBody	= str_replace( $search, $replace, $opt_val['email_message'] );
				$mail->WordWrap = 70;
				
				$mail->IsHTML(true);
				$mail->AddAddress( $_POST['receiver'] );
				$mail->AddReplyTo( $opt_val['admin_email'], get_option('blogname') );
				$mail->AddAttachment( $attachment, $new_name, 'base64', get_mimetype($attachment) );
				
				$args = array();
				
				//if the message is sent successfully print "Mail sent". Otherwise print "Mail failed"
				if ( $mail->Send() )
				{
					$wpdb->query(" UPDATE {$wpdb->prefix}immatt SET sent=sent+1 WHERE aid = '{$_POST['aid']}' ");
					$args['immatt'] = 'mailsent';
					$args['aid'] = $_POST['aid'];
					$mail->ClearAddresses();  
					$mail->ClearAttachments();
					
					// send a message to admin
					$amail			= new PHPMailer();
					$amail->From		= $opt_val['admin_email'];
					$amail->FromName	= get_option('blogname');
					$amail->AddAddress( $opt_val['admin_email'] );
					
					$search[]	= '%receiver%';
					$replace[]	= $_POST['receiver'];
					
					$amail->Subject		= str_replace( $search, $replace, 'CC: '.$opt_val['email_subject'] );
					$amail->Body		= str_replace( $search, $replace, $opt_val['admin_message'] );
					$amail->IsHTML(false);
					$amail->WordWrap = 70;
					$amail->Send();
					
					$amail->ClearAddresses();
				}
				else
				{
					$args['immatt'] = 'mailfailed';
					$args['aid'] = $_POST['aid'];
					$args['errors'] = $mail->ErrorInfo;
					$mail->ClearAddresses();  
					$mail->ClearAttachments();
				}
				
			break;
			
			case 'multiple': // form for multiple attachments has been used;
				
				if ( $att_ids = @implode(',',$_POST['attachment']) )
				{
					$files = $wpdb->get_results(" SELECT filename,filetitle,description FROM {$wpdb->prefix}immatt WHERE exclude=0 AND aid IN({$att_ids}) ", ARRAY_A);
					
					$mail			= new PHPMailer();
					$mail->Mailer	= 'mail';
					$mail->From		= $opt_val['admin_email'];
					$mail->FromName	= html_entity_decode(get_option('blogname'));
					
					$filenames = array(); $descriptions = array(); $fileinfo = array();
					
					foreach ( $files as $file )
					{
						$attachment		= get_option('upload_path').'/'.upload_dir.'/'.$file['filename'];
						$new_name		= $file['filetitle'].'.'.get_file_extension($file['filename']);
						
						$filenames[]	= $file['filetitle'];
						$descriptions[]	= $file['description'];
						$fileinfo[]		= $file['filetitle'].' - '.$file['description'];
						// add attachment to mail
						$mail->AddAttachment( $attachment, $new_name, 'base64', get_mimetype($attachment) );
					}
					
					$search		= array(
										'%attachment_title%',
										'%attachment_desc%',
										'%attachment_info%',
										'%blogname%'
									);
					$replace	= array(
										implode(', ',$filenames),
										implode("\n",$descriptions),
										implode("\n",$fileinfo),
										get_option('blogname')
									);
					
					$mail->Subject	= html_entity_decode(str_replace( $search, $replace, $opt_val['email_subject'] ));
					$mail->Body		= str_replace( $search, $replace, $opt_val['email_message_html'] );
					$mail->AltBody	= str_replace( $search, $replace, $opt_val['email_message'] );
					$mail->WordWrap = 70;
					
					$mail->IsHTML(true);
					$mail->AddAddress( $_POST['receiver'] );
					$mail->AddReplyTo( $opt_val['admin_email'], get_option('blogname') );
					
					$args = array();
					
					//if the message is sent
					if ( $mail->Send() )
					{
						$wpdb->query(" UPDATE {$wpdb->prefix}immatt SET sent=sent+1 WHERE aid IN({$att_ids}) ");
						$args['immatt'] = 'mailsent';
						$args['aid'] = 'multiple';
						$mail->ClearAddresses();  
						$mail->ClearAttachments();
						
						// send a message to admin
						$amail				= new PHPMailer();
						$amail->From		= $opt_val['admin_email'];
						$amail->FromName	= html_entity_decode(get_option('blogname'));
						$amail->AddAddress( $opt_val['admin_email'] );
						
						$search[]	= '%receiver%';
						$replace[]	= $_POST['receiver'];
						
						$amail->Subject	= html_entity_decode(str_replace( $search, $replace, 'CC: '.$opt_val['email_subject'] ));
						$amail->Body		= str_replace( $search, $replace, $opt_val['admin_message'] );
						$amail->IsHTML(false);
						$amail->WordWrap = 70;
						$amail->Send();
						
						$amail->ClearAddresses(); 
					}
					else
					{
						$args['immatt'] = 'mailfailed';
						$args['aid'] = 'multiple';
						$args['errors'] = $mail->ErrorInfo;
						$mail->ClearAddresses();  
						$mail->ClearAttachments();
					}
				}
				else // no attachments selected
				{
					$args['immatt'] = 'noattachment';
					$args['aid'] = 'multiple';
				}
				
			break;
			
			default:
				$args['immatt'] = 'error';
				$args['aid'] = isset($_POST['aid']) ? $_POST['aid'] : 'multiple';
			break;
		} // end switch
	}
	else // emailaddress is not valid
	{
		$args['immatt'] = 'addressinvalid';
		$args['aid'] = isset($_POST['aid']) ? $_POST['aid'] : 'multiple';
	}
}
else
{
	$args['immatt'] = 'error';
	$args['aid'] = isset($_POST['aid']) ? $_POST['aid'] : 'multiple';
}

	// sending back to referer
	$args['lightbox'] = isset($_POST['lightbox']) ? 'true' : 'false';	
	$url = add_query_arg($args,$referer);
	header( 'Location: '.$url );
	exit;



function checkmail($email) 
{
	if ( eregi("^[0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-z]{2,4}$",$email) ) 
    {
    	$host = explode('@', $email);
    	if ( checkdnsrr($host[1].'.', 'MX') )		return true;
    	if ( checkdnsrr($host[1].'.', 'A') )		return true;
    	if ( checkdnsrr($host[1].'.', 'CNAME') )	return true; 
    }
    return false; 
} // end function checkmail

	function get_file_extension($file)
	{
        return array_pop(explode('.',$file));
    }

    function get_mimetype($value='')
	{
        $ct['htm'] = 'text/html';
        $ct['html'] = 'text/html';
        $ct['txt'] = 'text/plain';
        $ct['asc'] = 'text/plain';
        $ct['bmp'] = 'image/bmp';
        $ct['gif'] = 'image/gif';
        $ct['jpeg'] = 'image/jpeg';
        $ct['jpg'] = 'image/jpeg';
        $ct['jpe'] = 'image/jpeg';
        $ct['png'] = 'image/png';
        $ct['ico'] = 'image/vnd.microsoft.icon';
        $ct['mpeg'] = 'video/mpeg';
        $ct['mpg'] = 'video/mpeg';
        $ct['mpe'] = 'video/mpeg';
        $ct['qt'] = 'video/quicktime';
        $ct['mov'] = 'video/quicktime';
        $ct['avi']  = 'video/x-msvideo';
        $ct['wmv'] = 'video/x-ms-wmv';
        $ct['mp2'] = 'audio/mpeg';
        $ct['mp3'] = 'audio/mpeg';
        $ct['rm'] = 'audio/x-pn-realaudio';
        $ct['ram'] = 'audio/x-pn-realaudio';
        $ct['rpm'] = 'audio/x-pn-realaudio-plugin';
        $ct['ra'] = 'audio/x-realaudio';
        $ct['wav'] = 'audio/x-wav';
        $ct['css'] = 'text/css';
        $ct['zip'] = 'application/zip';
        $ct['pdf'] = 'application/pdf';
        $ct['doc'] = 'application/msword';
        $ct['bin'] = 'application/octet-stream';
        $ct['exe'] = 'application/octet-stream';
        $ct['class']= 'application/octet-stream';
        $ct['dll'] = 'application/octet-stream';
        $ct['xls'] = 'application/vnd.ms-excel';
        $ct['ppt'] = 'application/vnd.ms-powerpoint';
        $ct['wbxml']= 'application/vnd.wap.wbxml';
        $ct['wmlc'] = 'application/vnd.wap.wmlc';
        $ct['wmlsc']= 'application/vnd.wap.wmlscriptc';
        $ct['dvi'] = 'application/x-dvi';
        $ct['spl'] = 'application/x-futuresplash';
        $ct['gtar'] = 'application/x-gtar';
        $ct['gzip'] = 'application/x-gzip';
        $ct['js'] = 'application/x-javascript';
        $ct['swf'] = 'application/x-shockwave-flash';
        $ct['tar'] = 'application/x-tar';
        $ct['xhtml']= 'application/xhtml+xml';
        $ct['au'] = 'audio/basic';
        $ct['snd'] = 'audio/basic';
        $ct['midi'] = 'audio/midi';
        $ct['mid'] = 'audio/midi';
        $ct['m3u'] = 'audio/x-mpegurl';
        $ct['tiff'] = 'image/tiff';
        $ct['tif'] = 'image/tiff';
        $ct['rtf'] = 'text/rtf';
        $ct['wml'] = 'text/vnd.wap.wml';
        $ct['wmls'] = 'text/vnd.wap.wmlscript';
        $ct['xsl'] = 'text/xml';
        $ct['xml'] = 'text/xml';

        $extension = get_file_extension($value);

        if (!$type = $ct[strtolower($extension)])
		{
            $type = 'text/html';
        }

        return $type;
    }

?>