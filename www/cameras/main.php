<?php

#Refuse if a direct call has been made
if(!defined('Cameras')){echo $denied;exit();}

#Include configuration
require_once("config.php");

if (isset($_SERVER['SERVER_NAME'])) $base_url = "https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];

#Set proper headers for cross domain calls
if (isset($_SERVER['HTTP_ORIGIN'])) {
    foreach($sites as $sitename => $sitedata) {
        if (strpos($sitedata["url"],$_SERVER['SERVER_NAME'])) continue;
        $url = parse_url($sitedata['url']);
        $url_str = $url['scheme']."://".$url['host'];
        if ($_SERVER['HTTP_ORIGIN'] == $url_str) header('Access-Control-Allow-Origin: '.$url_str);
    }
}
header('Access-Control-Allow-Credentials: true');

#Call action if requested and allowed
if (isset($_REQUEST['action'])) {
	if (is_callable($_REQUEST['action'])) {
		if ((isset($_GET['sig']) && isset($_GET['username'])) && !is_auth()) {
			if (!sso()) exit();
		}
		if (($_REQUEST['action'] == "gettoken" || $_REQUEST['action'] == "checktoken" || $_REQUEST['action'] == "login") || is_auth()) {
			call_user_func($_REQUEST['action']);
		}
		exit();
	} else {
		exit();
	}
}

#Content generation functions
function make_list() {
	global $sites, $private_key;
	$list = '';
    $sig = urlencode(base64_encode(hash_hmac('sha256', $_SESSION['username'], $private_key, TRUE)));

	foreach ($sites as $sitename => $sitedata) {
		$list .= '<li data-role="list-divider">'.$sitename.'</li>';
		foreach ($sitedata['cameras'] as $camera => $camdata) {
    		$list .= '<li><a href="javascript:preplive(\''.$sitename.'\',\''.strtolower(preg_replace("/\s/","",$camera)).'\')"><img src="'.$sitedata["url"].'?action=grab_still&username='.$_SESSION['username'].'&sig='.$sig.'&camid='.$camera.'" />'.$camera.'</a><a href="javascript:openVideoList(\''.$sitename.'\',\''.$camera.'\')">History</a></li>';
		}
	}
	return $list;
}
function make_all_cams() {
    global $sites;

    $newlist = '<ul data-role="listview" data-inset="true">';
    foreach ($sites as $sitename => $sitedata) {
        foreach ($sitedata['cameras'] as $camera => $camdata) {
        	$url = insertstring($sitedata["url"], $sitedata["username"].":".$sitedata["password"]."@", "://").$camdata["url"];
            $newlist .= '<li><a href="#"><img src="img/loading.gif" data-videoSource="'.$url.'" /><p class="ui-li-aside">'.$camera.'</p></a></li>';
        }
    }
    
    return $newlist."</ul>";
}
function make_footer() {
    $buttons = array(
        "Cameras" => array(
            "icon" => "home",
            "url" => "#cams"
        ),
        "View All" => array(
            "icon" => "grid",
            "url" => "#allcams"
        )
    );
    $footer = '<div data-role="footer" data-position="fixed" data-tap-toggle="false" style="text-align:center"><div data-role="controlgroup" data-type="horizontal">';
    foreach ($buttons as $button => $data) {
        $footer .= '<a data-icon="'.$data["icon"].'" data-theme="a" data-role="button" href="'.$data["url"].'">'.$button.'</a>';
    } 
    $footer .= '</div></div>';
    return $footer;
}
function make_panel($page) {
    $buttons = array(
        "Logout" => array(
            "icon" => "delete",
            "url" => "javascript:logout()"
        ),
        "Motion Alerts" => array(
            "icon" => "alert",
            "url" => "",
            "alerts" => true
        ),
        "View All" => array(
        	"icon" => "grid",
        	"url" => "#allcams"
        )
    );
    if ($page == "allcams") {
    	$opts = ' data-position="right"';
    } else {
    	$opts = '';
    }
    $panel = '<div data-role="panel" id="'.$page.'-settings" data-theme="a"'.$opts.'><ul data-role="listview" data-theme="a"><li>Logged in as: '.$_SESSION["username"].'</li><li><div class="ui-grid-a"><div class="ui-block-a"><br><label for="autologin">Auto Login</label></div><div class="ui-block-b"><select name="autologin" id="'.$page.'-autologin" data-role="slider"><option value="off">Off</option><option value="on">On</option></select></div><div class="ui-block-a"><br><label for="forwardbytime">Advance By Time</label></div><div class="ui-block-b"><select name="forwardbytime" id="'.$page.'-forwardbytime" data-role="slider"><option value="off">Off</option><option value="on">On</option></select></div></div><br><p class="panelhelp">Advance by time forces the video playback controls to operate based on time rather than the displayed order</p></li>';
    foreach ($buttons as $button => $data) {
        if (isset($data["alerts"]) && $data["alerts"]) {
            $total = check_alerts();
            if ($total) {
                $count = '<span class="ui-li-count">'.$total.'</span>';
            } else {
                $count = '';
            }

            $panel .= '<li data-icon="'.$data["icon"].'"><a href="javascript:openAlertList()">'.$button.'</a>'.$count.'</li>';
            continue;
        }

    	if ($data["url"] == "close") {
    		$url = '#" data-rel="close';
    	} else {
    		$url = $data["url"];
    	}
        $panel .= '<li data-icon="'.$data["icon"].'"><a href="'.$url.'">'.$button.'</a></li>';
    }
    $panel .= '</ul></div>';
    return $panel;
}

