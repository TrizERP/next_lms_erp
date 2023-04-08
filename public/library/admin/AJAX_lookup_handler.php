<?php
require_once '../sysconfig.inc.php';
// session checking
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';


// list limit
$limit = 20;
$table_name = $dbs->escape_string(trim($_POST['tableName']));
$table_fields = trim($_POST['tableFields']);

if (isset($_POST['keywords']) AND !empty($_POST['keywords'])) 
{

   $keywords = $dbs->escape_string(urldecode(trim($_POST['keywords'])));
}
else
{
    $keywords = '';
}
/*if(number_format($keywords))
{
    //    echo '<option value="0">Please Enter Character value</option>';
}*/

// explode table fields data
$fields = str_replace(':', ', ', $table_fields);
// set where criteria
$criteria = '';
foreach (explode(':',$table_fields) as $field) 
{
   $criteria .= " $field LIKE '%$keywords%' OR";
}
// remove the last OR
$criteria = substr_replace($criteria, '', -2);

$order_by = '';
if ($table_name=='mst_subject_type')
{
    $sql_string = "SELECT $fields,mst_topic.topic";
    $sql_string .= " FROM $table_name LEFT JOIN mst_topic ON mst_subject_type.topic_id=mst_topic.topic_id";
    $order_by = "order by subject_type_name";
}
elseif ($table_name=='mst_author')
{
    $sql_string = "SELECT $fields,authority_type";
    $sql_string .= " FROM $table_name";
    $order_by = "order by author_name";
}

else
{
    // echo 'divya';
    $sql_string = "SELECT $fields";
    $sql_string .= " FROM $table_name";
    // $order_by = "order by mst_topic.topic";
}
if ($criteria)
{
    $sql_string .= " WHERE $criteria $order_by LIMIT $limit";
}

// echo $sql_string;//die;
//SELECT subject_type_id, subject_type_name,mst_topic.topic  FROM mst_subject_type  LEFT JOIN mst_topic ON mst_subject_type.topic_id=mst_topic.topic_id  WHERE  mst_subject_type.subject_type_id LIKE '%maths%' OR mst_subject_type.subject_type_name LIKE '%maths%'
// send query to database

$query = $dbs->query($sql_string);
$error = $dbs->error;
if ($error) {
    die('<option value="0">SQL ERROR : '.$error.'</option>');
}

if ($query->num_rows > 0) 
{
    while ($row = $query->fetch_row())
    {        
        if ($table_name=='mst_subject_type')
        {   
            echo '<option value="'.$row[0].'">'.$row[1] .' - '. $row[2].'</option>'."\n";
        }
        elseif($table_name=='mst_author')
        {
            if ($row[2]=='p')
            {
                $body='Personal Name';
            }
            elseif($row[2]=='o')
            {
                $body='Organizational Body';
            }
            elseif($row[2]=='c')
            {
                $body='Conference';
            }
            echo '<option value="'.$row[0].'">'.$row[1] .' - '. $body.'</option>'."\n";
        }
        else
        {
            echo '<option value="'.$row[0].'">'.$row[1].'</option>'."\n";
        }
    }
    echo '<option value="0">NONE</option>'."\n";
} else {
    // output the SQL string
    // echo '<option value="0">'.$sql_string.'</option>';
    echo '<option value="0">NO DATA FOUND</option>';
}
?>
