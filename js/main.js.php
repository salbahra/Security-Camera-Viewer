<?php
#Start session
if(!isset($_SESSION)) session_start();

if(!defined('Cameras')) {

    #Tell main we are calling it
    define('Cameras', TRUE);

    #Required files
    require_once "../main.php";
    
    header("Content-type: application/x-javascript");
}

#Kick if not authenticated
if (!is_auth()) {exit();}

#Echo token so browser can cache it for automatic logins
if (isset($_SESSION['sendtoken']) && $_SESSION['sendtoken']) { echo "localStorage.setItem('token', '".$_SESSION['token']."');\n"; $_SESSION['sendtoken'] = false; }

$siteurls = array();
foreach($sites as $sitename => $sitedata) {
    $siteurls[$sitename] = $sitedata['url'];
}
echo "var sites = ".json_encode($siteurls).";\n";
?>
$(document).one("pageinit","#cams", function(){
    $.mobile.hidePageLoadingMsg();
    $.mobile.changePage($("#cams"));
});
$(document).on("swiperight swipeleft", function(e){
    eventtype = e.type;
    page = $(e.target).closest(".ui-page-active");
    pageid = page.attr("id");
    panel = page.find("[id$=settings]");

    if (panel.length != 0 && !panel.hasClass("ui-panel-closed")) {
        return false;
    }

    if (eventtype == "swipeleft" && pageid == "cams") {
        $.mobile.changePage($("#allcams"), {transition: "slide"});
    } else if (eventtype == "swiperight" && pageid == "allcams") {
        $.mobile.changePage($("#cams"), {transition: "slide", reverse: true});
    } else {
        if (panel.length == 0) return;
        panel.panel("open");
    }
});

$("select[data-role='slider']").change(function(){
    var slide = $(this);
    var type = this.name;
    var pageid = slide.closest(".ui-page-active").attr("id");
    var changedTo = slide.val();
    if(window.sliders[type]!==changedTo){
        if (changedTo=="on") {
            if (type === "autologin") {
                if (localStorage.getItem("token") != null) return;
                $("#login form").attr("action","javascript:grab_token('"+pageid+"')");
                $.mobile.changePage($("#login"));
            } else if (type === "forwardbytime") {
                seen = localStorage.getItem("seenwarning");
                if (typeof(seen) === "undefined" || seen !== "true") {
                    localStorage.setItem("seenwarning","true");
                    $("#container").append("<div data-role='popup' id='warning'><p style='max-width:200px'>When this option is enabled the direction of the video controls (previous/next) is always in relation to time rather than the list order</p></div>");
                    $("#warning").popup({history: false}).popup("open",{positionTo: slide});
                }
                localStorage.setItem("forwardbytime","true");
            }
        } else {
            localStorage.removeItem(typeToKey(type));                
        }
    }
    window.sliders["forwardbytime"] = $(this).val();
});

$("#cams, #allcams").on("pagebeforeshow",function(e,data){
    var newpage = e.target.id;
    $("#"+newpage+" div[data-role='footer'] a[href='#"+newpage+"']").addClass("ui-btn-active");
     
    if (newpage == "cams") {
        new_tip();
    }
});

$(document).on('pageinit', function (e, data) {
    var newpage = e.target.id;

    if (newpage == "cams" || newpage == "allcams" || newpage == "videoList" || newpage == "alertList") {
        currpage = $(e.target);

        currpage.find("a[data-rel=back]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            history.back();
        })
        currpage.find("a[data-rel=close]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            $(".ui-page-active [id$=settings]").panel("close");
        })
        currpage.find("a[href='#"+currpage.attr('id')+"-settings']").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            highlight(this);
            $(".ui-page-active [id$=settings]").panel("open");
        });
        currpage.find("a[href^=javascript\\:]").bind('vclick', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
            var func = $(this).attr("href").split("javascript:")[1];
            if (func.substr(0,7) == "preppop" || func.substr(0,14) == "prepAlertVideo") {
                $(this).addClass("ui-btn-active");
            } else if (func.substr(0,13) == "openVideoList" || func.substr(0,13) == "openAlertList") { 
                $.mobile.showPageLoadingMsg();
            } else {
                highlight(this);
            }
            eval(func);
        });
    }
    if (newpage == "videoList" || newpage == "alertList") {
        $.mobile.hidePageLoadingMsg();
        $("#"+newpage+" ul").listview({
            autodividers: true,
            autodividersSelector: function (li) {
                var out = li.data("videoday");
                return out;
            }
        }).listview("refresh");
    }
    if (newpage == "videoList") $.mobile.changePage($("#videoList"), {transition: "slidefade"});
});

