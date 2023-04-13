<?php
include('db.php');
require_once('PHPExcel.php');
session_start();

// if($_REQUEST['modfunc'] == "SAVE"){
// 	$random_num = rand(1, 50000);
//     $file_name = $random_num . "-" . str_replace(" ", _, $_FILES["file"]["name"]);
//     $target_path = 'assets/import_xlsx/' . $file_name;
//     $tmpFilePath = $_FILES['file']['tmp_name'];
//     echo move_uploaded_file($tmpFilePath, $target_path);

//     $inputFileType = PHPExcel_IOFactory::identify($_FILES['file']['tmp_name']);
//     $objReader = PHPExcel_IOFactory::createReader($inputFileType);
//     $objPHPExcel = $objReader->load($_FILES['file']['tmp_name']);

//     $worksheet = $objPHPExcel->getSheet(0);
//     $highestRow = $worksheet->getHighestRow();
//     $highestColumn = $worksheet->getHighestColumn();
//     $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
// }

if (isset($_REQUEST['submit'])) {
    $allowed = array('xlsx', 'xls');
    $filename = $_FILES['filename']['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);

    if (in_array($ext, $allowed)) {
        if (is_uploaded_file($_FILES['filename']['tmp_name'])) {
            $inputFileType = PHPExcel_IOFactory::identify($_FILES['filename']['tmp_name']);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($_FILES['filename']['tmp_name']);

            $worksheet = $objPHPExcel->getSheet(0);
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
            $full_array = array();
            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 0; $col < $highestColumnIndex; $col++) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row);
                    $val = $cell->getValue();
                    $full_array[$row][$col] = $val;
                }
            }
            $titleArr = $full_array[1];
            $titleArr = array_filter($titleArr);
            //$_SESSION['csv_header_raw'] = $titleArr;

            $finalFields = array();
            $finalMonth = array();
            foreach ($full_array[1] as $ke => $ve) {
                if ($ke > 10) {
                    $feve = explode("||", $ve);
                    // $finalFields[$feve[1]] = $feve[1];
                    $finalMonth[$feve[0]][] = $feve[1];
                } else {
                    $finalFields[$ve] = $ve;
                }
            }
            echo "<pre>";
            $firstArray = $full_array[1];
            // die;
            // print_r($finalMonth);
            unset($full_array[1]);

            $query = "INSERT INTO fees_collect ( ";
            foreach ($finalFields as $key => $value) {
                $query .= $value . ",";
            }


            $dataArr = array();
            $full_array = array_values($full_array);
            // echo "<pre>";
            // print_r($firstArray);
            // print_r($full_array);
            foreach ($full_array as $key => $value) {
                $jordarArray = array();
                foreach ($value as $k => $v) {
                    // if($v != "0")
                    // {
                    $jordarArray[$firstArray[$k]] = $v;
                    // }
                }
                $vQuery = '';
                $nn = 1;
                print_r($jordarArray);
                foreach ($jordarArray as $n => $l) {
                    $lQueury = '';
                    $iQueury = '';
                    $totalAmount = 0;
                    if ($nn < 12) {
                        if ($n == "cheque_date" || $n == "receiptdate") {
                            $vQuery .= "'" . date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($l)) . "',";
                        } else {
                            $vQuery .= "'" . $l . "',";
                        }
                    } else {
                        $le = explode("||", $n);
                        // echo "<pre>".$le[0];
                        // print_r($finalMonth[$le[0]]);
                        foreach ($finalMonth[$le[0]] as $f1 => $v1) {
                            $iQueury .= $v1 . ',';
                            $lQueury .= $jordarArray[$le[0] . "||" . $v1] . ",";
                            $totalAmount += $jordarArray[$le[0] . "||" . $v1];
                        }
                        $lQueury .= $le[0] . "," . $totalAmount . ")";
                        // unset($finalMonth[$le[0]]);
                        if ($iQueury != '') {
                            $checkEntery = "select * from fees_collect where term_id = '" . $le[0] . "' and student_id = '" . $jordarArray['student_id'] . "' and amount = '" . $totalAmount . "'";
                            $checkEntery = mysqli_query($cn, $checkEntery);
                            if (mysqli_num_rows($checkEntery) == 0) {
                                if ($totalAmount > 0) {
                                    echo mysqli_query($cn, $query . $iQueury . "term_id,amount) VALUES (" . $vQuery . $lQueury);
                                    echo "<br>";
                                    echo "<br>";
                                }
                            }
                        }
                        // die;

                    }
                    $nn++;
                }
                // print_r($jordarArray);
                // echo $query.$vQuery;


            }

        }
    }
}

$getTables = mysqli_query($cn, "SELECT * FROM import_table_fields group by table_name order by id");
?>
<form method="post" enctype="multipart/form-data">
    <select name="table" id="table">
        <option value=""> Select Module</option>
        <?php
        while ($value = mysqli_fetch_assoc($getTables)) {
            ?>
            <option value="<?php echo $value['table_name'] ?>"><?php echo $value['display_table_name'] ?></option>
            <?php
        }
        ?>
    </select>

    <input type="file" name="filename" id="filename">

    <input type="submit" name="submit" class="btn_medium" value="UPLOAD">
</form>
