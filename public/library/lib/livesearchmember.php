<?php
mysql_connect("localhost","root","");
mysql_select_db("trizcoin_slibrarynew");
$q=$_GET["q"];
$sqlid = "select material_sub_id from mst_material_sub_type where material_sub_name = '$_GET[t]'";
$sqlid=mysql_query($sqlid);
while($rowid = mysql_fetch_assoc($sqlid))
	{
	$newid = $rowid['material_sub_id'];
	}
$sql="select biblio_id,title from biblio where title like '$q%' AND material_sub_id = '$newid' limit 0,5";
//echo $sql;
$sql1=mysql_query($sql);

 echo "<table width=100% cellspacing=0 cellpadding=5  class='memberLoanList'>";
  $i='';	
  while ($row=mysql_fetch_array($sql1))
 {
	
	echo "<tr>";
        echo "<a href=\"?p=show_detail&id=" . $row['biblio_id'] . "\"style='font-weight: bold;color: #990000;  margin-right:10px;'>" . $row['title'] . "</a><br>";
	//echo "<td class='alterCell2'>" . $row['title'] ."</td>";
	echo "</tr>";
        $i++;
}

echo "</table>";