$(document).on("pageshow",function(e,data){
    newpage = e.target.id;

    if (newpage == "allcams") {
        correct_img_height();
        $("img[data-videosource]").each(function(){
            this.src = $(this).data("videosource");
        });
    }

    if (newpage == "videoList" || newpage == "alertList") {
        thumbs = $("#"+newpage+" img[data-imagesource]");
        thumbs.waypoint({
            context: $("#"+newpage+" div[data-role='content']"),
            offset: "100%",
            handler: function(){
                this.src = $(this).data("imagesource");
            }
        })
    }

    if (newpage == "allcams" || newpage == "cams") {
        check_auto($("#"+newpage+" select[data-role='slider']"));
    }
});

$(window).on('throttledresize', function(e){
    $.waypoints("refresh");
    if ($(".ui-page-active").attr("id") == "allcams") {
        setTimeout(function(){ correct_img_height(); }, 200);
    }
    var video = $("video");
    if (video.length) {
        setTimeout(function(){
        	var wh = getDimen();
        	video.attr("width",wh[0]+"px").attr("height",wh[1]+"px");
        }, 200);
    }
});

$(document).on("pagehide",function(e,data){
    var oldpage = e.target.id;
    
    if (oldpage == "cams" || oldpage == "allcams") {
        $("#"+oldpage+" div[data-role='footer'] a[href='#"+oldpage+"']").removeClass("ui-btn-active");
    }

    if (oldpage == "videoList" || oldpage == "alertList") {
        $("#"+oldpage+" img[data-imagesource]").waypoint("destroy");
        $(e.target).remove();
    }
});
$(document).on("pagebeforehide","#allcams",function(e,data){
	window.stop();
	$("img[data-videosource]").each(function(){
		this.src = "img/loading.gif";
	});
});
function check_auto(sliders){
    if (typeof(window.sliders) !== "object") window.sliders = [];
    sliders.each(function(i){
        var type = this.name;
        var item = typeToKey(type);
        if (!item) return;
        if (localStorage.getItem(item) != null) {
            window.sliders[type] = "on";
            $(this).val("on").slider("refresh");
        } else {
            window.sliders[type] = "off";
            $(this).val("off").slider("refresh");
        }
    })
}

function typeToKey(type) {
    if (type == "autologin") {
        return "token";            
    } else if (type == "forwardbytime") {
        return "forwardbytime";
    } else {
        return false;
    }
}

function highlight(button) {
    $(button).addClass("ui-btn-active").delay(150).queue(function(next){
        $(this).removeClass("ui-btn-active");
        next();
    });
}

function correct_img_height() {
    $.each($(".gridlist ul").children(), function(key,val){
        cam = $(val);
        cam.height( ( 480 * ( cam.width() / 640 ) ) + "px" )
    });
}

function grab_token(pageid){
    $.mobile.showPageLoadingMsg();
    var parameters = "action=gettoken&username=" + $('#username').val() + "&password=" + $('#password').val() + "&remember=" + $('#remember').is(':checked');
    if (!$('#remember').is(':checked')) {
        $("#"+pageid+"-autologin").val("off").slider("refresh");
        window.sliders["autologin"] = "off";
        $.mobile.changePage($("#"+pageid));
        return;
    }
    $.post("index.php",parameters,function(reply){
        $.mobile.hidePageLoadingMsg();
        if (reply == 0) {
            showerror("Invalid Login");
            $.mobile.changePage($("#"+pageid));
        } else if (reply === "") {
            $("#"+pageid+"-autologin").val("off").slider("refresh");
            window.sliders["autologin"] = "off";
            $.mobile.changePage($("#"+pageid));
        } else {
            localStorage.setItem('token',reply);
            $.mobile.changePage($("#"+pageid));
        }
    }, "text");
    $("#login form").attr("action","javascript:dologin()");
}

