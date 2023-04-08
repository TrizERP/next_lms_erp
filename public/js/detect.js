var BrowserDetect = {
	init: function () {
		this.browser = this.searchString(this.dataBrowser) || "An unknown browser";
		this.version = this.searchVersion(navigator.userAgent)
			|| this.searchVersion(navigator.appVersion)
			|| "an unknown version";
		this.OS = this.searchString(this.dataOS) || "an unknown OS";
	},
	searchString: function (data) {
		for (var i=0;i<data.length;i++)	{
			var dataString = data[i].string;
			var dataProp = data[i].prop;
			this.versionSearchString = data[i].versionSearch || data[i].identity;
			if (dataString) {
				if (dataString.indexOf(data[i].subString) != -1)
					return data[i].identity;
			}
			else if (dataProp)
				return data[i].identity;
		}
	},
	searchVersion: function (dataString) {
		var index = dataString.indexOf(this.versionSearchString);
		if (index == -1) return;
		return parseFloat(dataString.substring(index+this.versionSearchString.length+1));
	},
	dataBrowser: [
		{
			string: navigator.userAgent,
			subString: "Chrome",
			identity: "Chrome"
		},
		{ 	string: navigator.userAgent,
			subString: "OmniWeb",
			versionSearch: "OmniWeb/",
			identity: "OmniWeb"
		},
		{
			string: navigator.vendor,
			subString: "Apple",
			identity: "Safari",
			versionSearch: "Version"
		},
		{
			prop: window.opera,
			identity: "Opera"
		},
		{
			string: navigator.vendor,
			subString: "iCab",
			identity: "iCab"
		},
		{
			string: navigator.vendor,
			subString: "KDE",
			identity: "Konqueror"
		},
		{
			string: navigator.userAgent,
			subString: "Firefox",
			identity: "Firefox"
		},
		{
			string: navigator.vendor,
			subString: "Camino",
			identity: "Camino"
		},
		{		// for newer Netscapes (6+)
			string: navigator.userAgent,
			subString: "Netscape",
			identity: "Netscape"
		},
		{
			string: navigator.userAgent,
			subString: "MSIE",
			identity: "Internet Explorer",
			versionSearch: "MSIE"
		},
		{
  			string: navigator.userAgent,
  			subString: "Trident",
  			identity: "Internet Explorer",
  			versionSearch: "rv"
  		},{
			string: navigator.userAgent,
			subString: "Gecko",
			identity: "Safari",
			versionSearch: "Version"
		},
		{
			string: navigator.userAgent,
			subString: "Gecko",
			identity: "Mozilla",
			versionSearch: "rv"
		},
		{ 		// for older Netscapes (4-)
			string: navigator.userAgent,
			subString: "Mozilla",
			identity: "Netscape",
			versionSearch: "Mozilla"
		}
	],
	dataOS : [
		{
			string: navigator.platform,
			subString: "Win",
			identity: "Windows"
		},
		{
			string: navigator.platform,
			subString: "Mac",
			identity: "Mac"
		},
		{
			string: navigator.userAgent,
			subString: "iPad",
			identity: "iPad"
	    },
		{
			string: navigator.userAgent,
			subString: "Android",
			identity: "Android"
	    },
		{
			string: navigator.platform,
			subString: "Linux",
			identity: "Linux"
		}
	]
 
};

