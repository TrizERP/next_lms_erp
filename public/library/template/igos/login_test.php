
<script type="text/javascript">

//** Smooth Navigational Menu- By Dynamic Drive DHTML code library: http://www.dynamicdrive.com
//** Script Download/ instructions page: http://www.dynamicdrive.com/dynamicindex1/ddlevelsmenu/
//** Menu created: Nov 12, 2008

//** Dec 12th, 08" (v1.01): Fixed Shadow issue when multiple LIs within the same UL (level) contain sub menus: http://www.dynamicdrive.com/forums/showthread.php?t=39177&highlight=smooth

//** Feb 11th, 09" (v1.02): The currently active main menu item (LI A) now gets a CSS class of ".selected", including sub menu items.

//** May 1st, 09" (v1.3):
//** 1) Now supports vertical (side bar) menu mode- set "orientation" to 'v'
//** 2) In IE6, shadows are now always disabled

//** July 27th, 09" (v1.31): Fixed bug so shadows can be disabled if desired.
//** Feb 2nd, 10" (v1.4): Adds ability to specify delay before sub menus appear and disappear, respectively. See showhidedelay variable below

//** Dec 17th, 10" (v1.5): Updated menu shadow to use CSS3 box shadows when the browser is FF3.5+, IE9+, Opera9.5+, or Safari3+/Chrome. Only .js file changed.