function reverserec() {
    var mypage = $('#videoList, #alertList');
    var mylist = mypage.find("ul");
    var listitems = mylist.children('li').get();
    listitems.reverse();
    $.each(listitems, function(idx, itm) { mylist.append(itm); });
    mylist.listview("refresh");
    $.waypoints("refresh");
    icon = mypage.find('a[href="javascript:reverserec()"]');
    dir = icon.data('videoorder');
    if (dir) {
        icon.data('icon','arrow-d');
        icon.find('.ui-icon').addClass("ui-icon-arrow-d").removeClass("ui-icon-arrow-u");
    } else {
        icon.data('icon','arrow-u');
        icon.find('.ui-icon').addClass("ui-icon-arrow-u").removeClass("ui-icon-arrow-d");
    }
    icon.data('videoorder',!dir);
}
function getDimen(){
    if ( $(window).width() >= 670 && $(window).height() >= 510 ) {
        width = 640;
        height = 480;
    } else if ( $(window).width() > $(window).height() ) {
        height = ($(window).height() * 0.8) - 60;
        width = (640 * (height / 480)) - 60;
    } else {
        width = ($(window).width() * 0.8) - 60;
        height = (640 * (width / 480)) - 60;        
    }
    return [width, height];
}
function new_tip() {
    var tips = [
        "To view a live camera feed just tap on the camera name. For recent clips, tap the arrow to the right of the camera name.",
        "Swipe to the right to open the settings panel or to the left to open the all camera view."
    ];
    var i = Math.floor((Math.random()*tips.length));
    $("#tip").html(tips[i]);
}

function getNextVideo(prev) {
	if (typeof(prev) !== "boolean") prev = false;
    var $page = $(".ui-page-active");
    var icon = $page.find('a[href="javascript:reverserec()"]');
    var dir = icon.data('videoorder');
    
    forwardbytime = localStorage.getItem('forwardbytime');
    if (!dir && typeof(forwardbytime) !== "undefined" && forwardbytime) {
        prev = !prev;
    }
    
    var video = $("video")[0];
    var currentSrc = video.src;
    var tmp = currentSrc.split("=");

    var $list = $page.find("ul");
    $anchor = $list.find(".ui-btn-active");
    $listitems = $list.children("li:not(li.ui-li-divider)");
    $item = $anchor.closest("li:not(li.ui-li-divider)");
    var currPos = $listitems.index($item);
    var total = $listitems.length;
    console.log($item);
    if ((!prev && currPos >= total) || (prev && currPos === 0)) {
        $("#popupVideo").popup("close").remove();
        return;
    }
    if (prev)
        newPos = currPos - 1;
    else
        newPos = currPos + 1;
    if ($page.attr("id") == "videoList") {
        tmp[tmp.length - 1] = $($listitems.get(newPos)).find("a").attr("href").split(",")[2].slice(0,-1);
        var newSrc = tmp.join("=");
    } else if ($page.attr("id") == "alertList") {
        var newData = $($listitems.get(newPos)).find("a").attr("href");
        var reg = /javascript:prepAlertVideo\('([\w|\s]+)','([\w|\s]+)','([\w|\-\s]+)'\)/g;
        var x = reg.exec(newData);
        var newSrc = window.sites[x[1]]+"?action=alertvideo&camera="+x[2]+"&time="+x[3];
    }
    $anchor.removeClass("ui-btn-active");
    $($listitems.get(newPos)).find("a").addClass("ui-btn-active");
    video.src = newSrc;
    video.load();
    video.play();
}
function playPause() {
    var video = $('video')[0];
    if (video.paused)
        video.play();
    else
        video.pause();
}
function fastForward(speed) {
    if (typeof(speed) === "undefined") {
        if (window.videospeed !== undefined) {
            clearTimeout(window.videospeed);
            delete window.videospeed;
            $(this).removeClass("ui-btn-active");
            return;
        }
        $(this).delay(300).queue(function(next){
    		$(this).addClass("ui-btn-active");
    		next();
		});
        speed = 2;
    }

    video = $("video")[0];

    if (video === undefined || video.paused) {
        if (window.videospeed !== undefined) {
            clearTimeout(window.videospeed);
            delete window.videospeed;
        }
        return;
    }

    total = video.duration;
    now = video.currentTime;

    if ((now+speed)<total) {
        video.currentTime = now+speed;
        video.play();
    }

    if (window.videospeed !== undefined) clearTimeout(window.videospeed);
    window.videospeed = setTimeout(fastForward,1000,speed);
}
function showVideo(url) {
    $.mobile.showPageLoadingMsg();
    var wh = getDimen();

    $('#container').append("<div data-role='popup' id='popupVideo' data-overlay-theme='a' data-theme='d' data-tolerance='30' class='ui-content'><video id='player1' width='"+wh[0]+"px' height='"+wh[1]+"px' src='"+url+"' controls /></div>");
    $popup = $("#popupVideo");
    $popup.bind({
        popupafteropen: function(e,ui){
            $("#popupVideo-screen").height( $(document).height() );
            $("video")[0].play();
        },        
        popupafterclose: function(e,ui){
            $("#videoList .ui-btn-active, #alertList .ui-btn-active").removeClass("ui-btn-active");
            setTimeout(function() {
                $("#popupVideo").remove();
            }, 100)
        }
    });

    $.mobile.hidePageLoadingMsg();
    $popup.popup({history: false}).popup("open");
}