function get_videolist() {
    global $mysql_hostname, $mysql_username, $mysql_password, $mysql_database, $sites;

    $link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
    if ($link->connect_errno) {
        die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
    }

    if (!isset($_REQUEST['camid'])) return false;

    $sitename = getCurrentSiteData("name");
    $sitedata = getCurrentSiteData();

    if (!$sitename || !$sitedata) return false;

    foreach ($sitedata["cameras"] as $camera => $cameradata) {
        if ($_REQUEST['camid'] == strtolower(preg_replace("/\s/","",$camera))) break;
    }
    
    $list = '';

    $videos_stmt = $link->prepare("SELECT id,timestamp,duration,action FROM videos WHERE camera = ? ORDER BY timestamp DESC LIMIT 60");
    $videos_stmt->bind_param("s",$camera);
    $videos_stmt->execute();

    $meta = $videos_stmt->result_metadata();

    while ($field = $meta->fetch_field()) {
        $parameters[] = &$row[$field->name];
    }

    call_user_func_array(array($videos_stmt, 'bind_result'), $parameters);

    while ($videos_stmt->fetch()) {
        foreach($row as $key => $val) {
            $x[$key] = $val;
        }
        $time = date('h:i:s A', $x['timestamp']);
        $date = date('l, M d, Y', $x['timestamp']);
        $theme = ($x["action"] > 2000) ? " data-theme='e'" : "";
        $list .= "<li data-videoday='".$date."'".$theme."><a href='javascript:preppop(\"".$sitename."\",\"".$_REQUEST['camid']."\",".$x['id'].")'><img src='img/loading.gif' data-imagesource='".$sitedata["url"]."?action=getThumbFromId&camid=".$_REQUEST["camid"]."&id=".$x['id']."' /><h2>".$time."</h2><p>".round($x["action"] / 1000,2)."s / ".round($x["duration"] / 1000,2)."s</p></a></li>";
    }

    $page = '<div data-role="page" id="videoList"><div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false"><h3>'.$camera.'</h3><a href="javascript:gohome()" data-icon="back">Back</a><a href="javascript:reverserec()" data-videoorder="false" data-icon="arrow-d">Sort</a></div><div data-role="content"><ul data-inset="true" data-role="listview" data-autodividers="true">'.$list.'</ul></div></div>';
    echo $page;
}

