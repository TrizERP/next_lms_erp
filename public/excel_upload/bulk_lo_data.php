<?php
include('db.php');
require_once('PHPExcel.php');
session_start();

$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$user_id = $_REQUEST['user_id'];

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
        //echo '<pre>';
        //print_r($_REQUEST);
        //print_r($dataArr);

        mysqli_set_charset($cn, "utf8");

        $lo_records_uploaded = $lo_records_not_uploaded = $li_records_uploaded = $li_records_not_uploaded = 0;
        $li_problematic_records = $lo_problematic_records = "";

        //START Chapter and Topic array
        if ($_REQUEST['grade'] != "" && $_REQUEST['standard'] != "" && $_REQUEST['subject'] != "") {
            $getchapters = "SELECT id,chapter_name FROM chapter_master
              WHERE grade_id = '" . $_REQUEST['grade'] . "' AND standard_id = '" . $_REQUEST['standard'] . "'
              AND subject_id = '" . $_REQUEST['subject'] . "'
              AND sub_institute_id = '" . $_REQUEST['sub_institute_id'] . "'";
            $getchapters_result = mysqli_query($cn, $getchapters) or die(mysqli_error($cn));

            if (mysqli_num_rows($getchapters_result) > 0) {
                while ($value = mysqli_fetch_assoc($getchapters_result)) {
                    $chapter_array[$value['id']] = strtoupper($value['chapter_name']);
                }
            }

            foreach ($chapter_array as $chapter_id => $chapter_name) {
                $gettopics = "SELECT id,name FROM topic_master
                WHERE chapter_id = '" . $chapter_id . "'
                AND sub_institute_id = '" . $_REQUEST['sub_institute_id'] . "'";
                $gettopics_result = mysqli_query($cn, $gettopics) or die(mysqli_error($cn));

                if (mysqli_num_rows($gettopics_result) > 0) {
                    while ($value1 = mysqli_fetch_assoc($gettopics_result)) {
                        $topic_array[$chapter_id][$value1['id']] = strtoupper($value1['name']);
                    }
                }
            }
        }
        //END Chapter and Topic array
        // echo '<pre>';
        // print_r($chapter_array);
        // print_r($topic_array);
        // die;

        foreach ($dataArr as $key => $value) {
            $LO_return_array = $LI_return_array = array();

            if ($value['ChapterName'] != "" && $value['TopicName'] != "") {
                $chaptername = strtoupper($value['ChapterName']);
                $topicname = strtoupper($value['TopicName']);
                $LO = $value['LearningOutcome'];
                $LI = $value['LearningIndicator'];

                $chapter_id = array_search("$chaptername", $chapter_array);
                $topic_id = array_search("$topicname", $topic_array[$chapter_id]);

                if ($LO != "") {
                    if ($chapter_id != "" && $topic_id != "") {
                        $LO_return_array = insert_LO_LI("LO", $LO, $chaptername, $topicname, $chapter_id, $topic_id);

                        // echo '<pre>';
                        // echo "LO result<br>";
                        // print_r($LO_return_array);
                        if ($LO_return_array['PROBLEMATIC_RECORDS'] != "") {
                            $lo_problematic_records .= $LO_return_array['PROBLEMATIC_RECORDS'];
                        }
                        if ($LO_return_array['RECORDS_UPLOADED'] != 0) {
                            $lo_records_uploaded++;
                        }
                        if ($LO_return_array['RECORDS_NOT_UPLOADED'] != 0) {
                            $lo_records_not_uploaded++;
                        }
                    } else {
                        $lo_problematic_records .= "<span style='color:#b81ed4;'>Row No->" . ($key + 2) . " / Chapter Name ->" . $value['ChapterName'] . " / Topic Name->" . $value['TopicName'] . "</span><br><br>";
                        $lo_records_not_uploaded++;
                    }
                }


                if ($LI != "") {
                    if ($chapter_id != "" && $topic_id != "") {
                        $LI_return_array = insert_LO_LI("LI", $LI, $chaptername, $topicname, $chapter_id, $topic_id);

                        // echo '<pre>';
                        // echo "LI result<br>";
                        // print_r($LI_return_array);
                        if ($LI_return_array['PROBLEMATIC_RECORDS'] != "") {
                            $li_problematic_records .= $LI_return_array['PROBLEMATIC_RECORDS'];
                        }
                        if ($LI_return_array['RECORDS_UPLOADED'] != 0) {
                            $li_records_uploaded++;
                        }
                        if ($LI_return_array['RECORDS_NOT_UPLOADED'] != 0) {
                            $li_records_not_uploaded++;
                        }
                    } else {
                        $li_problematic_records .= "<span style='color:#b81ed4;'>Row No->" . ($key + 2) . " / Chapter Name ->" . $value['ChapterName'] . " / Topic Name->" . $value['TopicName'] . "</span><br><br>";
                        $li_records_not_uploaded++;
                    }
                }

            }
        }
    }

    echo "
    <h2>Upload Result</h2>
    <hr>
    <span class='dot' style='background-color:#b81ed4;'></span> Problem in Chapter Name or Topic Name
    <span class='dot' style='background-color:#0b08e6;'></span> Problem in Upload Query

    <h2 style='color:green'>LO Records</h2>
    <h4><span style='color:green'>LO Records Uploaded -  " . $lo_records_uploaded . "</span>
	  <br><br><span style='color:red'>LO Problematic Records -  " . $lo_records_not_uploaded . "</span>
	  <br><br>" . $lo_problematic_records . "
	  </h4>

    <h2 style='color:green'>LI Records</h2>
    <h4><span style='color:green'>LI Records Uploaded -  " . $li_records_uploaded . "</span>
    <br><br><span style='color:red'>LI Problematic Records -  " . $li_records_not_uploaded . "</span>
    <br><br>" . $li_problematic_records . "
    </h4>
    ";

}

