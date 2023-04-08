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
function listEntries(booksInfo) 
{ 
     for (i in booksInfo) 
    {
 
        var book = booksInfo[i];
        set_url = book.thumbnail_url;
        var word = set_url.split("?");
        var word1 = word[1].split("&");
        set_id = word1[0];

    }


}
function listEntries1(booksInfo) 
{ 

 for (i in booksInfo) 
 {

        var book1 = booksInfo[i];
        set_url1 = book1.thumbnail_url;
        var word = set_url1.split("?");
        var word1 = word[1].split("&");
        set_id1 = word1[0];

 }
 

}
</script>




<script id="jsonScript" src="http://books.google.com/books?bibkeys=ISBN:<?php echo $_GET[img_set]; ?>&jscmd=viewapi&callback=listEntries"></script>
<script id="jsonScript" src="http://books.google.com/books?bibkeys=ISSN:<?php echo $_GET[img_set]; ?>&jscmd=viewapi&callback=listEntries1"></script>
<SCRIPT TYPE="text/javascript" language="javascript">
  
var make = set_id;  
var makeit = 'http://books.google.com/books?'+set_id+'&source=gbs_navlinks_s';
var make1 = set_id1;
var makeit1 = 'http://books.google.com/books?'+set_id1+'&source=gbs_navlinks_s';
if(set_id!='')
{
    location.href = 'test4.php?var1='+makeit;
}
else if(set_id1!='')
{

    location.href = 'test4.php?var1='+makeit1;
}
else
{
    document.write('Sorry Summary Not Available !!!!');
}




</script>