function get_all_alerts() {
    global $mysql_hostname, $mysql_username, $mysql_password, $mysql_database, $sites;

    $link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
    if ($link->connect_errno) {
        die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
    }

    $list = '';

    $alert_stmt = $link->prepare("SELECT id,sitename,camera,time FROM alerts WHERE seen = 0 ORDER BY id DESC LIMIT 60");
    $alert_stmt->execute();

    $meta = $alert_stmt->result_metadata();

    while ($field = $meta->fetch_field()) {
        $parameters[] = &$row[$field->name];
    }

    call_user_func_array(array($alert_stmt, 'bind_result'), $parameters);

    while ($alert_stmt->fetch()) {
        foreach($row as $key => $val) {
            $x[$key] = $val;
        }

        $time = date('h:i:s A', strtotime($x['time']));
        $date = date('l, M d, Y', strtotime($x['time']));
        $list .= '<li data-videoday="'.$date.'""><a href="javascript:prepAlertVideo(\''.$x['sitename'].'\',\''.$x['camera'].'\',\''.$x['time'].'\')"><img src="img/loading.gif" data-imagesource="?action=grab_alert_image&id='.$x['id'].'" /><h2>'.$time.'</h2><p>'.$x['camera'].' camera from '.$x['sitename'].'</p></a></li>';
    }

    $alert_stmt->close();
    $link->close();
    $page = '<div data-role="page" id="alertList"><div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false"><h3>Alerts</h3><a href="javascript:gohome()" data-icon="back">Back</a><a href="javascript:reverserec()" data-videoorder="false" data-icon="arrow-d">Sort</a></div><div data-role="content"><ul data-inset="true" data-role="listview" data-autodividers="true">' . $list . '</ul></div></div>';
    echo $page;
}

#Alert functions
function check_alerts() {
    global $mysql_hostname, $mysql_username, $mysql_password, $mysql_database;

    $link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
    if ($link->connect_errno) {
        die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
    }

    $results = $link->query("SELECT COUNT(*) AS total FROM alerts WHERE seen = 0")->fetch_assoc();
    $link->close();
    return $results['total'];
}

#Image functions
function grab_alert_image() {
    if (!isset($_REQUEST["id"])) return false;

    global $mysql_hostname, $mysql_username, $mysql_password, $mysql_database;

    $link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
    if ($link->connect_errno) {
        die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
    }

    $alert_stmt = $link->prepare("SELECT img FROM alerts WHERE id=?");
    $alert_stmt->bind_param("i",$_REQUEST["id"]);
    $alert_stmt->execute();

    $alert_stmt->bind_result($img);
    $alert_stmt->fetch();
    $alert_stmt->free_result();

    $link->close();
    header("Content-Type: image/jpeg");
    echo $img;
}

function grab_still() {
    global $sites, $currentsite;

    if (!isset($_REQUEST["camid"])) return false;

    foreach($sites as $sitename => $sitedata) {
        foreach($sitedata["cameras"] as $camera => $camdata) {
            if ($_REQUEST["camid"] == $camera && $currentsite == $sitename) {
            $camurl = insertstring($sitedata["url"], $sitedata["username"].":".$sitedata["password"]."@", "://").$camdata["url"];
            }
        }
    }

    if (!isset($camurl)) return false;
    session_write_close();

    $f = fopen($camurl,"r");
    if(!$f){
        return false;
    } else {
        $r="";
        while (substr_count($r,"Content-Length") != 2) $r.=fread($f,512);

        $start = strpos($r,"\xff");
        $end   = strpos($r,"\n--",$start)-1;
        $frame = substr("$r",$start,$end - $start);

        header("Content-type: image/jpeg");
        echo $frame;
    }

    fclose($f);
}
function getThumbFromId() {
    if (!isset($_REQUEST['camid']) || !isset($_REQUEST['id'])) return false;

    global $mysql_hostname, $mysql_username, $mysql_password, $mysql_database, $sites;

    $link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
    if ($link->connect_errno) {
        die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
    }

    $image_stmt = $link->prepare("SELECT thumbnailpath FROM videos WHERE id=?");
    $image_stmt->bind_param("i",$_REQUEST["id"]);
    $image_stmt->execute();

    $image_stmt->bind_result($imgOut);
    $image_stmt->fetch();
    $image_stmt->free_result();
    $image_stmt->close();
    $link->close();
    if (!is_file($imgOut)) $imgOut = "img/loading.gif";
    
    header("Content-type: image/jpeg");
    readfile($imgOut); 
}

function thumbLocation($file,$location = "") {
    if ($location === "") {
        if (!$sitedata = getCurrentSiteData()) return false;
        $folder = $sitedata["videostore"];
    } else {
        global $sites;
        $folder = $sites[$location]["videostore"];
    }

    $folder .= "Thumbnails/";
    $file_arr = explode("/",$file);
    $camid = strtolower(preg_replace("/\([^)]+\)|\s/","",$file_arr[count($file_arr) - 5]));
    $imgOut = $folder.$camid.".".implode(".",array_slice($file_arr,count($file_arr) - 4)).".jpg";
    return $imgOut;
}