var ddsmoothmenu={

//Specify full URL to down and right arrow images (23 is padding-right added to top level LIs with drop downs):
arrowimages: {down:['downarrowclass', 'down.gif', 23], right:['rightarrowclass', 'right.gif']},
transition: {overtime:300, outtime:300}, //duration of slide in/ out animation, in milliseconds
shadow: {enable:true, offsetx:5, offsety:5}, //enable shadow?
showhidedelay: {showdelay: 100, hidedelay: 200}, //set delay in milliseconds before sub menus appear and disappear, respectively

///////Stop configuring beyond here///////////////////////////

detectwebkit: navigator.userAgent.toLowerCase().indexOf("applewebkit")!=-1, //detect WebKit browsers (Safari, Chrome etc)
detectie6: document.all && !window.XMLHttpRequest,
css3support: window.msPerformance || (!document.all && document.querySelector), //detect browsers that support CSS3 box shadows (ie9+ or FF3.5+, Safari3+, Chrome etc)

getajaxmenu:function($, setting){ //function to fetch external page containing the panel DIVs
	var $menucontainer=$('#'+setting.contentsource[0]) //reference empty div on page that will hold menu
	$menucontainer.html("Loading Menu...")
	$.ajax({
		url: setting.contentsource[1], //path to external menu file
		async: true,
		error:function(ajaxrequest){
			$menucontainer.html('Error fetching content. Server Response: '+ajaxrequest.responseText)
		},
		success:function(content){
			$menucontainer.html(content)
			ddsmoothmenu.buildmenu($, setting)
		}
	})
},


buildmenu:function($, setting){
	var smoothmenu=ddsmoothmenu
	var $mainmenu=$("#"+setting.mainmenuid+">ul") //reference main menu UL
	$mainmenu.parent().get(0).className=setting.classname || "ddsmoothmenu"
	var $headers=$mainmenu.find("ul").parent()
	$headers.hover(
		function(e){
			$(this).children('a:eq(0)').addClass('selected')
		},
		function(e){
			$(this).children('a:eq(0)').removeClass('selected')
		}
	)
	$headers.each(function(i){ //loop through each LI header
		var $curobj=$(this).css({zIndex: 100-i}) //reference current LI header
		var $subul=$(this).find('ul:eq(0)').css({display:'block'})
		$subul.data('timers', {})
		this._dimensions={w:this.offsetWidth, h:this.offsetHeight, subulw:$subul.outerWidth(), subulh:$subul.outerHeight()}
		this.istopheader=$curobj.parents("ul").length==1? true : false //is top level header?
		$subul.css({top:this.istopheader && setting.orientation!='v'? this._dimensions.h+"px" : 0})
		$curobj.children("a:eq(0)").css(this.istopheader? {paddingRight: smoothmenu.arrowimages.down[2]} : {}).append( //add arrow images
			
		)
		if (smoothmenu.shadow.enable && !smoothmenu.css3support){ //if shadows enabled and browser doesn't support CSS3 box shadows
			this._shadowoffset={x:(this.istopheader?$subul.offset().left+smoothmenu.shadow.offsetx : this._dimensions.w), y:(this.istopheader? $subul.offset().top+smoothmenu.shadow.offsety : $curobj.position().top)} //store this shadow's offsets
			if (this.istopheader)
				$parentshadow=$(document.body)
			else{
				var $parentLi=$curobj.parents("li:eq(0)")
				$parentshadow=$parentLi.get(0).$shadow
			}
			this.$shadow=$('<div class="ddshadow'+(this.istopheader? ' toplevelshadow' : '')+'"></div>').prependTo($parentshadow).css({left:this._shadowoffset.x+'px', top:this._shadowoffset.y+'px'})  //insert shadow DIV and set it to parent node for the next shadow div
		}
		$curobj.hover(
			function(e){
				var $targetul=$subul //reference UL to reveal
				var header=$curobj.get(0) //reference header LI as DOM object
				clearTimeout($targetul.data('timers').hidetimer)
				$targetul.data('timers').showtimer=setTimeout(function(){
					header._offsets={left:$curobj.offset().left, top:$curobj.offset().top}
					var menuleft=header.istopheader && setting.orientation!='v'? 0 : header._dimensions.w
					menuleft=(header._offsets.left+menuleft+header._dimensions.subulw>$(window).width())? (header.istopheader && setting.orientation!='v'? -header._dimensions.subulw+header._dimensions.w : -header._dimensions.w) : menuleft //calculate this sub menu's offsets from its parent
					if ($targetul.queue().length<=1){ //if 1 or less queued animations
						$targetul.css({left:menuleft+"px", width:header._dimensions.subulw+'px'}).animate({height:'show',opacity:'show'}, ddsmoothmenu.transition.overtime)
						if (smoothmenu.shadow.enable && !smoothmenu.css3support){
							var shadowleft=header.istopheader? $targetul.offset().left+ddsmoothmenu.shadow.offsetx : menuleft
							var shadowtop=header.istopheader?$targetul.offset().top+smoothmenu.shadow.offsety : header._shadowoffset.y
							if (!header.istopheader && ddsmoothmenu.detectwebkit){ //in WebKit browsers, restore shadow's opacity to full
								header.$shadow.css({opacity:1})
							}
							header.$shadow.css({overflow:'', width:header._dimensions.subulw+'px', left:shadowleft+'px', top:shadowtop+'px'}).animate({height:header._dimensions.subulh+'px'}, ddsmoothmenu.transition.overtime)
						}
					}
				}, ddsmoothmenu.showhidedelay.showdelay)
			},
			function(e){
				var $targetul=$subul
				var header=$curobj.get(0)
				clearTimeout($targetul.data('timers').showtimer)
				$targetul.data('timers').hidetimer=setTimeout(function(){
					$targetul.animate({height:'hide', opacity:'hide'}, ddsmoothmenu.transition.outtime)
					if (smoothmenu.shadow.enable && !smoothmenu.css3support){
						if (ddsmoothmenu.detectwebkit){ //in WebKit browsers, set first child shadow's opacity to 0, as "overflow:hidden" doesn't work in them
							header.$shadow.children('div:eq(0)').css({opacity:0})
						}
						header.$shadow.css({overflow:'hidden'}).animate({height:0}, ddsmoothmenu.transition.outtime)
					}
				}, ddsmoothmenu.showhidedelay.hidedelay)
			}
		) //end hover
	}) //end $headers.each()
	if (smoothmenu.shadow.enable && smoothmenu.css3support){ //if shadows enabled and browser supports CSS3 shadows
		var $toplevelul=$('#'+setting.mainmenuid+' ul li ul')
		var css3shadow=parseInt(smoothmenu.shadow.offsetx)+"px "+parseInt(smoothmenu.shadow.offsety)+"px 5px #aaa" //construct CSS3 box-shadow value
		var shadowprop=["boxShadow", "MozBoxShadow", "WebkitBoxShadow", "MsBoxShadow"] //possible vendor specific CSS3 shadow properties
		for (var i=0; i<shadowprop.length; i++){
			$toplevelul.css(shadowprop[i], css3shadow)
		}
	}
	$mainmenu.find("ul").css({display:'none', visibility:'visible'})
},

init:function(setting){
	if (typeof setting.customtheme=="object" && setting.customtheme.length==2){ //override default menu colors (default/hover) with custom set?
		var mainmenuid='#'+setting.mainmenuid
		var mainselector=(setting.orientation=="v")? mainmenuid : mainmenuid+', '+mainmenuid
		document.write('<style type="text/css">\n'
			+mainselector+' ul li a {background:'+setting.customtheme[0]+';}\n'
			+mainmenuid+' ul li a:hover {background:'+setting.customtheme[1]+';}\n'
		+'</style>')
	}
	this.shadow.enable=(document.all && !window.XMLHttpRequest)? false : this.shadow.enable //in IE6, always disable shadow
	jQuery(document).ready(function($){ //ajax menu?
		if (typeof setting.contentsource=="object"){ //if external ajax menu
			ddsmoothmenu.getajaxmenu($, setting)
		}
		else{ //else if markup menu
			ddsmoothmenu.buildmenu($, setting)
		}
	})
}

} //end ddsmoothmenu variable