function browserCheck() {
	var deferred = new $.Deferred();
		BrowserDetect.init();
		var checkFailed = false;
		
		$('#result-icon').html('<i class="ti-settings fa fa-spin text-blue"></i>');
		$('#recommend-text-line1').html('Please wait while we check your device...');
		
		if (BrowserDetect.OS == "Windows" || BrowserDetect.OS == "Mac" || BrowserDetect.OS == "iPad" || BrowserDetect.OS == "Android" || BrowserDetect.OS == "Linux") {
			$('#os-type-device').html(BrowserDetect.OS);
			$('#os-type-status').html('<i class="ti-check text-success"></i>');
		} else {
			$('#os-type-device').html(BrowserDetect.OS);
			$('#os-type-status').html('<i class="ti-close text-danger"></i>');
			checkFailed = true; 
			$('#recommend-text-line2').html('Your '+BrowserDetect.OS+' operating system is not supported. See below a list of supported operating systems.');
		}

		if (BrowserDetect.browser == "Chrome" || BrowserDetect.browser == "Internet Explorer" || BrowserDetect.browser == "Firefox" || BrowserDetect.browser == "Safari") {
			if (BrowserDetect.browser == "Chrome" ) {
				$('#browser-type-device').html( BrowserDetect.browser + ' ' + BrowserDetect.version);
				if( BrowserDetect.version > 10) {
					$('#browser-type-status').html('<i class="ti-check text-success"></i>');
				} else {
					$('#browser-type-status').html('<i class="ti-close text-danger"></i>');
					checkFailed = true; 
					$('#recommend-text-line2').html('Your Chrome browser version is not supported. <a href="https://www.google.com/chrome/browser/desktop/index.html#external" target="_new">Upgrade Chrome!</a>');
				}
			}

			if (BrowserDetect.browser == "Internet Explorer"){
				$('#browser-type-device').html( BrowserDetect.browser + ' ' + BrowserDetect.version);
				if(BrowserDetect.version > 8) {
					$('#browser-type-status').html('<i class="ti-check text-success"></i>');
				} else {
					$('#browser-type-status').html('<i class="ti-close text-danger"></i>');
					checkFailed = true; 
					$('#recommend-text-line2').html('Your IE browser version is not supported. <a href="https://www.microsoft.com/en-us/download/internet-explorer.aspx#external" target="_new">Upgrade IE!</a>');
				}
			}
		
			if (BrowserDetect.browser == "Firefox"){
				$('#browser-type-device').html( BrowserDetect.browser + ' ' + BrowserDetect.version);
				if(BrowserDetect.version > 5) {
					$('#browser-type-status').html('<i class="ti-check text-success"></i>');
				} else {
					$('#browser-type-status').html('<i class="ti-close text-danger"></i>');
					checkFailed = true; 
					$('#recommend-text-line2').html('Your Firefox browser version is not supported. <a href="http://www.mozilla.com/firefox#external" target="_new">Upgrade Firefox!</a>');
				}
			}
		
			if (BrowserDetect.browser == "Safari"){ 
				if(BrowserDetect.version > 4 || BrowserDetect.OS == "iPad") {
					if(BrowserDetect.version > 4){
						$('#browser-type-device').html( BrowserDetect.browser + ' ' + BrowserDetect.version);						
						$('#browser-type-status').html('<i class="ti-check text-success"></i>');
					}
					else{
						$('#browser-type-device').html(  BrowserDetect.browser + ' iPad App');
						$('#browser-type-status').html('<i class="ti-check text-success"></i>');
					   }
				} else {
					$('#browser-type-device').html( BrowserDetect.browser + ' ' + BrowserDetect.version);
					$('#browser-type-status').html('<i class="ti-close text-danger"></i>');
					checkFailed = true; 
					$('#recommend-text-line2').html('Your Safari browser version is not supported. <a href="https://support.apple.com/safari#external" target="_new">Upgrade Safari!</a>');
				}
			}
		} else {
			$('#browser-type-device').html( BrowserDetect.browser + ' ' + BrowserDetect.version);
			$('#browser-type-status').html('<i class="ti-close text-danger"></i>');
			checkFailed = true; 
			$('#recommend-text-line2').html('Your browser is not supported. We recommend you to <a href="https://www.google.com/chrome/browser/desktop/index.html#external" target="_new">Download Chrome</a> browser.');
		}


		var cookieEnabled = (navigator.cookieEnabled) ? true : false;
	  	if (typeof navigator.cookieEnabled == "undefined" && !cookieEnabled) { 
	  		document.cookie="testcookie";
	  		cookieEnabled = (document.cookie.indexOf("testcookie") != -1) ? true : false;
	  	}
	  	if(cookieEnabled){
	  		$('#cookie-device').html('Enabled');
	  		$('#cookie-status').html('<i class="ti-check text-success"></i>');
	  		var theCookies = document.cookie.split(';');
	  	    var aString = '';
	  	    for (var i = 1 ; i <= theCookies.length; i++) {
	  	        aString += i + '. ' + theCookies[i-1] + "<br>";
	  	    }
	  	    $('#cookie-text').html(aString)
	  	} else {
	  		$('#cookie-device').html('Not Enabled');
	  		$('#cookie-status').html('<i class="ti-close text-danger"></i>');
			checkFailed = true; 
			$('#recommend-text-line2').html('Your device does not support cookies. You must enable cookies on this device.');
		}

	  	if (typeof document.createElement('canvas').getContext === "function") {
			$('#html5-device').html('Supported');
			$('#html5-status').html('<i class="ti-check text-success"></i>');
		} else {
			$('#html5-device').html('Not Supported');
			$('#html5-status').html('<i class="ti-close text-danger"></i>');
			checkFailed = true; 
			$('#recommend-text-line2').html('Your device does not support HTML5. You must use a HTML5 supported device.');
		}
		
  		$('#js-device').html('Enabled');
		$('#js-status').html('<i class="ti-check text-success"></i>');
	  
		var promises = [];

	  	promises.push(checkDomain('edNexus-res','https://jsapi.ednexus.io/api.js','script'));
	  	promises.push(checkDomain('mathjax-res','https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.2.0/MathJax.js?config=TeX-AMS_HTML','script'));
	  	promises.push(checkDomain('video-res','https://ssl.p.jwpcdn.com/player/v/8.4.6/jwplayer.core.controls.html5.js','script'));
	  	$.when.apply($, promises).then(function(chk1, chk2, chk3){
	  		var chk4 = false;
	  		
	  		loadImageWithPromise('video-res', 'https://assets-jpcust.jwpsrv.com/watermarks/CXIvWNEK.png').then(function(jwimgstatus){
	  			chk4 = jwimgstatus;
	  			if((chk1 || chk2 || chk3 || chk4) && !checkFailed){
	  				$('#result-icon').html('<i class="ti-close text-danger"></i>');
					$('#recommend-text-line1').html('Sorry! This device is not compatible with ScootPad.');
					$('#recommend-text-line1').removeClass('text-success').addClass('text-danger');
					$('#result-icon').html('<i class="ti-close text-danger"></i>');
					$('#recommend-text-line1').html('Sorry! This device is not compatible with ScootPad.');
					$('#recommend-text-line1').removeClass('text-success').addClass('text-danger');
					$('#recommend-text-line2').html('Your device can not access required URLs. Ask your IT/Network administrator to whitelist URLs listed below.');
					checkFailed = true;
	  			} else if((chk1 || chk2 || chk3 || chk4) && checkFailed){
	  				$('#result-icon').html('<i class="ti-close text-danger"></i>');
  					$('#recommend-text-line1').html('Sorry! This device is not compatible with ScootPad.');
  					$('#recommend-text-line1').removeClass('text-success').addClass('text-danger');
					$('#result-icon').html('<i class="ti-close text-danger"></i>');
					$('#recommend-text-line1').html('Sorry! This device is not compatible with ScootPad.');
					$('#recommend-text-line1').removeClass('text-success').addClass('text-danger');
	  			} else {
	  				$('#result-icon').html('<i class="ti-check text-success"></i>');
					$('#recommend-text-line1').html('Yay! This device is compatible with ScootPad.');
					$('#recommend-text-line1').removeClass('text-danger').addClass('text-success');
	  			}
	  		})
	  		
	  		
	  		deferred.resolve(checkFailed);
	  		
	  	})	
	 return deferred.promise();
}

