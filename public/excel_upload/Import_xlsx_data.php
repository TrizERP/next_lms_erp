<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
 error_reporting(0);
include('db.php');
require_once('PHPExcel.php');
session_start();
//echo "<pre>";
//print_r($_REQUEST);
//print_r($_SESSION);
//die();

// if($_REQUEST['modfunc'] == "SAVE"){
//  $random_num = rand(1, 50000);
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

            unset($full_array[1]);

            $dataArr = array();
            $full_array = array_values($full_array);

            foreach ($full_array as $key => $subvalue) {
                foreach ($subvalue as $skey => $svalue) {
                    if (!empty($titleArr[$skey])) {
                        $dataArr[$key][$titleArr[$skey]] = trim($svalue);
                    }
                }
            }

        }
        $relationFields = array();
        $relationTable = array();
        $getRelations = mysqli_query($cn, "SELECT * FROM relation_table_fields WHERE table_name = '" . $_REQUEST['table'] . "'");
        while ($value = mysqli_fetch_assoc($getRelations)) {
            $relationFields[] = $value["table_field"];
            $relationTable[$value["table_field"]]['TABLE_NAME'] = $value["relation_table_name"];
            $relationTable[$value["table_field"]]['TABLE_FIELD'] = $value["relation_table_field"];
            $relationTable[$value["table_field"]]['INSERT_FIELD'] = $value["relation_table_id"];
        }

        $getFields = mysqli_query($cn, "SELECT FIELD FROM import_table_fields where display_status=1 and table_name = '" . $_REQUEST['table'] . "' order by id asc");

        $insertQuery = "  INSERT INTO " . $_REQUEST['table'] . " (";

        $fieldQuery = '';

        $valueQuery = '';

        $valueFields1 = array();
        $vf = 1;
        while ($value = mysqli_fetch_assoc($getFields)) {
            $fieldQuery .= $value['FIELD'] . ',';
            $valueFields1[$vf]['field'] = $value['FIELD'];
            $vf++;
        }

        //$fieldQuery = rtrim($fieldQuery, ',');
        $fieldQuery = $fieldQuery.'SUB_INSTITUTE_ID';
        $fieldQuery .= ' ) VALUES ';

        foreach ($dataArr as $key => $value) {
            $valueQuery .= ' (';
            foreach ($valueFields1 as $kvf => $valueFields) {
                /*if ($valueFields['field'] == "SYEAR" || $valueFields['field'] == "syear") {
                    $valueQuery .= "'2023',";
                } else */
                if ($valueFields['field'] == "SCHOOL_ID" || $valueFields['field'] == "school_id") {
                    $valueQuery .= "'" . $_SESSION['SUB_INSTITUTE_ID'] . "',";
                } else if ($valueFields['field'] == "SUB_INSTITUTE_ID" || $valueFields['field'] == "sub_institute_id" || $valueFields['field'] == "sub_inst_id") {
                    $valueQuery .= "'" . $_SESSION['SUB_INSTITUTE_ID'] . "',";
                } else if ($valueFields['field'] == "MARKING_PERIOD_ID" || $valueFields['field'] == "marking_period_id" || $valueFields['field'] == "term_id") {
                    $valueQuery .= "'5',";
                } else if ($valueFields['field'] == "CREATED_BY" || $valueFields['field'] == "created_by" || $valueFields['field'] == "status") {
                    $valueQuery .= "'1',";
                } else if ($valueFields['field'] == "CREATED_ON" || $valueFields['field'] == "created_on") {
                    $valueQuery .= "now(),";
                } else if ($valueFields['field'] == "CREATED_IP_ADDRESS" || $valueFields['field'] == "created_ip_address") {
                    $valueQuery .= "'" . $_SERVER['REMOTE_ADDR'] . "',";
                } else if ($valueFields['field'] == "admission_date" || $valueFields['field'] == "ADMISSION_DATE" || $valueFields['field'] == "dob" || $valueFields['field'] == "DOB" || $valueFields['field'] == "start_date" || $valueFields['field'] == "followup_date" || $valueFields['field'] == "date_of_birth" || $valueFields['field'] == "birthdate" ||$valueFields['field'] == "from_date"||$valueFields['field'] == "to_date" || $valueFields['field'] == "day" || $valueFields['field'] == "punchin_time"|| $valueFields['field'] == "punchout_time" ) {
    
                    $excelDateTime = $value[$valueFields['field']];

                    if ($valueFields['field'] == "punchin_time" || $valueFields['field'] == "punchout_time") {
                        $timestamp = PHPExcel_Style_NumberFormat::toFormattedString($excelDateTime, 'YYYY-MM-DD HH:MM:SS'); // Convert Excel timestamp to formatted date and time string
                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp); // Create a DateTime object using the formatted string
                        $valueQuery .= "'" . $date->format('Y-m-d H:i:s'). "',"; // Output the formatted date and time
                    } else {
                        $valueQuery .= "'" . date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($value[$valueFields['field']])) . "',";
                    }
                    // echo $valueQuery."<br>";
                } else {
                    if (in_array($valueFields['field'], $relationFields)) {
                        // $relationQuery = "SELECT " . strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']) . " FROM " . $relationTable[$valueFields['field']]['TABLE_NAME'] . "  WHERE  " . $relationTable[$valueFields['field']]['TABLE_FIELD'] . " = '" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "' AND sub_institute_id = '" . $_SESSION['SUB_INSTITUTE_ID'] . "'";
                        $relationQuery = "SELECT " . strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']) . " FROM " . $relationTable[$valueFields['field']]['TABLE_NAME'] . " WHERE " . $relationTable[$valueFields['field']]['TABLE_FIELD'] . " = '" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "'";

                        if (isset($relationTable[$valueFields['field']]['SUB_INSTITUTE_COLUMN'])) {
                            $relationQuery .= " AND " . $relationTable[$valueFields['field']]['SUB_INSTITUTE_COLUMN'] . " = '" . $_SESSION['SUB_INSTITUTE_ID'] . "'";
                        }
                        // print_r($relationQuery);die();
                        
                        $checkSubInstitute = mysqli_query($cn, "SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('sub_institute_id','SUB_INSTITUTE_ID') AND TABLE_SCHEMA='triz_erp_21' AND TABLE_NAME = '" . $relationTable[$valueFields['field']]['TABLE_NAME'] . "'");
                        if (mysqli_num_rows($checkSubInstitute) > 0) {
                            $relationQuery .= "   AND (SUB_INSTITUTE_ID = '" . $_SESSION['SUB_INSTITUTE_ID'] . "' OR SUB_INSTITUTE_ID IS NULL OR SUB_INSTITUTE_ID = 0) ";
                        }
                        // print_r($getRelationValue);die();
                        
                        $getRelationValue = mysqli_fetch_assoc(mysqli_query($cn, $relationQuery));
                        $keyId = strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']);
                        
                        if (isset($getRelationValue) && count($getRelationValue) > 0) {
                        // echo "<pre>";print_r($value[$valueFields['field']]);
                            
                            $finalValue = $getRelationValue[$keyId];
                            $valueQuery .= "'" . $finalValue . "',";
                        } else {
                        // print_r($value[$valueFields['field']]);die();
                            echo "<h5 style='color:red'>Problem Occurred while upload for <b style='color:black'>" . $valueFields['field'] . "</b> when value is <b style='color:black'>" . $value[$valueFields['field']] . "</b> please check and reupload the file.</h5>";
                            // die;
                        }
                    } else {
                        $valueQuery .= "'" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "',";
                    }
                }
            }
            //$valueQuery = rtrim($valueQuery, ',');
            $valueQuery = $valueQuery.$_SESSION['SUB_INSTITUTE_ID'];
            $valueQuery .= ' ),';
        }
// die();
        $valueQuery = rtrim($valueQuery, ',');
        //echo $insertQuery.$fieldQuery.$valueQuery;
        mysqli_query($cn, $insertQuery . $fieldQuery . $valueQuery) or die(mysqli_error($cn));
        echo "<h4 style='color:green'>Data Imported Successfully.</h4>";
    }
}


$getTables = mysqli_query($cn, "SELECT * FROM import_table_fields where display_status=1 group by table_name order by id");
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
