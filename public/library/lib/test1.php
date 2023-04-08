<script>
var set_url='';
var set_url1='';
function listEntries(booksInfo) { 
// Clear any old data to prepare to display the Loading... message.
//var div = document.getElementById("data");
//if (div.firstChild) div.removeChild(div.firstChild);
// var mainDiv = document.createElement("div");

 for (i in booksInfo) {
 // Create a DIV for each book
 var book = booksInfo[i];
 //var element = document.getElementById(i); 
//element.href = book.thumbnail_url; 
set_url = book.thumbnail_url;
var myNewString = set_url.replace("zoom=5", "zoom=1");
set_url = myNewString;
set_preview_url = book.info_url;
//alert(myNewString);
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

function listEntries1(booksInfo1) { 
// Clear any old data to prepare to display the Loading... message.
//var div = document.getElementById("data");
//if (div.firstChild) div.removeChild(div.firstChild);
// var mainDiv = document.createElement("div");

 for (i in booksInfo){
 // Create a DIV for each book
 var book1 = booksInfo1[i];
 //var element = document.getElementById(i); 
//element.href = book.thumbnail_url; 
set_url1 = book1.thumbnail_url;
var myNewString = set_url1.replace("zoom=5", "zoom=1");
set_url1 = myNewString;
//set_preview_url = book1.info_url;
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
<script id="jsonScript" src="http://books.google.com/books?bibkeys=ISBN:<?php echo $img_set; ?>&jscmd=viewapi&callback=listEntries">
//alert(callback);
</script>
<script id="jsonScript1" src="http://books.google.com/books?bibkeys=ISSN:<?php echo $img_set; ?>&jscmd=viewapi&callback=listEntries1">
//alert(callback);
</script>


<SCRIPT TYPE="text/javascript">
if(set_url!='' && typeof set_url != 'undefined')
{
var makeset = 'set';
  var make = "<img id=<?php echo $img_set;?> src="+set_url+ " onmouseover=mover(this.id) onmouseout=mout(this.id) height=120px width=100px />";
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
 document.write(make);
}
else
{
var makeset = 'set';
var make2 = "<img src='lib/images/imagesnotavailable.jpg' ID=<?php echo $img_set;?>   height=120px width=100px />";
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
 document.write(make2);
}
 //document.write(make_frame);	

</script>


<SCRIPT TYPE="text/javascript">


  var make1 = "<img src="+set_url1+ " ID=<?php echo $img_set;?>   height=120px width=100px/>";
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
 //document.write(make1);

if(makeset!='set')
{
//var make11 = "<img src='lib/images/imagesnotavailable.jpg' ID=<?php echo $img_set;?>   height=120px width=100px />";
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
// document.write(make11);

 //document.write(make_frame);	
}
</script>

