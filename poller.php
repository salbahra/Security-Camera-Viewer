<?php

#Tell main we are calling it
define('Cameras', TRUE);

#Include configuration
require_once("main.php");

#Connect to the database
$link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
if ($link->connect_errno) {
	die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
}

$folder = $sites[$currentsite]["videostore"]."Cameras";

if (!chdir($folder)) return;

$cameras = directoryToArray(".",false,true,false);
$videos_stmt = $link->prepare("INSERT IGNORE INTO videos (camera,timestamp,duration,action,filepath,thumbnailpath) VALUES(?,?,?,?,?,?)");
$videos_stmt->bind_param("ssssss",$cameraname,$timestamp,$duration,$action,$filepath,$imgOut);

foreach ($cameras as $camera) {

    if (!chdir($camera)) continue;

	$cameraname = str_replace("./","",preg_replace("/\([^)]+\)/","",$camera));

    $files = directoryToArray(".",true,false,true,'/\.media$|\.xml$/');
	rsort($files);

    $x = 0;
    foreach ($files as $path) {

        if ($x>59) break;
        $file = explode("/", $path);
        $i = count($file);
        $name = $file[$i-1];
        preg_match("/(LMR)\sS(\d{2})(\d{2})(\d{2}).(\d{7})-(\d{4})\sD(\d+)\sA(\d+)/",$name,$matches);

        if ($matches[1] !== "LMR") continue;

        $action = $matches[8];
        $duration = $matches[7];
        $day = $file[$i-2];
        $month = $file[$i-3];
        $year = $file[$i-4];
        $timestamp = strtotime("$month/$day/$year ".$matches[2].":".$matches[3].":".$matches[4].".".$matches[5]."-".$matches[6]);
        $filepath = str_replace(".",$folder,$camera).str_replace("./","/",$path);
        $imgOut = thumbLocation($filepath,$currentsite);
        if (!is_file($imgOut)) genThumb($filepath,$currentsite);
        $videos_stmt->execute();
        $x++;
    }
    if (!chdir("..")) exit();
}

$videos_stmt->close();

$thumbFolder = $sites[$currentsite]["videostore"]."Thumbnails/";
exec("find ".$thumbFolder." -mtime +5 -exec rm {} \;");

if ($imap_username == '' || $imap_password == '') exit();

$inbox = imap_open($imap_hostname,$imap_username,$imap_password) or die('Cannot connect to email: ' . imap_last_error());
$emails = imap_search($inbox,'ALL');
$messages = array();

if($emails) {
  
	sort($emails);
  
	$alert_stmt = $link->prepare("INSERT INTO alerts (sitename,camera,time,img) VALUES(?,?,?,?)");
	$alert_stmt->bind_param("ssss",$sitename,$camera,$time,$img);

	foreach($emails as $email_number) {
    
		$overview = imap_fetch_overview($inbox,$email_number,0);
		if ($overview[0]->from != "do-not-reply@logitech.com" || !strpos($overview[0]->subject,"camera detected motion at")) continue;

		$body = get_part($inbox, $email_number, "TEXT/HTML");
		preg_match("/Your[\s|\w|,|:]+(AM|PM)/",$body,$matches);

		$struct = imap_fetchstructure($inbox,$email_number);
		$contentParts = count($struct->parts);

		if ($contentParts >= 2) {
			for ($i=2;$i<=$contentParts;$i++) {
				$att[$i-2] = imap_bodystruct($inbox,$email_number,$i);
			}
			for ($k=0;$k<sizeof($att);$k++) {
				if ($att[$k]->parameters[0]->value == "us-ascii" || $att[$k]->parameters[0]->value == "US-ASCII") {
					if ($att[$k]->parameters[1]->value != "") {
						$imgname = $att[$k]->parameters[1]->value;
						break;
					}
				} elseif ($att[$k]->parameters[0]->value != "iso-8859-1" && $att[$k]->parameters[0]->value != "ISO-8859-1") {
					$imgname = $att[$k]->parameters[0]->value;
					break;
				}
			}
			if (!isset($imgname)) continue;
			
			$info = explode(" - ",$imgname);
			$camera = $info[0];
			$sitename = $info[1];
			foreach($sites as $name => $sitedata) {
				if (strpos($info[1], $name) !== false) $sitename = $name;
			}
			$time = substr($info[2],0,-4);
			if ($matches[1] == "PM") {
				$tmp = explode(" ",$time);
				preg_match("/(\d\d)(\d\d\d\d)/", $tmp[1],$time_matches);
				if ($time_matches[1] != 12) $tmp[1] = ($time_matches[1]+12).$time_matches[2];
				$time = implode(" ",$tmp);
			}
			$img = imap_base64(imap_fetchbody($inbox,$email_number,$imgname+2));
			$alert_stmt->execute();
		}
		imap_delete($inbox,$email_number);
	}
	$alert_stmt->close();
} 
imap_close($inbox, CL_EXPUNGE);


$link->close();

function get_mime_type(&$structure) {
	$primary_mime_type = array("TEXT", "MULTIPART","MESSAGE", "APPLICATION", "AUDIO","IMAGE", "VIDEO", "OTHER");
	if($structure->subtype) {
		return $primary_mime_type[(int) $structure->type] . '/' .$structure->subtype;
	}
	return "TEXT/PLAIN";
}
function get_part($stream, $msg_number, $mime_type, $structure = false,$part_number = false) {
   
   	if(!$structure) {
   		$structure = imap_fetchstructure($stream, $msg_number);
   	}
   	if($structure) {
   		if($mime_type == get_mime_type($structure)) {
   			if(!$part_number) {
   				$part_number = "1";
   			}
   			$text = imap_fetchbody($stream, $msg_number, $part_number);
   			if($structure->encoding == 3) {
   				return imap_base64($text);
   			} else if($structure->encoding == 4) {
   				return imap_qprint($text);
   			} else {
   				return $text;
	   		}
   		}

		if($structure->type == 1) /* multipart */ {
	   		while(list($index, $sub_structure) = each($structure->parts)) {
	   			if($part_number) {
	   				$prefix = $part_number . '.';
	   			} else {
	   				$prefix = '';
	   			}
	   			$data = get_part($stream, $msg_number, $mime_type, $sub_structure,$prefix . ($index + 1));
	   			if($data) {
	   				return $data;
	   			}
	   		}
   		}
   	}
	return false;
}
?>