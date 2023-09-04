<!--<script src="https://miraibot.ai/embed@latest.js" id="687e1c179bbf486788f11fa77d33f82f"></script>-->
<div id="loading-overlay" style="display:none;">
<center>
  <img src="/admin_dep/images/loader-man.gif" id="loading-gif" alt="loading-gif" >
  </center>
    </div>
<script>
	// Accordian Menu
	$(document).ready(function(){
		$(".dot-icon, .abs-submenu").hover(
			function () {
			$(".tab-pane.active").addClass("result_hover");
			},
			function () {
			$(".tab-pane.active").removeClass("result_hover");
			}
		);
	});
</script>
</body>
<!-- loading gif  -->
<script>
        window.addEventListener('beforeunload', function() {
			$('#loading-overlay').show();
        });
</script>
	<script>
		function setSession(item,object)
		{
			var xhttp = new XMLHttpRequest();
			xhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {
					location.reload();
					var form_session = document.querySelector('form');
					if (form_session) {
						form_session.submit();
					}
				}
			};
			xhttp.open("GET", "{{route('setsession')}}?"+item+"="+object, true);
			xhttp.send();
		}

		function setInstitute(item,object)
		{
			var xhttp = new XMLHttpRequest();
			xhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {
					location.reload();
				}
			};
			xhttp.open("GET", "{{route('setinstitute')}}?"+item+"="+object, true);
			xhttp.send();
		}

	</script>
	<script type="text/javascript">
		$( document ).ready(function() {
		    var url = window.location;
			var element = $('ul.nav a').filter(function () {
				return this.href == url;
			});

			if (element) {
				element.addClass('active').parents('#side-menu ul').addClass('in');
				element.parents('#side-menu li').addClass('active');
				element.parents('main-nav nav-link a').addClass('active');
			}
		});
	</script>

	<script>
		$(function(){		
		var url = window.location.pathname, 
			urlRegExp = new RegExp(url.replace(/\/$/,'') + "$"); // create regexp to match current url pathname and remove trailing slash if present as it could collide with the link in navigation in case trailing slash wasn't present there
			// now grab every link from the navigation
			// $('.left-sidebar li.sub-drop-header a.panel-click').each(function(){

			$('.left-sidebar ul li a').each(function(){
				// and test its normalized href against the url pathname regexp				
				if(urlRegExp.test(this.href.replace(/\/$/,''))){
					$(this).parent('li').addClass('active');
					$(this).parent('li').parents('.sub-drop-body').css("display", "block");
					$(this).parent('li').parents('.sub-drop-panel').addClass('open');
					//$(this).parent('li').parents('.sub-drop-panel').parents('.tab-pane').addClass('active');											
					$('.nav-pills .nav-link.active').attr('aria-selected','true');
				}else{
					// $(this).parent('li').parents('.tab-pane').removeClass('active');
				}
			});
			$('.sub-drop-nav li a').each(function(){
				// and test its normalized href against the url pathname regexp
				if(urlRegExp.test(this.href.replace(/\/$/,''))){
					$(this).parent('li').addClass('active');
			
				}
			});
		});

		$(document).ready(function () {
		   $("main-menu-block main-nav a").each(function () {
           if ($(this).attr("aria-selected") === "true") {
	                $(this).parent().addClass('active')
	            } else {
	                $(this).parent().removeClass('active')

	            }
        	});
		});

		// Help Guide
		$('.help-body').hide(100);
		$('.guide-title').on('click', function(event) {
		    $('.help-guide').toggleClass('active', 100);
		    $('.help-body').slideToggle(100);
		});

	</script>
	<script type="text/javascript">
		$('.panel-click').on('click', function(event) {
        event.preventDefault();
		event.stopPropagation();
	     $(".sub-drop-panel.open > .sub-drop-body").show();
        // Just Remove .stop("true", "true")
		$(this).closest('.sub-drop-panel').toggleClass('open').children(".sub-drop-body").slideToggle(100);
		$(this).closest('.sub-drop-panel').siblings().find(".sub-drop-body").slideUp(100);
		$(this).closest('.sub-drop-panel').siblings().removeClass("open");
    });

	    $('.panel-click').on('click', function(event) {
        	$(this).parents('.tab-content').removeClass('active');
    	});	
    	
	</script>
</html>