  <script type="text/javascript" src="jquery-1.4.2.js">

</script>  
     <script type="text/javascript">  
 function doAjaxPost(str) {  
  // get the form values  
alert('hi');
 var field_a = $('#field_a').val();  
 var field_b = $('#field_b').val();  
  
   $.ajax({  
    type: "POST",  
    url: "getuser.php",  
    data: "firstname="+field_a+"&lastname="+field_b+"&q="+str,  
   success: function(resp){  
      // we have the response  
  
 //    alert("Server said:\n '" + resp + "'");  
 document.getElementById("new").innerHTML=resp;
    },  
    error: function(e){  
      alert('Error: ' + e);  
     }  
  });  
 }  
   </script>  
   <form>
First Name:  
 <input type="text" id="field_a" value="Sergio">  
 Last Name:  
<select name="new1" id="new1" onchange="doAjaxPost(this.value)">
<option value="select">--Select--<option>
<option value="Parth">Parth<option>
<option value="Kapadia">Kapadia<option>
</select>
 <input type="text" id="field_b" value="Leone">  
<table id="new"></table> 
 <input type="button" value="Ajax Request" onClick="doAjaxPost()">  
  </form>
