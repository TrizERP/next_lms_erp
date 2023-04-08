<style type="text/css">

 .thumbnail { float: left; width: 6em; border: 1px solid #999;
 margin: 3em 1em 1em 0em; padding: 5px;
 text-align: center;}
 .thumbnail img { border: 1px solid #aaa; }
 .thumbnail p { margin:0em 0em 0em 0em; }
 .clearboth { clear: both; }

</style>
<script>
var set_url='';
var set_id=''; 
var set_url1='';
var set_id1=''; 
function listEntries(booksInfo) { 

// Clear any old data to prepare to display the Loading... message.
//var div = document.getElementById("data");
//if (div.firstChild) div.removeChild(div.firstChild);
// var mainDiv = document.createElement("div");

//alert($getnew);
//alert(booksInfo["responseData"]);
 for (i in booksInfo) {
 // Create a DIV for each book
 var book = booksInfo[i];
//alert(book);
 //var element = document.getElementById(i); 
//element.href = book.thumbnail_url; 
set_url = book.thumbnail_url;
var word = set_url.split("?");
var word1 = word[1].split("&");
set_id = word1[0];
//set_preview_url = book.info_url;
//alert(set_preview_url);
//alert(book.info_url);

 //var thumbnailDiv = document.createElement("div");
 //thumbnailDiv.className = "thumbnail";
//alert(thumbnailDiv.className); 
// Add a link to each book's informtaion page
 //var a = document.createElement("a");
 //a.href = book.info_url;
 //a.innerHTML = book.bib_key ;
//alert(book.info_url); 
// Display a thumbnail of the book's cover
 //var img = document.createElement("img");
 //img.src = book.thumbnail_url;

 
//a.appendChild(img);
 //thumbnailDiv.appendChild(a);
 // Alert the user that the book is not previewable
 //var p = document.createElement("p");
 //p.innerHTML = book.preview;
 //if (p.innerHTML == "noview"){
 //p.style.fontWeight = "bold";
 //p.style.color = "#f00";
 //}

 //thumbnailDiv.appendChild(p);
 //mainDiv.appendChild(thumbnailDiv);
 }
 //div.appendChild(mainDiv);

}
function listEntries1(booksInfo) { 

// Clear any old data to prepare to display the Loading... message.
//var div = document.getElementById("data");
//if (div.firstChild) div.removeChild(div.firstChild);
// var mainDiv = document.createElement("div");
//alert($getnew);
//alert(booksInfo["responseData"]);
 for (i in booksInfo) {
 // Create a DIV for each book
 var book1 = booksInfo[i];
//alert(book);
 //var element = document.getElementById(i); 
//element.href = book.thumbnail_url; 
set_url1 = book1.thumbnail_url;
var word = set_url1.split("?");
var word1 = word[1].split("&");
set_id1 = word1[0];
//set_preview_url = book.info_url;
//alert(set_preview_url);
//alert(book.info_url);

 //var thumbnailDiv = document.createElement("div");
 //thumbnailDiv.className = "thumbnail";
//alert(thumbnailDiv.className); 
// Add a link to each book's informtaion page
 //var a = document.createElement("a");
 //a.href = book.info_url;
 //a.innerHTML = book.bib_key ;
//alert(book.info_url); 
// Display a thumbnail of the book's cover
 //var img = document.createElement("img");
 //img.src = book.thumbnail_url;

 
//a.appendChild(img);
 //thumbnailDiv.appendChild(a);
 // Alert the user that the book is not previewable
 //var p = document.createElement("p");
 //p.innerHTML = book.preview;
 //if (p.innerHTML == "noview"){
 //p.style.fontWeight = "bold";
 //p.style.color = "#f00";
 //}

 //thumbnailDiv.appendChild(p);
 //mainDiv.appendChild(thumbnailDiv);
 }
 //div.appendChild(mainDiv);

}
</script>




<script id="jsonScript" src="http://books.google.com/books?bibkeys=ISBN:<?php echo $_GET[img_set]; ?>&jscmd=viewapi&callback=listEntries">


//alert(callback);
</script>
<script id="jsonScript" src="http://books.google.com/books?bibkeys=ISSN:<?php echo $_GET[img_set]; ?>&jscmd=viewapi&callback=listEntries1">


//alert(callback);
</script>
<SCRIPT TYPE="text/javascript" language="javascript">
  //var make = "<img src="+set_url+ "ID=toggler   />";
var make = set_id;
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
var makeit = 'http://books.google.com/books?'+set_id+'&source=gbs_navlinks_s';
//location.href='test4.php?var1='+makeit;

var make1 = set_id1;
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
var makeit1 = 'http://books.google.com/books?'+set_id1+'&source=gbs_navlinks_s';
if(set_id!='')
{

//function checkall()
//{
//document.getElementById('forgetting').value=set_id;
//document.getElementById("php_code").innerHTML="<?php include('test4.php?"set_id"'); ?>";
location.href = 'toc1.php?var1='+makeit;
//alert(url);
}
else if(set_id1!='')
{

//}
//function checkall1()
//{
//document.getElementById('forgetting').value=set_id1;
//document.getElementById("php_code").innerHTML="<?php include('test4.php?"set_id"'); ?>";
location.href = 'toc1.php?var1='+makeit1;
}
//alert(url);
else
{
document.write('Sorry Table Of Contents Not Available !!!!');
}

//}


</script>



