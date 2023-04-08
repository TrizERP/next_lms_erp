<?php
// session checking
// Assuming the above tags are at www.example.com
//$set = 'http://books.google.com/books?';
//$set.= "<script>document.write(make);</script>";
//$set.='&source=gbs_navlinks_s';
//echo "-->".$_GET['var1'];
//echo "||-->".$_POST['var1'];
//$tags = get_meta_tags($_GET['var1']);
 
// Notice how the keys are all lowercase now, and
// how . was replaced by _ in the key.
//echo $tags['author'];       // name
//echo $tags['keywords'];     // php documentation
echo '<table align=center border=1><tr bgcolor=lightgray><td align=center>Author Biography</td></tr><tr><td>';
$data = file_get_contents($_GET['var1']);
//$data = file_get_contents($_GET['var1']);

//read the file
$convert = explode("<div id=about_author class=about_content>", $data); //create array separate by new line
$convert1 = explode("<div class=vertical_module_list_row>", $convert[1]); //create array separate by new line

//for ($i=0;$i<count($convert1);$i++) 
//{
   // echo $convert1[0].''; //write value by index
//}
echo '<div style="width:100%;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$convert1[0].'</div>';  // a php manual
echo '</td></tr></table>';
/*@$_SESSION['summary'] = '<div style="width:250px;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$tags['description'].'</div>';*/
//$contents = file_get_contents($_GET['var1']); 
//echo $contents['description'];
//echo $tags['geo_position']; // 49.33;-86.59

?>