function genThumb($file, $location = "") {
    if ($location === "") {
        if (!$sitedata = getCurrentSiteData()) return false;
        $folder = $sitedata["videostore"];
    } else {
        global $sites;
        $folder = $sites[$location]["videostore"];
    }

    $imgOut = thumbLocation($file,$location);
    $ffmpeg = "/usr/bin/ffmpeg";
    $second = "03";
    $cmd = $ffmpeg." -i \"".$file."\" -an -ss 00:00:".$second.".001 -r 1/1 -qscale 31 -s vga -y -f mjpeg \"".$imgOut."\" 2>&1";
    return `$cmd`;
}

#Video functions
function getfeed() {
    global $sites;

    if (!isset($_REQUEST['camid'])) return false;

    foreach ($sites as $sitename => $sitedata) {
        if (strpos($sitedata["url"],$_SERVER['SERVER_NAME'])) {
            $folder = $sitedata["videostore"]."Live/";
            foreach ($sitedata['cameras'] as $camera => $camdata) {
                if (strtolower(preg_replace("/\s/","",$camera)) == $_REQUEST['camid']) {
                    $file = $_REQUEST['camid'].".m3u8";
                }
            }
        }
    }
    if (!isset($folder) || !isset($file)) return false;

    session_write_close();
    $file = $folder.$file;
    if (is_file($file)) {
        header("Content-type: application/x-mpegURL");
        header("Content-Length: ".filesize($file));
        readfile($file);
    }

}
function getpart() {
    global $sites;

    if (!isset($_REQUEST['camid']) || !isset($_REQUEST['part'])) return false;

    foreach ($sites as $sitename => $sitedata) {
        if (strpos($sitedata["url"],$_SERVER['SERVER_NAME'])) {
            $folder = $sitedata["videostore"]."Live/";
        }
        foreach ($sitedata['cameras'] as $camera => $camdata) {
            if (strtolower(preg_replace("/\s/","",$camera)) == $_REQUEST['camid']) {
                $file = $_REQUEST['camid']."-".$_REQUEST['part'].".ts";
            }
        }
    }
    if (!isset($folder) || !isset($file)) return false;

    session_write_close();
    $file = $folder.$file;
    if (is_file($file)) {
        header("Content-type: video/MP2T");
        header("Content-Length: ".filesize($file));
        readfile($file);
    }
}

function alertvideo() {
    if (!isset($_REQUEST['camera']) || !isset($_REQUEST['time'])) return false;

    global $sites;

    if (!$sitedata = getCurrentSiteData()) return false;
    $folder = $sitedata["videostore"]."Cameras";

    if (!chdir($folder)) return;

    $cameras = directoryToArray(".",false,true,false);

    foreach ($cameras as $camera) {

        if (!chdir($camera)) continue;
        if (strpos($camera,$_REQUEST['camera']) === false) {
            chdir(".."); continue;
        }
        $tmp = explode(" ",$_REQUEST['time']);
        $date = implode("/",explode("-",$tmp[0]));
        $time = $tmp[1];
        $files = directoryToArray("./".$date,true,false,true,'/\.media$|\.xml$/');
        $file = $folder.substr($camera,1).substr(current(preg_grep("/.*".$time.".*/", $files)),1);
        outputmp4($file);        
    }
}
function recordedvideo() {
    if (!isset($_REQUEST['camid']) || !isset($_REQUEST['id'])) return false;

    global $mysql_hostname, $mysql_username, $mysql_password, $mysql_database, $sites;

    $link = new mysqli($mysql_hostname, $mysql_username, $mysql_password,$mysql_database);
    if ($link->connect_errno) {
        die("Failed to connect to MySQL: (" . $link->connect_errno . ") " . $link->connect_error);
    }

    $video_stmt = $link->prepare("SELECT filepath FROM videos WHERE id=?");
    $video_stmt->bind_param("i",$_REQUEST["id"]);
    $video_stmt->execute();
    $video_stmt->bind_result($file);
    $video_stmt->fetch();
    $video_stmt->free_result();
    $video_stmt->close();
    $link->close();
    
    outputmp4($file);    
}
function outputmp4($file) {
    session_write_close();
    if (is_file($file)) {
        header("Content-type: video/mp4");
        if (isset($_SERVER['HTTP_RANGE']))  {
            rangeDownload($file);
        } else {     
            header("Content-Length: ".filesize($file));
            readfile($file);
        }
    }
    exit();
}
function rangeDownload($file) {
 
    $fp = @fopen($file, 'rb');
 
    $size   = filesize($file);
    $length = $size;
    $start  = 0;
    $end    = $size - 1;
    header("Accept-Ranges: 0-$length");
    if (isset($_SERVER['HTTP_RANGE'])) { 
        $c_start = $start;
        $c_end   = $end;
        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }
        if (substr($range, 0, 1) == '-') { 
            $c_start = $size - substr($range, 1);
        }
        else { 
            $range  = explode('-', $range);
            $c_start = $range[0];
            $c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }
        $c_end = ($c_end > $end) ? $end : $c_end;
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) { 
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }
        $start  = $c_start;
        $end    = $c_end;
        $length = $end - $start + 1;
        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
    }
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $length");
 
    $buffer = 1024 * 8;
    while(!feof($fp) && ($p = ftell($fp)) <= $end) {
 
        if ($p + $buffer > $end) {
            $buffer = $end - $p + 1;
        }
        set_time_limit(0);
        echo fread($fp, $buffer);
        flush();
    }
    fclose($fp);
}

