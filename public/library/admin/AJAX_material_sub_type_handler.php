<?php

require_once '../sysconfig.inc.php';
// session checking
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';


// list limit
$limit = 20;
$table_name = $dbs->escape_string(trim($_POST['tableName']));
$table_fields = trim($_POST['tableFields']);

if (isset($_POST['keywords']) AND !empty($_POST['keywords'])) {

   $keywords = $dbs->escape_string(urldecode(trim($_POST['keywords'])));
} else {
    $keywords = '';
}

// explode table fields data
$fields = str_replace(':', ', ', $table_fields);
// set where criteria
$criteria = '';
$explode=explode(':', $table_fields);
$array=array_pop($explode);

//foreach (explode(':', $table_fields) as $field) {
 //  $criteria .= " $field LIKE '%$keywords%' OR";
//}
// remove the last OR
//$criteria = substr_replace($criteria, '', -2);
$criteria.=" $array ='$keywords'";
$sql_string = "SELECT $fields ";

// append table name
$sql_string .= " FROM $table_name ";

if ($criteria) 
{
    $sql_string .= " WHERE $criteria order by $explode[1] LIMIT $limit";
}

// send query to databasef
$query = $dbs->query($sql_string);
$error = $dbs->error;
if ($error) {
    die('<option value="0">SQL ERROR : '.$error.'</option>');
}
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
?>