function insert_LO_LI($mapping_type, $LO_LI, $chaptername, $topicname, $chapter_id, $topic_id)
{
    global $cn;

    $records_uploaded = $records_not_uploaded = 0;
    $problematic_records = "";

    //Check for existing LO master in lms_mapping_type table
    if ($mapping_type == "LO") {
        $mapping_type_name = "Learning Outcome";
    } elseif ($mapping_type == "LI") {
        $mapping_type_name = "Learning Indicator";
    }

    $lo_name = $mapping_type_name . ' - ' . $topicname;

    $checkQuery = "SELECT * FROM lms_mapping_type WHERE parent_id = 0 AND name = '" . $lo_name . "'
    AND chapter_id = '" . $chapter_id . "' AND topic_id ='" . $topic_id . "'";

    $checkQueryresult = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));
    if (mysqli_num_rows($checkQueryresult) == 0) {
        //START INSERT MASTER RECORD

        $insertQuery = "INSERT INTO lms_mapping_type(name,parent_id,globally,chapter_id,topic_id,status,created_at)
      values('" . $lo_name . "','0','0','" . $chapter_id . "','" . $topic_id . "','1',now())";

        $result = mysqli_query($cn, $insertQuery) or die(mysqli_error($cn));
        $lo_parent_id = mysqli_insert_id($cn);
        //END INSERT MASTER RECORD
    } else {
        $lo_master_data = mysqli_fetch_assoc($checkQueryresult);
        $lo_parent_id = $lo_master_data['id'];
    }

    //START INSERT LO RECORD
    $check = "SELECT * FROM lms_mapping_type WHERE name = '" . $LO_LI . "'
    AND parent_id = '" . $lo_parent_id . "' AND chapter_id = '" . $chapter_id . "' AND topic_id = '" . $topic_id . "'";
    $ck_result = mysqli_query($cn, $check) or die(mysqli_error($cn));
    if (mysqli_num_rows($ck_result) == 0) {
        $insertQuery = "INSERT INTO lms_mapping_type(name,parent_id,globally,chapter_id,topic_id,status,created_at)
        values('" . $LO_LI . "','" . $lo_parent_id . "','0','" . $chapter_id . "','" . $topic_id . "','1',now())";
        $result = mysqli_query($cn, $insertQuery) or die(mysqli_error($cn));
        //END INSERT LO RECORD
        if ($result) {
            $records_uploaded++;
        } else {
            $records_not_uploaded++;
            $problematic_records .= "<span style='color:#0b08e6;'>Row No->" . ($key + 2) . " / Chapter Name -  " . $chaptername . " / Topic Name -  " . $topicname . "LO/LI -  " . $LO_LI . "<span><br><br>";
        }
    }

    $return_array['RECORDS_UPLOADED'] = $records_uploaded;
    $return_array['RECORDS_NOT_UPLOADED'] = $records_not_uploaded;
    $return_array['PROBLEMATIC_RECORDS'] = $problematic_records;

    return $return_array;
}


