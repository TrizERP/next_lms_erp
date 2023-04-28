<?php
mysqli_connect("localhost", "root", "");
mysqli_select_db("trizcoin_slibrarynew");
$q = $_GET["q"];
//echo $_GET["t"];
$sql = "select biblio_id,title from biblio where title like '$q%' limit 0,5";
$sql1 = mysqli_query($sql);

echo "<table width=100% cellspacing=0 cellpadding=5  class='memberLoanList'>";
$i = '';
while ($row = mysqli_fetch_array($sql1)) {

    echo "<tr>";
    echo "<a href=\"?p=show_detail&id=" . $row['biblio_id'] . "\"style='font-weight: bold;color: #990000;  margin-right:10px;'>" . $row['title'] . "</a><br>";
    //echo "<td class='alterCell2'>" . $row['title'] ."</td>";
    echo "</tr>";
    $i++;
}

echo "</table>";



?>