function checkDomain(id,url,type){
	var deferred = new $.Deferred();
	var isresolved = false;
    $.ajax({
	    type: 'GET',
	    url: url,	    
	    dataType: 'script',	
	    timeout: 3000,
		success: function(data, textStatus, xhr) {
			isresolved = true;
			deferred.resolve(checkDomainAvail(true,id));				
		}, error  : function(xhr,textStatus) {
			if(xhr.status>0 || xhr.statusText==="timeout") {
				isresolved = true;
				deferred.resolve(checkDomainAvail(false,id));	
			}			
		}
    })
    
    setTimeout(function(){
    	if(!isresolved){
    		deferred.resolve(true);
    	}
    }, 3000)
    
	return deferred.promise();
}

function loadImageWithPromise(id, url){
	var img = new Image();
	var deferred = new $.Deferred();
	img.onload = function(){
		deferred.resolve(checkDomainAvail(true,id));				
	}
	img.onerror = function(){
		deferred.resolve(checkDomainAvail(false,id));
	}
	img.onabort = function(){
		deferred.resolve(checkDomainAvail(false,id));
	}
	img.src = url;
	return deferred.promise();
}

function checkDomainAvail(isDomainAvail,id){
	if(isDomainAvail==true){
		$("#"+id+"-device").html('Accessible');
		$("#"+id).html('<i class="ti-check text-success"></i>');
		return false;
	} else {
		$("#"+id+"-device").html('Not Accessible');
		$("#"+id).html('<i class="ti-close text-danger"></i>');
		return true;
	}
}

function setDeviceCheckCookie(){
    var today = new Date();
	var date = new Date();
    date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
    $.cookie('sp_dchk', today, {expires: date, path: '/' });
}

function foundDeviceCheckCookie(){
	if($.cookie('sp_dchk') != '' && typeof($.cookie('sp_dchk')) !=='undefined'){
		return true;
	} else {
		return false;
	}
}

$(document).ready(function(){
	$('#clear-all').click(function(e){
		e.preventDefault();
		var cookies = document.cookie.split(";");
		
		for(var i=0; i < cookies.length; i++) {
		    var equals = cookies[i].indexOf("=");
		    var name = equals > -1 ? cookies[i].substr(0, equals) : cookies[i];
		    document.cookie = name+"=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0";
		}
		window.location.reload();
	})
	
	$('#show-cookies').click(function(e){
		$("#cookie-data").show();
	})
	
});