</SCRIPT>
<STYLE TYPE="text/css">
.ddsmoothmenu{
font: bold 16px Arial;
//background: #414141; /*background of menu bar (default state)*/
width: 100%;
}

.ddsmoothmenu ul{
z-index:100;
margin: 0;
padding: 0;
list-style-type: none;
}

/*Top level list items*/
.ddsmoothmenu ul li{
position: relative;
display: inline;
float: left;
}

/*Top level menu link items style*/
.ddsmoothmenu ul li a{
display: block;
//background: #414141; /*background of menu items (default state)*/
color: white;
padding: 8px 10px;
//border-right: 1px solid #778;
color: #2d2b2b;
text-decoration: none;
}

* html .ddsmoothmenu ul li a{ /*IE6 hack to get sub menu links to behave correctly*/
display: inline-block;
}

.ddsmoothmenu ul li a:link, .ddsmoothmenu ul li a:visited{
color: black;
}

.ddsmoothmenu ul li a.selected{ /*CSS class that's dynamically added to the currently active menu items' LI A element*/
background: black; 
color: white;
}

.ddsmoothmenu ul li a:hover{
background: black; /*background of menu items during onmouseover (hover state)*/
color: white;
}
	
/*1st sub level menu*/
.ddsmoothmenu ul li ul{
position: absolute;
left: 0;
display: none; /*collapse all sub menus to begin with*/
visibility: hidden;
}

/*Sub level menu list items (undo style from Top level List Items)*/
.ddsmoothmenu ul li ul li{
display: list-item;
float: none;
}

/*All subsequent sub menu levels vertical offset after 1st level sub menu */
.ddsmoothmenu ul li ul li ul{
top: 0;
}

/* Sub level menu links style */
.ddsmoothmenu ul li ul li a{
font: normal 13px Verdana;
width: 160px; /*width of sub menus*/
padding: 5px;
margin: 0;
border-top-width: 0;
border-bottom: 1px solid gray;
}

/* Holly Hack for IE \*/
* html .ddsmoothmenu{height: 1%;} /*Holly Hack for IE7 and below*/


/* ######### CSS classes applied to down and right arrow images  ######### */

.downarrowclass{
position: absolute;
top: 12px;
right: 7px;
}

