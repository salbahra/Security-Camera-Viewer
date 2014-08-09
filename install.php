<?php
if (file_exists("config.php")) header("Location: index.php");

if (isset($_REQUEST['action']) && $_REQUEST['action'] == "new_config" && !file_exists("config.php")) {
    new_config();
    exit();
}

#New config setup
function new_config() {
    $config = "<?php\n";
    $needed = array("webtitle","private_key","mysql_hostname","mysql_username","mysql_password","mysql_database","imap_hostname","imap_username","imap_password","currentsite","pass_file","cache_file","vlc_file");
    foreach ($needed as $key) {
        if (!isset($_REQUEST[$key])) fail();
        $data = $_REQUEST[$key];
        if ($key == "pass_file") {
            if (!isset($_REQUEST["username"]) || !isset($_REQUEST["password"])) fail();
            $file = fopen($data, 'w');
            if (!$file) {
                fail();
            } else {
                $r = fwrite($file,$_REQUEST["username"].":".base64_encode(sha1($_REQUEST["password"])));
                if (!$r) fail();
                fclose($file);
            }
        }
        if ($key == "cache_file" || $key == "vlc_file") make_file($data);
        if ($key == "timezone") {
            $config .= "date_default_timezone_set('".$data."');\n";
        } else {
            $config .= "$".$key." = '".$data."';\n";            
        }
    }
    $file = fopen("config.php", 'w');
    if (!$file) fail();
    $r = fwrite($file,$config."?>");
    if (!$r) fail();

    $output = shell_exec('crontab -l');
    file_put_contents('/tmp/crontab.txt', $output.'* * * * * php '.dirname(__FILE__).'/poller.php >/dev/null 2>&1'.PHP_EOL);
    exec('crontab /tmp/crontab.txt');

    echo 1;
}

function make_file($data) {
    $file = fopen($data, "w");
    if (!$file) fail();
    fclose($file);    
}

function fail() {
    echo 0;
    exit();
}

?>

<!DOCTYPE html>
<html>
	<head>
    	<title>New Install</title> 
        <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
        <meta name="viewport" content="initial-scale=1.0,user-scalable=no,maximum-scale=1" media="(device-height: 568px)" />
        <meta content="yes" name="apple-mobile-web-app-capable">
        <meta name="apple-mobile-web-app-title" content="Security Viewer">
        <link rel="apple-touch-icon" href="img/icon.png">
    	<link rel="stylesheet" href="css/jquery.mobile-1.3.0.min.css" />
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script src="js/jquery.mobile-1.3.0.min.js"></script>
        <script>
            function showerror(msg) {
                // show error message
                $.mobile.loading( 'show', {
                    text: msg,
                    textVisible: true,
                    textonly: true,
                    theme: 'a'
                });
            }
            function submit_config() {
                $.mobile.showPageLoadingMsg()
                $.get("install.php","action=new_config&"+$("#options").find(":input").serialize(),function(data){
                    if (data == 1) {
                        $.mobile.hidePageLoadingMsg()
                        showerror("Settings have been saved. Please wait while your redirected to the login screen!")
                        setTimeout(function(){location.reload()},2500);
                    } else {
                        $.mobile.hidePageLoadingMsg()
                        showerror("Settings have NOT been saved. Check folder permissions and file paths then try again.")
                        setTimeout(function(){$.mobile.loading('hide')}, 2500);                    
                    }
                })
            }
        </script>
    </head> 
    <body>
        <div data-role="page" id="install" data-close-btn="none">
        	<div data-role="header" data-position="fixed">
                <h1>New Install</h1>
                <a href="javascript:submit_config()" class="ui-btn-right">Submit</a>
           </div>
        	<div data-role="content">
                <form action="javascript:submit_config()" method="post" id="options">
                    <ul data-inset="true" data-role="listview">
                        <li data-role="list-divider">Add New User</li>
                        <li>
                            <div data-role="fieldcontain">
                                <label for="username">Username:</label>
                                <input type="text" name="username" id="username" value="" />
                                <label for="password">Password:</label>
                                <input type="password" name="password" id="password" value="" />
                            </div>
                        </li>
                    </ul>
                    <ul data-inset="true" data-role="listview">
                        <li data-role="list-divider">Intial Configuration</li>
                        <li>
                            <div data-role="fieldcontain">
                                <label for="webtitle">Site Title:</label>
                                <input type="text" name="webtitle" id="webtitle" value="Security Viewer" />
                                <label for="currentsite">Current Site Name:</label>
                                <input type="text" name="currentsite" id="currentsite" value="Home" />
                                <label for="timezone">Timezone:</label>
                                <input type="text" name="timezone" id="timezone" value="US/Central" />
                                <label for="pass_file">Pass File Location:</label>
                                <input type="text" name="pass_file" id="pass_file" value="/var/www/security/.htpasswd" />
                                <label for="cache_file">Cache File Location:</label>
                                <input type="text" name="cache_file" id="cache_file" value="/var/www/security/.cache" />
                                <label for="vlc_file">VLC File Location:</label>
                                <input type="text" name="vlc_file" id="vlc_file" value="/var/www/security/feeds.vlm" />
                            </div>
                        </li>
                    </ul>
                    <ul data-inset="true" data-role="listview">
                        <li data-role="list-divider">MySQL Configuration</li>
                        <li>
                            <div data-role="fieldcontain">
                                <label for="mysql_hostname">Hostname:</label>
                                <input type="text" name="mysql_hostname" id="mysql_hostname" value="localhost" />
                                <label for="mysql_username">Username:</label>
                                <input type="text" name="mysql_username" id="mysql_username" value="" />
                                <label for="mysql_password">Password:</label>
                                <input type="password" name="mysql_password" id="mysql_password" value="" />
                                <label for="mysql_hostname">Database:</label>
                                <input type="text" name="mysql_database" id="mysql_database" value="security" />
                            </div>
                        </li>
                    </ul>
                    <ul data-inset="true" data-role="listview">
                        <li data-role="list-divider">IMAP Configuration</li>
                        <li>
                            <div data-role="fieldcontain">
                                <label for="imap_hostname">Hostname:</label>
                                <input type="text" name="imap_hostname" id="imap_hostname" value="{imap.gmail.com:993/imap/ssl}INBOX" />
                                <label for="imap_username">Username:</label>
                                <input type="text" name="imap_username" id="imap_username" value="alert@gmail.com" />
                                <label for="imap_password">Password:</label>
                                <input type="password" name="imap_password" id="imap_password" value="" />
                            </div>
                        </li>
                    </ul>
                    <ul data-inset="true" data-role="listview">
                        <li data-role="list-divider">Camera Configuration</li>
                        <li>
                            <div data-role="fieldcontain">
                                <label for="camera_name-1">Camera Name:</label>
                                <input type="text" name="camera_name-1" id="camera_name-1" value="Camera 1" />
                                <label for="camera_feed-1">Camera RTSP Source Feed:</label>
                                <input type="text" name="camera_feed-1" id="camera_feed-1" value="rtsp://admin:password@192.168.1.100:554/LowResolutionVideo" />
                                <label for="camera_index-1">Index Path:</label>
                                <input type="text" name="camera_index-1" id="camera_index-1" value="" />
                            </div>
                        </li>
                    </ul>
                    <input type="submit" value="Submit" />
                </form>
            </div>
        </div>
    </body>
</html>
