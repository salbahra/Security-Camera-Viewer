<?php

#Set timezone
date_default_timezone_set('US/Central');

#WebApp Title
$webtitle = "Camera Viewer";

#Set private key for cross site communication
#This is used to encrypt communication between sites
#This is not used if you only have one premise with cameras you wish to view
$private_key = "RANDOMPASS";

#Set hostname/username/password/database for mysql account
$mysql_hostname = 'localhost';
$mysql_username = 'user';
$mysql_password = 'pass';
$mysql_database = 'db';

#Set hostname/username/password for email account with alerts
$imap_hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$imap_username = 'alert@gmail.com';
$imap_password = 'password';

$currentsite = "Home";

#Define sites
$sites = array(
	"Home" => array(
		"url" => "https://yourdomain.com/cameras/",
		"username" => "USER",
		"password" => "PASS",
		"videostore" => "/var/cameras/",
		"cameras" => array(
			"Camera 1" => array(
				"url" => "feeds/camera1.mjpg"
			)
		)
	)
);

#Set denied message
$denied = "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\"><html><head><title>401 Authorization Required</title></head><body><h1>Authorization Required</h1><p>This server could not verify that you are authorized to access the document requested.  Either you supplied the wrong credentials (e.g., bad password), or your browser doesn't understand how to supply the credentials required.</p></body></html>";

?>
