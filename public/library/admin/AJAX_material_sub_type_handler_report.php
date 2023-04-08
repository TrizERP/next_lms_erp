<?php

require_once '../sysconfig.inc.php';
// session checking
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

// list limit
$limit = 20;

$sql_string="SELECT gmd_id, gmd_name, material_resource_id  FROM mst_gmd  WHERE  material_resource_id ='".$_REQUEST['keywords']."'";
// send query to databasef
$query = $dbs->query($sql_string);
$error = $dbs->error;
 
 echo '<option value="0">--Select--</option>'."\n";
if ($query->num_rows > 0) {
    while ($row = $query->fetch_row()) {
        echo '<option value="'.$row[0].'">'.$row[1].'</option>'."\n";
    }
  
} else {
    // output the SQL string
	
   // echo '<option value="0">'.$sql_string.'</option>';
    echo '<option value="0">NO DATA FOUND</option>';
}


/*if ($query->num_rows > 0) 
{
    while ($row = $query->fetch_row()) 
    {
        echo '<option value="'.$row[0].'">'.$row[1].'</option>'."\n";
        $data.='"'.$row[0].'='.$row[1].'",';
    }
    
   substr($data, 0,-1);
   $data=Array($data);
   echo $data;
}
else
{
  
    
   // echo '</select >';
}*/
?>
