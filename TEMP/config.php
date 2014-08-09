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

?>