function addVideoControls(){
    $popup = $("#popupVideo");
    $popup.append("<div class='video-controls' data-role='controlgroup' data-corners='false' data-mini='true' data-type='horizontal'><a data-mini='true' data-role='button' href='javascript:getNextVideo(true)'>Prev</a><a data-mini='true' data-role='button' href='javascript:fastForward()'>Fast Forward</a><a data-mini='true' data-role='button' href='javascript:getNextVideo()'>Next</a></div>");
    $controls = $popup.find(".video-controls");
    $controls.find("a").button();
    $controls.controlgroup();
}

function preppop(sitename,camid,key) {
    showVideo(window.sites[sitename]+"?action=recordedvideo&camid="+camid+"&id="+key);
    $("video").bind("ended", getNextVideo);
    addVideoControls();
}

function prepAlertVideo(sitename,camera,time) {
    showVideo(window.sites[sitename]+"?action=alertvideo&camera="+camera+"&time="+time);
    $("video").bind("ended", getNextVideo);
    addVideoControls();
}

function preplive(sitename,camid) {
    showVideo(window.sites[sitename]+"?action=getfeed&camid="+camid);    
}
function logout(){
    if (confirm('Are you sure you want to logout?')) {
        $.get("index.php", "action=logout",function(){
            localStorage.removeItem('token');
            $("#container div[data-role='page']:not('.ui-page-active')").remove();
            $('.ui-page-active').one("pagehide",function(){
                $(this).remove();
            })
            $.mobile.changePage($("#login"));
        });
    }
}
function openAlertList(){
    $(document).one('pageload',function(evt,data){ 
        $.mobile.changePage($(data.page), {transition: "slidefade"}); 
    });
    $.mobile.loadPage("index.php?action=get_all_alerts",{"pageContainer":$("#container")});
}

function openVideoList(sitename, camname) {
    if ($("#videoList").length != 0) return;
    camid = camname.toLowerCase().replace(/\s+/g, '');
    $.ajax({
        cache: false,
        xhrFields: {withCredentials: true},
        crossDomain: true,
        url: window.sites[sitename],
        data: "action=get_videolist&camid="+camid,
        success: function(data){
            if (data == "") return;
            $("#container").append(data);
            $("#videoList").page();
        }
    });
}
function gohome() {
    $.mobile.changePage($('#cams'), {reverse: true, transition: "slidefade"});
}