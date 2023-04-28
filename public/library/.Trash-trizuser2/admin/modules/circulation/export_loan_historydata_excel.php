<?php
mysqli_connect("localhost", "root", "triz");
mysqli_select_db("school_library_final");
//include SENAYAN_BASE_DIR.'admin/modules/circulation/loan_list.php';

$select = "SELECT loan_id as LoanID,item_code as Barcode,member_id as MemberID ,DATE_FORMAT(loan_date,'%d-%m-%Y') as Loandate,DATE_FORMAT(due_date,'%d-%m-%Y') as Duedate,case renewed when '1' then 'Yes' else 'No' end as renewed,ifnull(return_date,'Not Returned Yet')  as return_date,time as Time from loan where member_id='" . $_REQUEST['memberid'] . "'";

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