#Authentication functions
function http_authenticate($user,$pass,$pass_file='/var/cameras/htpasswd',$crypt_type='SHA'){
    if (!ctype_alnum($user)) return FALSE;
    if (!ctype_alnum($pass)) return FALSE;

    if(file_exists($pass_file) && is_readable($pass_file)){
        if($fp=fopen($pass_file,'r')){
            while($line=fgets($fp)){
                $line=preg_replace('`[\r\n]$`','',$line);
                list($fuser,$fpass)=explode(':',$line);
                if($fuser==$user){
                    switch($crypt_type){
                        case 'DES':
                            $salt=substr($fpass,0,2);
                            $test_pw=crypt($pass,$salt);
                            break;
                        case 'PLAIN':
                            $test_pw=$pass;
                            break;
                        case 'SHA':
                            $test_pw=base64_encode(sha1($pass));
                            break;
                        case 'MD5':
                            $test_pw=md5($pass);
                            break;
                        default:
                            fclose($fp);
                            return FALSE;
                    }
                    if($test_pw == $fpass){
                        fclose($fp);
                        return TRUE;
                    }else{
                        fclose($fp);
                        return FALSE;
                    }
                }
            }
            fclose($fp);
        }else{
            return FALSE;
        }
    }else{
        return FALSE;
    }
}
function gettoken() {
    if (is_auth() && isset($_SESSION["token"])) {
        echo $_SESSION["token"];
        return;
    }
    login("token");
}

