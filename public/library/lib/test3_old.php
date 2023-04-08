<style type="text/css">

 .thumbnail { float: left; width: 6em; border: 1px solid #999;
 margin: 3em 1em 1em 0em; padding: 5px;
 text-align: center;}
 .thumbnail img { border: 1px solid #aaa; }
 .thumbnail p { margin:0em 0em 0em 0em; }
 .clearboth { clear: both; }

</style>
<script>
var set_url;
var set_id; 
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

</script>




<script id="jsonScript" src="http://books.google.com/books?bibkeys=ISBN:<?php echo $_GET[img_set]; ?>&jscmd=viewapi&callback=listEntries">


//alert(callback);
</script>

<SCRIPT TYPE="text/javascript" language="javascript">
  //var make = "<img src="+set_url+ "ID=toggler   />";
var make = set_id;
  //var make_frame = "<div align=center style= overflow:hidden;><iframe src="+set_preview_url+" height=400px width=600px; style=margin-top:-200px;margin-left:-500px;></iframe></div>"; 	
var makeit = 'http://books.google.com/books?'+set_id+'&source=gbs_navlinks_s';
//location.href='test4.php?var1='+makeit;

function checkall()
{
document.getElementById('forgetting').value=set_id;
//document.getElementById("php_code").innerHTML="<?php include('test4.php?"set_id"'); ?>";
location.href = 'test4.php?var1='+makeit;
//alert(url);



}

<?php 
		 $set = "<script language=javascript>document.write(makeit);
</script>";
?>

//document.write(newform); 

//iURL="pix.php?variable=" + set_id; 

//var the_cookie = ur_id + "=" + set_id; 
//document.cookie = the_cookie;
 //document.write(make_frame);	

</script>


<?php
// Assuming the above tags are at www.example.com
//$set = 'http://books.google.com/books?';
//$set.= "<script>document.write(make);</script>";
//$set.='&source=gbs_navlinks_s';

$get = $set;
//echo $get;
if(!empty($get))
{
//$tags = get_meta_tags($get);
echo "<body onLoad=javascript:checkall(); >";
echo "<input type=hidden name=forgetting id=forgetting ></body>";
echo "<span id=php_code> </span>";

// Notice how the keys are all lowercase now, and
// how . was replaced by _ in the key.
//echo $tags['author'];       // name
//echo $tags['keywords'];     // php documentation
//echo $tags['description'];  // a php manual
}
//echo $tags['geo_position']; // 49.33;-86.59
?>
