<?php

require '../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';


/*define('DB_HOST', 'trizapps.in');
define('DB_PORT', '3306');
define('DB_NAME', 'trizino_slibrary');
define('DB_USERNAME', 'dev_db');
define('DB_PASSWORD', 'dev@sql');
$dbs = @new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);*/

//mysqli_connect("localhost","mysql_user","mysql_pwd");

mysqli_connect("trizapps.in", "dev_db", "dev@sql");
mysqli_select_db("trizino_slibrary");

//include SENAYAN_BASE_DIR.'admin/modules/circulation/loan_list.php';

$select = "SELECT SQL_CALC_FOUND_ROWS l.item_code AS 'Item Code', b.title AS 'Title', l.loan_date AS 'Loan Date', IF(return_date IS NULL, 'Not Returned Yet', return_date) AS 'Return Date' FROM loan AS l
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE l.member_id='$_SESSION[memberID]' ORDER BY l.loan_date DESC";


//$select = "SELECT loan_id as LoanID,item_code as Barcode,member_id as MemberID ,DATE_FORMAT(loan_date,'%d-%m-%Y') as Loandate,DATE_FORMAT(due_date,'%d-%m-%Y') as Duedate,case renewed when '1' then 'Yes' else 'No' end as renewed,ifnull(return_date,'Not Returned Yet')  as return_date,time as Time from loan where member_id='".$_REQUEST['memberid']."'";

$export = mysql_query($select) or die ("Sql error : " . mysqli_error());

$fields = mysql_num_fields($export);

for ($i = 0; $i < $fields; $i++) {
    $header .= mysql_field_name($export, $i) . "\t";
}

while ($row = mysql_fetch_row($export)) {
    $line = '';
    foreach( $row as $value ) {
        if ( ( !isset( $value ) ) || ( $value == "" ) )
        {
            $value = "\t";
        }
        else
        {
            $value = str_replace( '"' , '""' , $value );
            $value = '"' . $value . '"' . "\t";
        }
        $line .= $value;
    }
    $data .= trim( $line ) . "\n";
}
$data = str_replace( "\r" , "" , $data );

if ( $data == "" )
{
    $data = "\n(0) Records Found!\n";
}

header("Content-type: application/octet-stream");
header("Content-Disposition: attachment; filename=member_loan_report.xls");
header("Pragma: no-cache");
header("Expires: 0");
print "$header\n$data";

?>