$gradeArr = mysqli_query($cn, "SELECT * FROM academic_section WHERE sub_institute_id = '" . $sub_institute_id . "'");
?>
<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
    }

    * {
        box-sizing: border-box;
    }

    input[type=text], select, textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        margin-top: 6px;
        margin-bottom: 16px;
        resize: vertical;
    }

    input[type=submit] {
        background-color: #4CAF50;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    input[type=submit]:hover {
        background-color: #45a049;
    }

    a {
        font-size: 16px;
        color: black;
        font-weight: bold;
    }

    .container {
        border-radius: 5px;
        background-color: #f2f2f2;
        width: 50%;
    }

    .dot {
        height: 20px;
        width: 20px;
        background-color: #bbb;
        border-radius: 50%;
        display: inline-block;
    }
</style>
<div class="container">
    <form method="post" enctype="multipart/form-data">
        <div style="height:40px;background-color: #41b3f9;">
            <a style="float:right;" href="Sample_LO_LI_Upload.xlsx" download>Sample Excel File</a>
            <h4>Bulk LO / LI Upload</h4>
        </div>
        <br>
        <label for="grade"><b>Section</b></label>
        <select name="grade" id="grade" required>
            <option value=""> Select Section</option>
            <?php
            while ($value = mysqli_fetch_assoc($gradeArr)) {
                ?>
                <option value="<?php echo $value['id'] ?>"><?php echo $value['title'] ?></option>
                <?php
            }
            ?>
        </select>

        <label for="standard"><b>Standard</b></label>
        <select name="standard" id="standard" required>
            <option value=""> Select Standard</option>
            <?php
            while ($value = mysqli_fetch_assoc($gradeArr)) {
                ?>
                <option value="<?php echo $value['id'] ?>"><?php echo $value['title'] ?></option>
                <?php
            }
            ?>
        </select>

        <label for="subject"><b>Subject</b></label>
        <select name="subject" id="subject" required>
            <option value=""> Select Subject</option>
            <?php
            while ($value = mysqli_fetch_assoc($gradeArr)) {
                ?>
                <option value="<?php echo $value['id'] ?>"><?php echo $value['title'] ?></option>
                <?php
            }
            ?>
        </select>

        <label for="fname"><b>Excel File</b></label>
        <input type="file" name="filename" id="filename" required>
        <br><br>

        <input type="hidden" name="sub_institute_id" id="sub_institute_id" value="<?php echo $sub_institute_id; ?>">
        <input type="hidden" name="syear" id="syear" value="<?php echo $syear; ?>">
        <input type="hidden" name="user_id" id="user_id" value="<?php echo $user_id; ?>">

        <center><input type="submit" name="submit" class="btn_medium" value="UPLOAD"></center>
    </form>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
    $("#grade").change(function () {
        var sub_institute_id = $("#sub_institute_id").val();
        var grade = $("#grade").val();

        $.ajax({
            type: 'POST',
            url: "ajax.php",
            data: {
                'grade': grade,
                'sub_institute_id': sub_institute_id,
                'action': 'getStandard'
            },
            cache: false,
            success: function (result) {
                $("#standard").html(result);
            }
        });
    });

    $("#standard").change(function () {
        var sub_institute_id = $("#sub_institute_id").val();
        var standard = $("#standard").val();

        $.ajax({
            type: 'POST',
            url: "ajax.php",
            data: {
                'standard': standard,
                'sub_institute_id': sub_institute_id,
                'action': 'getSubject'
            },
            cache: false,
            success: function (result) {
                $("#subject").html(result);
            }
        });
    });

</script>