.rightarrowclass{
position: absolute;
top: 6px;
right: 5px;
}

/* ######### CSS for shadow added to sub menus  ######### */

.ddshadow{ /*shadow for NON CSS3 capable browsers*/
position: absolute;
left: 0;
top: 0;
width: 0;
height: 0;
background: white;
}

.toplevelshadow{ /*shadow opacity for NON CSS3 capable browsers. Doesn't work in IE*/
opacity: 1;
}

.ddsmoothmenu-v ul{
margin: 0;
padding: 0;
width: 170px; /* Main Menu Item widths */
list-style-type: none;
font: bold 12px Verdana;
}
 
.ddsmoothmenu-v ul li{
position: relative;
}

/* Top level menu links style */
.ddsmoothmenu-v ul li a{
display: block;
overflow: auto; /*force hasLayout in IE7 */
color: white;
text-decoration: none;
padding: 6px;
border-bottom: 1px solid #778;
border-right: 1px solid #778;
}

.ddsmoothmenu-v ul li a:link, .ddsmoothmenu-v ul li a:visited, .ddsmoothmenu-v ul li a:active{
background: #414141; /*background of menu items (default state)*/
color: white;
}


.ddsmoothmenu-v ul li a.selected{ /*CSS class that's dynamically added to the currently active menu items' LI A element*/
background: black; 
color: white;
}

.ddsmoothmenu-v ul li a:hover{
background: black; /*background of menu items during onmouseover (hover state)*/
color: white;
}

/*Sub level menu items */
.ddsmoothmenu-v ul li ul{
position: absolute;
width: 170px; /*Sub Menu Items width */
top: 0;
font-weight: normal;
visibility: hidden;
}

 
/* Holly Hack for IE \*/
* html .ddsmoothmenu-v ul li { float: left; height: 1%; }
* html .ddsmoothmenu-v ul li a { height: 1%; }
/* End */
</STYLE>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
<script type="text/javascript">

ddsmoothmenu.init({
	mainmenuid: "smoothmenu1", //menu DIV id
	orientation: 'h', //Horizontal or vertical menu: Set to "h" or "v"
	classname: 'ddsmoothmenu', //class added to menu's outer DIV
	//customtheme: ["#1c5a80", "#18374a"],
	contentsource: "markup" //"markup" or ["container_id", "path_to_menu_file"]
})


</script>



<div id="smoothmenu1" style="width:20px" >
<ul>
<li><a href="#" style="font-size:15px;color:red;">Login</a>
  <ul>
  <li align="center">
<table width="200px" style="padding:10px;"> 
            <form name="login" action="index.php?p=member" method="post">
          <tr><td><?php echo _('Id/Username'); ?>  <input type="text" name="uname" value="<?php echo 'Enter Username'.$_REQUEST['username']; ?>" onclick="this.value=''" style="width:115px;"/></td></tr>
	<tr><td><?php echo _('Password'); ?> <input type="password" name="pass" value="<?php echo 'Enter Password'. $_REQUEST['password']; ?>" onclick="this.value=''"/></td></tr>	
<tr><td><?php echo ""; ?>
 <?php	             
		$member_status = 'unchecked';
		$admin_status = 'unchecked';

		
		if($_REQUEST['profile']=='teacher' || $_REQUEST['profile']=='student' )
	        {
		$member_status = 'checked';
		}
		else if ($_REQUEST['profile']=='admin') {
		$admin_status = 'checked';
		}
		else
		{
			$member_status = 'checked';
		}

	
	   ?><input type="radio" name="user" value="member"<?php print $member_status; ?>/>member
             <input type="radio" name="user" value="admin" <?php print $admin_status; ?>/>admin
</td></tr>
	  <br>
            <tr><td><input type="submit" name="logMeIn" value="<?php echo __('login'); ?>" class="button marginTop" /></td></tr>
            </form>	
                   
          
</li>
  </ul>
</li>


</ul>
<br style="clear: left" />
</div>


