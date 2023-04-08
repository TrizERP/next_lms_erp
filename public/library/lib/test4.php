<?php
// session checking
// Assuming the above tags are at www.example.com
//$set = 'http://books.google.com/books?';
//$set.= "<script>document.write(make);</script>";
//$set.='&source=gbs_navlinks_s';
//echo "-->".$_GET['var1'];
//echo "||-->".$_POST['var1'];
$tags = get_meta_tags($_GET['var1']);
 
// Notice how the keys are all lowercase now, and
// how . was replaced by _ in the key.
//echo $tags['author'];       // name
//echo $tags['keywords'];     // php documentation
echo '<table align=center border=1><tr bgcolor=lightgray><td align=center>Summary</td></tr><tr><td>';
echo '<div style="width:100%;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$tags['description'].'</div>';  // a php manual
echo '<td></tr></table>';
/*@$_SESSION['summary'] = '<div style="width:250px;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$tags['description'].'</div>';*/
//$contents = file_get_contents($_GET['var1']); 
//echo $contents['description'];
//echo $tags['geo_position']; // 49.33;-86.59
?>