function login($tosend = "cameras") {
    global $denied, $sites, $webtitle, $private_key;

    $starttime = explode(' ', microtime()); 
    $starttime = $starttime[1] + $starttime[0]; 
    $cache = "/var/cameras/cache";
    $auth = base64_encode(hash("sha256",$_SERVER['REMOTE_ADDR']).hash("sha256",$starttime).hash("sha256",$_POST['username']));
    if (!http_authenticate($_POST['username'],$_POST['password'])) {
        echo 0; 
        exit();
    } else {
        if (isset($_POST['remember']) && $_POST['remember'] == "true") {
            $fh = fopen($cache, 'a+');
            fwrite($fh, $starttime." ".$auth." ".$_POST['username']."\n");
            fclose($fh);
            $_SESSION['sendtoken'] = true;
        }
        $_SESSION['token'] = $auth;
        $_SESSION['isauth'] = 1;
        $_SESSION['username'] = $_POST['username'];
        
        if ($tosend == "token") {
            if (isset($_SESSION["token"])) echo $_SESSION["token"];
        } else {
           include_once("cameras.php");
        }
    }
}
function remove_token() {
    $cache = "/var/cameras/cache";
    $hashs = file($cache);
    if (isset($_SESSION['token']) && count($hashs) !== 0) {
        $i = 0;
        foreach ($hashs as $hash){
            $hash = explode(" ",$hash);
            $hash[1] = str_replace("\n", "", $hash[1]);
            if ($hash[1] === $_SESSION['token']) {
                delLineFromFile($cache, $i);
                unset($_SESSION['token']);
            }
            $i++;
        }
    }
    unset($hashs);
}
function logout() {
    global $denied, $base_url;
    remove_token();
    $_SESSION = array();
    session_destroy();
    header('Location: '.$base_url);
}
function check_localstorage($token) {
    $starttime = explode(' ', microtime()); 
    $starttime = $starttime[1] + $starttime[0]; 
    $endtime = $starttime - 2592000;
    $cache = "/var/cameras/cache";
    $hashs = file($cache);
    if (count($hashs) !== 0) {
        $i = 0;
        foreach ($hashs as $hash){
            $hash = explode(" ",$hash);
            $hash[2] = str_replace("\n", "", $hash[2]);
            if ($hash[0] <= $endtime) {
                delLineFromFile($cache, $i);
                return FALSE;
            }
            if ($token === $hash[1]) {
                $_SESSION['token'] = $token;
                $_SESSION['isauth'] = 1;
                $_SESSION['username'] = $hash[2];
                return TRUE;
            }
            $i++;
        }
    }

    return FALSE;
}
function sso() {    
    global $private_key;
        
    $username = $_GET['username'];  
    $received_signature = $_GET['sig'];  
    $computed_signature = base64_encode(hash_hmac('sha256', $username, $private_key, TRUE));

    if($computed_signature == $received_signature) {
        $_SESSION['username'] = $_GET['username'];
        $_SESSION['isauth'] = 1;
        return TRUE;
    }
    return FALSE;
}
function is_auth() {
    is_ssl();
    if (isset($_SESSION['isauth']) && $_SESSION['isauth'] === 1) { return TRUE; }
    return FALSE;   
}
function is_ssl() {
    if(empty($_SERVER['HTTPS'])) {
        $newurl = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        header("Location: ".$newurl);
        exit();
    }
    return TRUE;
}
function checktoken() {
    global $webtitle, $sites, $private_key;

    if (check_localstorage($_POST['token'])) {
        include_once("cameras.php");
    } else {
        echo 0;
    }
    exit();
}

#Supplemental functions
function directoryToArray($directory, $recursive = true, $listDirs = false, $listFiles = true, $exclude = '') {
    $arrayItems = array();
    $skipByExclude = false;
    $handle = opendir($directory);
    if ($handle) {
        while (false !== ($file = readdir($handle))) {
        preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$/iu", $file, $skip);
        if($exclude){
            preg_match($exclude, $file, $skipByExclude);
        }
        if (!$skip && !$skipByExclude) {
            if (is_dir($directory. DIRECTORY_SEPARATOR . $file)) {
                if($recursive) {
                    $arrayItems = array_merge($arrayItems, directoryToArray($directory. DIRECTORY_SEPARATOR . $file, $recursive, $listDirs, $listFiles, $exclude));
                }
                if($listDirs){
                    $file = $directory . DIRECTORY_SEPARATOR . $file;
                    $arrayItems[] = $file;
                }
            } else {
                if($listFiles){
                    $file = $directory . DIRECTORY_SEPARATOR . $file;
                    $arrayItems[] = $file;
                }
            }
        }
    }
    closedir($handle);
    }
    return $arrayItems;
}
function formatSeconds($seconds) {
  $hours = 0;
  $milliseconds = str_replace( "0.", '', $seconds - floor($seconds));

  if ( $seconds > 3600 ) {
    $hours = floor( $seconds / 3600 );
  }
  $seconds = $seconds % 3600;


  return str_pad( $hours, 2, '0', STR_PAD_LEFT )
       . gmdate( ':i:s', $seconds )
       . ($milliseconds ? ".$milliseconds" : '');
}
function delLineFromFile($fileName, $lineNum){
    $arr = file($fileName);
    $lineToDelete = $lineNum;
    unset($arr["$lineToDelete"]);
    $fp = fopen($fileName, 'w+');
    foreach($arr as $line) { fwrite($fp,$line); }
    fclose($fp);
    return TRUE;
}
function getCurrentSiteData($opt = "data") {
    global $sites;
    foreach ($sites as $sitename => $sitedata) {
        if (strpos($sitedata["url"],$_SERVER['SERVER_NAME'])) {
            if ($opt == "name") return $sitename;
            return $sitedata;
        }
    }

    return false;
}

function insertstring($string, $new, $pos) {
   return  str_replace($pos, $pos.$new ,$string);
}
?>