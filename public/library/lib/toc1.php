<style>
.first_toc_column{
float:left;
}
.second_toc_column{
float:left;
}
.toc_number{
padding:10px;
}
</style>
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
echo '<table align=center border=1><tr bgcolor=lightgray><td align=center>Table Of Contents</td></tr><tr><td>';
$data = file_get_contents($_GET['var1']); //read the file
$convert = explode("<div id=toc class=about_content>", $data); //create array separate by new line
$convert1 = explode("Copyright", $convert[1]); //create array separate by new line
//$convert2 = explode("<tr>",$convert1[0]);
/*for ($i=0;$i<count($convert2);$i++) 
{
    echo "<tr><td>".$convert2[$i]."</td></tr><tr><td>".$convert2[$i+2]."</td></tr>";  // a php manual
    $i=1;
}*/
echo '<div style="width:100%;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$convert1[0].'</div>';  // a php manual
echo '</td></tr></table>';
/*@$_SESSION['summary'] = '<div style="width:250px;font-family:Helvetica,Arial;font-size:11px;list-style-image:none;list-style-position:outside;list-style-type:none;">'.$tags['description'].'</div>';*/
//$contents = file_get_contents($_GET['var1']); 
//echo $contents['description'];
//echo $tags['geo_position']; // 49.33;-86.59

?>
