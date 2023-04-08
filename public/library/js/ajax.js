function setOptions(matchWith){
  var choicesOLD = ['Civil','Mechanical','Electrical','IT','EC'];
  var choicesNEW = ['Title/Series Title','ISBN','Topic','Author','Publisher'];
  var choices = [];
  var sel = document.getElementById('list1');
  var tmp = '';
  sel.options.length = 0;
  switch (matchWith) {
    case 'ans1' : choices = choicesOLD.slice(0); break;
    case 'ans2' : choices = choicesNEW.slice(0); break;
 }
  for (i=0; i<choices.length; i++) {  
    tmp = choices[i];
    sel.options[sel.options.length] = new Option(tmp,tmp,false,false);
  }
}




function getData(dataSource, divID)
	{
                        
		var XMLHttpRequestObject =false;
		if(window.XMLHttpRequest){
			XMLHttpRequestObject = new XMLHttpRequest();
		}else if (window.ActiveXObject){
			XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP");
		}
		if(XMLHttpRequestObject){
			var obj = document.getElementById(divID);
			XMLHttpRequestObject.open("POST",dataSource);
			XMLHttpRequestObject.onreadystatechange = function(){
				if(XMLHttpRequestObject.readyState == 4  && XMLHttpRequestObject.status == 200)
				{
	var choicesOLD = ['TOWNOLD','CITYOLD','COUNTRYOLD'];
var choicesNEW = ['TOWNNEW','CITYNEW','COUNTRYNEW'];
				obj.innerHTML = XMLHttpRequestObject.responseText;
                                        
				}
			}
			XMLHttpRequestObject.send(null);
		}
	}
	function getData(dataSource, divID, param,afterComplete)
	{
		if(!param)
		{
			param='';
		}
		var XMLHttpRequestObject =false;
		if(window.XMLHttpRequest){
			XMLHttpRequestObject = new XMLHttpRequest();
		}else if (window.ActiveXObject){
			XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP");
		}
		if(XMLHttpRequestObject){
			var obj = document.getElementById(divID);
			XMLHttpRequestObject.open("POST",dataSource);
			XMLHttpRequestObject.setRequestHeader('Content-Type','application/x-www-form-urlencoded');			
			XMLHttpRequestObject.onreadystatechange = function(){
				if(XMLHttpRequestObject.readyState == 4  && XMLHttpRequestObject.status == 200){
					obj.innerHTML = XMLHttpRequestObject.responseText;
					if(afterComplete==1)
					setScript1();
					else if(afterComplete==2)
					setScript2();
					else if(afterComplete==3)
					setScript3();
				}
			}
			XMLHttpRequestObject.send(param);
		}
	}

	function setScript1()
	{
                alert('parimal1');
		/* WRITE YOUR CODE HERE*/
		
	}
	function setScript2()
	{
            alert('parimal2');
            //getData('subgroup_foliomap.php','folio_scheme_list','grpcode='+this.value+'&func=DisplayFolioSchemeList');
            /* WRITE YOUR CODE HERE*/
		
	}
	function setScript3()
	{
                alert('parimal3');
		/* WRITE YOUR CODE HERE*/		
	}

	/*//////////////////////////////////////////////////////////////////////////////
	This function use for call specific user define function when after getting
	response from server

	PARAMETER
	[1] dataSource : URL
	[2] divID : Division Name 
	[3] param : All Form elements value 
	[4] function_name : Function Name as String
	////////////////////////////////////////////////////////////////////////////////*/
	function getDataResponse(dataSource,divID,param,function_name)
	{
		var XMLHttpRequestObject =false;
		if(window.XMLHttpRequest)
		{
			XMLHttpRequestObject = new XMLHttpRequest();
		}
		else if (window.ActiveXObject)
		{
			XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP");
		}
		if(XMLHttpRequestObject)
		{
			var obj = document.getElementById(divID);
                        if(obj!=null)
			obj.innerHTML = '<img src="/images/loading.gif"  width="75" height="75"><br><div align=center><font color=darkblue><b>Loading Page... </b></font></div>';
			XMLHttpRequestObject.open("POST",dataSource);
                        XMLHttpRequestObject.setRequestHeader('Content-Type','application/x-www-form-urlencoded');			
			XMLHttpRequestObject.onreadystatechange = function()
			{
				if(XMLHttpRequestObject.readyState == 4  && XMLHttpRequestObject.status == 200){
                                        if(obj!=null)
					obj.innerHTML = XMLHttpRequestObject.responseText;
					if(function_name!='' && function_name!='getDataResponse')
					{
						eval(function_name+'();');
					}
				}
			}
			XMLHttpRequestObject.send(param);
		}
	}


	function getData(dataSource, divID, param){
		if(!param)
		{
			param='';
		}

		var XMLHttpRequestObject =false;
		if(window.XMLHttpRequest){
			XMLHttpRequestObject = new XMLHttpRequest();
		}else if (window.ActiveXObject){
			XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP");
		}
		if(XMLHttpRequestObject){
			var obj = document.getElementById(divID);
                        if(obj!=null)
			obj.innerHTML = '<img src="../images/load_page.gif"  width="75" height="75"><br><div align="center"><font color="darkblue"><b>Loading Page... </b></font></div>';			
			XMLHttpRequestObject.open("POST",dataSource);
			XMLHttpRequestObject.setRequestHeader('Content-Type','application/x-www-form-urlencoded');			
			XMLHttpRequestObject.onreadystatechange = function(){
				if(XMLHttpRequestObject.readyState == 4  && XMLHttpRequestObject.status == 200){
                                        if(obj!=null)
					obj.innerHTML = XMLHttpRequestObject.responseText;
				}
			}
			XMLHttpRequestObject.send(param);
		}
	}	
	function getRequestBody(oForm) {
		var aParams = new Array();
		var sParam = '';
		for (var i=0 ; i < oForm.elements.length; i++) {
			if(oForm.elements[i].options){	// dropdwn		
				for(var j=0; j < oForm.elements[i].options.length; j++)
				{					
					if(oForm.elements[i].options[j].selected){
						sParam = encodeURIComponent(oForm.elements[i].name);
						sParam += "=";
						sParam += encodeURIComponent(oForm.elements[i][j].value);
						aParams.push(sParam);					
					}
					
					
				}
				//alert(oForm.elements[i].name + ' = ' +oForm.elements[i].options[0].selected);
			}else{
				sParam = encodeURIComponent(oForm.elements[i].name);
				sParam += "=";
				sParam += encodeURIComponent(oForm.elements[i].value);
				aParams.push(sParam);
			}
		}
		return aParams.join("&");	
		
	}
  function getalldata(oForm) {
		var aParams = new Array();
		var sParam = '';
		//aParams.push(sParam);
		for (var i=0 ; i < oForm.elements.length; i++) {				
					if (oForm.elements[i].tagName == "SELECT") 
					{
						 /*var sel = oForm.elements[i];
						 sParam = encodeURIComponent(sel.name) + "=" + encodeURIComponent(sel.options[sel.selectedIndex].value) + "&";
						*/ //aParams.push(sParam);
							for(var j=0; j < oForm.elements[i].options.length; j++)
							{					
									if(oForm.elements[i].options[j].selected){
										sParam = encodeURIComponent(oForm.elements[i].name);
										sParam += "=";
										sParam += encodeURIComponent(oForm.elements[i][j].value);
										aParams.push(sParam);					
									}
									
									
							}
							
					}
		
					if(oForm.elements[i].type == "checkbox" && oForm.elements[i].checked == true)
						{
							sParam = encodeURIComponent(oForm.elements[i].name);
							sParam += "=";
							sParam += encodeURIComponent(oForm.elements[i].value);
							aParams.push(sParam);					
						}
					
					if(oForm.elements[i].type == "radio" && oForm.elements[i].checked==true )
						{
							sParam = encodeURIComponent(oForm.elements[i].name);
							sParam += "=";
							sParam += encodeURIComponent(oForm.elements[i].value);
							aParams.push(sParam);					
						}
					
					if(oForm.elements[i].tagName == "INPUT" && oForm.elements[i].type=="text" )
						{
							sParam = encodeURIComponent(oForm.elements[i].name);
							sParam += "=";
							sParam += encodeURIComponent(oForm.elements[i].value);
							aParams.push(sParam);					
						}
					if(oForm.elements[i].tagName == "INPUT" && oForm.elements[i].type=="hidden" )
						{
							sParam = encodeURIComponent(oForm.elements[i].name);
							sParam += "=";
							sParam += encodeURIComponent(oForm.elements[i].value);
							aParams.push(sParam);					
						}
				      if(oForm.elements[i].tagName == "INPUT" && oForm.elements[i].type=="file")
						{
							sParam = encodeURIComponent(oForm.elements[i].name);
							sParam += "=";
							sParam += encodeURIComponent(oForm.elements[i].value);
							aParams.push(sParam);					
						}
					if(oForm.elements[i].type == "textarea")
						{	
							
							sParam = encodeURIComponent(oForm.elements[i].name);
							sParam += "=";
							sParam += encodeURIComponent(oForm.elements[i].value);
							aParams.push(sParam);	
						}		
				//alert("hello"+sParam);
				}
		
		return aParams.join("&");	
	}
	var timerid     = null;
	var matchString = "";
	var mseconds    = 1000;	// Length of time before search string is reset

	function shiftHighlight(keyCode,targ)
	{
		keyVal      = String.fromCharCode(keyCode); // Convert ASCII Code to a string
		matchString = matchString + keyVal; // Add to previously typed characters

		elementCnt  = targ.length - 1;	// Calculate length of array -1

		for (i=elementCnt; i > 0; i--)
		{
			selectText = targ.options[i].text.toLowerCase(); // convert text in SELECT to lower case

			if (selectText.substr(0,matchString.length) == 	matchString.toLowerCase())
			{
				targ.options[i].selected = true; // Make the relevant OPTION selected
				break;
			}
		}

		clearTimeout(timerid); // Clear the timeout
		timerid = setTimeout('matchString = ""',mseconds); // Set a new timeout to reset the key press string
		
		return false; // to prevent IE from doing its own highlight switching
	}

	function getData_sort(dataSource, divID, param)
        { 
		if(!param) 
		{ 
			param=''; 
		} 
                var XMLHttpRequestObject =false; 
                if(window.XMLHttpRequest){ 
                	XMLHttpRequestObject = new XMLHttpRequest(); 
                }else if (window.ActiveXObject){ 
                	XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP"); 
                } 
                if(XMLHttpRequestObject){ 
                	var obj = document.getElementById(divID); 
                        obj.innerHTML = '<center><img src="../images/load_page.gif"  width="75" height="75"><br><div align="center">'+
                         '<font color="darkblue"><b>Loading Page... </b></font></div></center>';	
                        XMLHttpRequestObject.open("POST",dataSource); 
                        XMLHttpRequestObject.setRequestHeader('Content-Type','application/x-www-form-urlencoded');	
                        XMLHttpRequestObject.onreadystatechange = function(){ 
                        if(XMLHttpRequestObject.readyState == 4  && XMLHttpRequestObject.status == 200){ 
                        	obj.innerHTML = XMLHttpRequestObject.responseText; 
                                setScript(); 
                        } 
                        } 
                        XMLHttpRequestObject.send(param); 
                } 
        } 
        function setScript() 
        { 
        	Table.auto(); 
        }
	function setDraggable() 
        { 
        	Table.auto();
		dragtable.init.done=false;
		dragtable.init();
        }

	function getData_sync(dataSource, divID, param)
	{
		var XMLHttpRequestObject =false;
		if(window.XMLHttpRequest){
			XMLHttpRequestObject = new XMLHttpRequest();
		}else if (window.ActiveXObject){
			XMLHttpRequestObject = new ActiveXObject("Microsoft.XMLHTTP");
		}
		if(XMLHttpRequestObject){
			var obj = document.getElementById(divID);
			XMLHttpRequestObject.open("POST",dataSource,false);
			XMLHttpRequestObject.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
			XMLHttpRequestObject.send(param);
			if(XMLHttpRequestObject.readyState == 4  && XMLHttpRequestObject.status == 200){
				obj.innerHTML = XMLHttpRequestObject.responseText;
				return XMLHttpRequestObject.responseText;
			}
			
		}
	}

jQuery("#side-menu > li > a").click(function () {
    jQuery('ul.sub-menu').not(jQuery(this).siblings()).slideUp();
    jQuery(this).siblings("ul.sub-menu").slideToggle();
});

function showSubMenu(e) {
  var c = e.getElementsByTagName("ul")[0]; 
  c.classList.toggle("sub-menu");
}

jQuery(function() {                       
  jQuery(".open-close").click(function() {  
    jQuery("body").toggleClass("active");     
  });
});
