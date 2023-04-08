<?php
//echo "hi";
// Assuming the above tags are at www.example.com
//$set = 'http://books.google.com/books?';
//$set.= "<script>document.write(make);</script>";
//$set.='&source=gbs_navlinks_s';
$tags = get_meta_tags($_GET['var1']);

// Notice how the keys are all lowercase now, and
// how . was replaced by _ in the key.
//echo $tags['author'];       // name
//echo $tags['keywords'];     // php documentation
echo '<div style="width:250px;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$tags['description'].'</div>';  // a php manual
//echo $tags['geo_position']; // 49.33;-86.59
?>
