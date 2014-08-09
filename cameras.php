<?
#Start session
if(!isset($_SESSION)) session_start();

if(!defined('Cameras')) {

    #Tell main we are calling it
    define('Cameras', TRUE);

    #Required files
    require_once "main.php";
}

#Redirect if not authenticated or grabbing page directly
if (!is_auth() || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {header('Location: '.$base_url); exit();}
?>
<script><?php include_once("js/main.js.php"); ?></script>

<div data-role="page" id="cams">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
        <a data-icon="bars" data-iconpos="notext" href="#cams-settings"></a>
        <h3><?php echo $webtitle; ?></h3>
        <a data-icon="grid" data-iconpos="notext" href="#allcams" data-transition="slide"></a>
    </div>
    <div data-role="content">
        <p style="text-align:center" id="tip"></p>
        <ul data-role="listview" data-inset="true">
            <?php echo make_list(); ?>
        </ul>
    </div>
    <?php echo make_panel("cams"); ?>

</div>

<div data-role="page" id="allcams" data-theme="a">
    <div data-theme="a" data-role="header" data-position="fixed" data-tap-toggle="false">
    	<a data-icon="home" data-iconpos="notext" href="#cams" data-transition="slide" data-direction="reverse"></a>
    	<h3><?php echo $webtitle; ?></h3>
        <a data-icon="bars" data-iconpos="notext" href="#allcams-settings"></a>
    </div>
    <div data-role="content" class="gridlist">
        <?php echo make_all_cams(); ?>
    </div>
    <?php echo make_panel("allcams"); ?>
</div>