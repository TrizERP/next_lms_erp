<?php
session_start();
require '../../../sysconfig.inc.php';

require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';

// privileges checking
$can_read = utility::havePrivilege('master_file', 'r');
$can_write = utility::havePrivilege('master_file', 'w');
  $label_cache = array();

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}
function check_url($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	$headers = curl_getinfo($ch);
	curl_close($ch);
	return $headers['http_code'];
}
$query_file = "SELECT labels,title,isbn_issn from biblio";
$set_file = $dbs->query($query_file);
echo "<table border=0 align=center id=dataList cellpadding=5 cellspacing=0 class=alterCell style=font-weight: bold; class=alterCell2><tr class=dataListHeader style=font-weight:bold;><td>URL</td><td>Title</td><td>ISBN/ISSN</td><td>Active or Broken</td></tr>";
while($_biblio_d=$set_file->fetch_assoc())
{
if (!empty($_biblio_d['labels'])) {
                $arr_labels = @unserialize($_biblio_d['labels']);
                if ($arr_labels !== false) {
	                foreach ($arr_labels as $label) {
	                    $myurl = $label['1'];
			    $satus = check_url($myurl);
				if($satus == '200')
					{
					echo "<tr><td>$label[1]</td><td>$_biblio_d[title]</td><td>$_biblio_d[isbn_issn]</td>";
					echo "<td>Active</td></tr>";
					}
				else
					{
					echo "<tr><td>$label[1]</td><td>$_biblio_d[title]</td><td>$_biblio_d[isbn_issn]</td><td>broken url</td></tr>";
					}
							}
         
  					}
				}
}
echo "</table>";
//echo $query_file = "SELECT file_url from files where mime_type = 'text/uri-list'";
/*$set_file = $dbs->query($query_file);
while($set_get_file=$set_file->fetch_assoc())
{
echo "hi";
$myurl = $set_get_file['file_url'];
$satus = check_url($myurl);
if($satus == '200')
	echo "Its works";
else
	echo "broken url";
}*/
?>
