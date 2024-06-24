<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
 error_reporting(0);
include('db.php');

require_once('PHPExcel.php');
session_start();
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
            $relationTable[$value["table_field"]]['main_table'] = $value["table_name"];
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
        $all_error = array();
// echo "<pre>";print_r($relationTable);exit;
        foreach ($dataArr as $key => $value) {
            $valueQuery .= ' (';
            
            foreach ($valueFields1 as $kvf => $valueFields) {
                /*if ($valueFields['field'] == "SYEAR" || $valueFields['field'] == "syear") {
                    $valueQuery .= "'2023',";
                } else */
                if ($valueFields['field'] == "SCHOOL_ID" || $valueFields['field'] == "school_id") {
                    $valueQuery .= "'" . $_SESSION['SUB_INSTITUTE_ID'] . "',";
                } 
                else if($valueFields['field']=="user_code"){
                   $valueQuery.= "'".PHPExcel_Style_NumberFormat::toFormattedString($value[$valueFields['field']], '0000'). "',";
                //    echo "<pre>";print_r($valueQuery);
                }
                else if($valueFields['field']=="user_id" && $relationTable[$valueFields['field']]['main_table']==="hrms_emp_leaves"){
                    $valueQuery.= "'".PHPExcel_Style_NumberFormat::toFormattedString($value[$valueFields['field']], '0000'). "',";
                 //    echo "<pre>";print_r($valueQuery);
                 }
                else if ($valueFields['field'] == "SUB_INSTITUTE_ID" || $valueFields['field'] == "sub_institute_id" || $valueFields['field'] == "sub_inst_id") {
                    $valueQuery .= "'" . $_SESSION['SUB_INSTITUTE_ID'] . "',";
                } else if ($valueFields['field'] == "MARKING_PERIOD_ID" || $valueFields['field'] == "marking_period_id" || $valueFields['field'] == "term_id") {
                    $valueQuery .= "'5',";
                } else if ($valueFields['field'] == "CREATED_BY" || $valueFields['field'] == "created_by" || $valueFields['field'] == "status") {
                    $valueQuery .= "'1',";
                } else if ($valueFields['field'] == "CREATED_ON" || $valueFields['field'] == "created_on") {
                    $valueQuery .= "now(),";
                } else if ($valueFields['field'] == "CREATED_IP_ADDRESS" || $valueFields['field'] == "created_ip_address") {
                    $valueQuery .= "'" . $_SERVER['REMOTE_ADDR'] . "',";
                } else if ($valueFields['field'] == "admission_date" || $valueFields['field'] == "ADMISSION_DATE" || $valueFields['field'] == "dob" || $valueFields['field'] == "DOB" || $valueFields['field'] == "start_date" || $valueFields['field'] == "followup_date" || $valueFields['field'] == "date_of_birth" || $valueFields['field'] == "birthdate" || $valueFields['field'] == "from_date" || $valueFields['field'] == "to_date" || $valueFields['field'] == "day" || $valueFields['field'] == "punchin_time" || $valueFields['field'] == "punchout_time" || $valueFields['field'] == "receiptdate" || $valueFields['field'] == "attendance_date" || $valueFields['field'] == "issued_date" || $valueFields['field'] == "due_date" || $valueFields['field'] == "return_date") {
                    // || $valueFields['field'] == "issued_date" || $valueFields['field'] == "due_date" || $valueFields['field'] == "return_date"
                    $excelDateTime = $value[$valueFields['field']];

                    if ($valueFields['field'] == "punchin_time" || $valueFields['field'] == "punchout_time" || $valueFields['field'] == "issued_date" || $valueFields['field'] == "return_date") {
                        $timestamp = PHPExcel_Style_NumberFormat::toFormattedString($excelDateTime, 'YYYY-MM-DD HH:MM:SS'); // Convert Excel timestamp to formatted date and time string
                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
                        if($date !== false){ // Create a DateTime object using the formatted string
                            $valueQuery .= "'" . $date->format('Y-m-d H:i:s'). "',"; // Output the formatted date and time
                        }else{
                            $valueQuery .= "'".$value[$valueFields['field']] . "',";
                        }
                    } else {
                        $valueQuery .= "'" . date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($value[$valueFields['field']])) . "',";
                    }
                } else {
                    if (in_array($valueFields['field'], $relationFields)) {
                        if($relationTable[$valueFields['field']]['main_table']==="fees_breakoff_other"){
                        $relationQuery = "SELECT " . strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']) . " FROM " . $relationTable[$valueFields['field']]['TABLE_NAME'] . "  WHERE  " . $relationTable[$valueFields['field']]['TABLE_FIELD'] . " = '" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "' AND sub_institute_id = '" . $_SESSION['SUB_INSTITUTE_ID'] . "'"; 
                         
                        }
                       else{
                           
                        // $relationQuery = "SELECT " . strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']) . " FROM " . $relationTable[$valueFields['field']]['TABLE_NAME'] . "  WHERE  " . $relationTable[$valueFields['field']]['TABLE_FIELD'] . " = '" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "' AND sub_institute_id = '" . $_SESSION['SUB_INSTITUTE_ID'] . "'"; 
                            
                             if($valueFields['field']=='user_id' && $relationTable[$valueFields['field']]['main_table']=="hrms_emp_leaves"){

                                $relationQuery = "SELECT " . strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']) . " FROM " . $relationTable[$valueFields['field']]['TABLE_NAME'] . " WHERE " . $relationTable[$valueFields['field']]['TABLE_FIELD'] . " = '" . mysqli_real_escape_string($cn, PHPExcel_Style_NumberFormat::toFormattedString($value[$valueFields['field']], '0000')) . "'";
 
                             }else{
                                $relationQuery = "SELECT " . strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']) . " FROM " . $relationTable[$valueFields['field']]['TABLE_NAME'] . " WHERE " . $relationTable[$valueFields['field']]['TABLE_FIELD'] . " = '" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "'";
 
                             }
                        }
                      

                        if (isset($relationTable[$valueFields['field']]['SUB_INSTITUTE_COLUMN'])) {
                            $relationQuery .= " AND " . $relationTable[$valueFields['field']]['SUB_INSTITUTE_COLUMN'] . " = '" . $_SESSION['SUB_INSTITUTE_ID'] . "'";
                        }
                        // print_r($relationQuery);die();
                        
                        $checkSubInstitute = mysqli_query($cn, "SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME IN ('sub_institute_id','SUB_INSTITUTE_ID') AND TABLE_SCHEMA='triz_erp_21' AND TABLE_NAME = '" . $relationTable[$valueFields['field']]['TABLE_NAME'] . "'");
                        if (mysqli_num_rows($checkSubInstitute) > 0) {
                            $relationQuery .= "   AND (SUB_INSTITUTE_ID = '" . $_SESSION['SUB_INSTITUTE_ID'] . "' OR SUB_INSTITUTE_ID IS NULL OR SUB_INSTITUTE_ID = 0) ";
                        }
                        
                        $getRelationValue = mysqli_fetch_assoc(mysqli_query($cn, $relationQuery));
                        // ECHO "<pre>";print_r($getRelationValue);
                        $keyId = strtoupper($relationTable[$valueFields['field']]['INSERT_FIELD']);
                        // print_r($getRelationValue);
                        //|| $relationTable[$valueFields['field']]['main_table']=="library_book_circulations"
         
                        if (isset($getRelationValue) && count($getRelationValue) > 0) {                            
                            $finalValue = $getRelationValue[$keyId];
                            $valueQuery .= "'" . $finalValue . "',";
                            // echo $finalValue;
                        } else {
                            // echo "error ".$value[$valueFields['field']];
                            
                            if($relationTable[$valueFields['field']]['main_table']=="fees_breakoff_other"){
                                 $valueQuery .="'" . $value[$valueFields['field']] . "',";
                            }if($relationTable[$valueFields['field']]['main_table']=="library_book_circulations"){
                                $valueQuery .="'no_value',";
                                echo "<h5 style='color:red'>Problem Occurred while upload for <b style='color:black'>" . $valueFields['field'] . "</b> when value is <b style='color:black'>" . $value[$valueFields['field']] . "</b> please check and reupload the file ON LINE ".$key.".</h5>";
                                $all_error[$valueFields['field']][] =$value[$valueFields['field']];
                           }else{
                                    // echo "<h5 style='color:red'>Problem Occurred while upload for <b style='color:black'>" . $valueFields['field'] . "</b> when value is <b style='color:black'>" . $value[$valueFields['field']] . "</b> please check and reupload the file ON LINE ".$key.".</h5>";
                                    $valueQuery .="'no_value',";
                                    echo "<h5 style='color:red'>Problem Occurred while upload for <b style='color:black'>" . $valueFields['field'] . "</b> when value is <b style='color:black'>" . $value[$valueFields['field']] . "</b> please check and reupload the file ON LINE ".$key.".</h5>";
                                    $all_error[$valueFields['field']][] =$value[$valueFields['field']];
                            }
                           
                        }
                    } else {
                        if(!isset($value[$valueFields['field']])){
                        echo "<h5 style='color:red'> <b style='color:black'>" . $valueFields['field'] . "</b> While Uploading Field is required </h5>";
                        exit;
                        }else{
                            $valueQuery .= "'" . mysqli_real_escape_string($cn, $value[$valueFields['field']]) . "',";
                        }
                    }
                }
               
                // echo ;
            }
            // exit;
            $valueQuery = $valueQuery.$_SESSION['SUB_INSTITUTE_ID'];
            $valueQuery .= ' ),';
        }
      if($_REQUEST['table']=="library_book_circulations"){
        $valueQuery = rtrim($valueQuery, ',');
        $rows = explode('),', $valueQuery);
        $newRows = [];
        foreach ($rows as $row) {
            if (strpos($row, "'no_value'") === false) {
                $newRows[] = $row . ')';
            }
        }
        
        // Join the modified rows back into a string
        $valueQuery = implode(',', $newRows);
        
        // Ensure there is only one ')' at the end
        $valueQuery = rtrim($valueQuery, ')') . ')';
        
        // echo $valueQuery;
             
    }else{
        // Remove trailing comma
        $valueQuery = rtrim($valueQuery, ',');
    }
    // echo "<pre>";print_r($valueQuery);
    // exit;
// echo $valueQuery;exit;
        // echo  $valueQuery ."<br/><br/>"; 
       $query = mysqli_query($cn, $insertQuery . $fieldQuery . $valueQuery) or die(mysqli_error($cn));
       if($query==true){
        echo "<h4 style='color:green'>Data Imported Successfully.</h4>";
       }else{
        echo "<h4 style='color:red'>Data Import Failed.</h4>";           
       }
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
        <option value="<?php echo $value['table_name'] ?>">
            <?php echo $value['display_table_name'] ?>
        </option>
        <?php
        }
        ?>
    </select>

    <input type="file" name="filename" id="filename">
    <input type="submit" name="submit" class="btn_medium" value="UPLOAD">
</form>