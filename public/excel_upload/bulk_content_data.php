<?php
include 'db.php';
require_once 'PHPExcel.php';
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
            $objReader->setReadDataOnly(true);
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
            // die('here');

            $records_uploaded = $records_not_uploaded = 0;
            $problematic_reocords = "";

            foreach ($full_array as $key => $subvalue) {
                foreach ($subvalue as $skey => $svalue) {
                    if (!empty($titleArr[$skey])) {
                        if (gettype($svalue) == 'boolean') {
                            $svalue = $svalue == 1 ? 'TRUE' : 'FALSE';
                        }
                        $dataArr[$key][$titleArr[$skey]] = $svalue;
                    }
                }
            }
        }

        $chapter_create = $records_uploaded = $records_not_uploaded = 0;
        $problematic_reocords = "";

        // new Create Chapter array
        $newCreateChapter = [];

        foreach ($dataArr as $key => $value) {
            // get Chapter ID
            $chapter_id = $value['chapter_id'];


            // Chapter ID exists
            if ($chapter_id == '') {
                $find_chapter_id = array_search($value['chapter'], $newCreateChapter);
                if ($find_chapter_id) {
                    $chapter_id = $find_chapter_id;
                } else {
                    $query = "INSERT INTO chapter_master (syear, sub_institute_id, grade_id, standard_id, subject_id, chapter_name, created_by)
                    VALUES ( '" . $_REQUEST['syear'] . "', '" . $_REQUEST['sub_institute_id'] . "', '" . mysqli_real_escape_string($cn, $value['grade_id']) . "', '" . $value['standard_id'] . "', '" . mysqli_real_escape_string($cn, $value['subject_id']) . "', '" . mysqli_real_escape_string($cn, $value['chapter']) . "', '" . $_REQUEST['user_id'] . "' );";

                    $addChapter = mysqli_query($cn, $query);

                    if ($addChapter) {
                        $chapter_create++;
                        $chapter_id = mysqli_insert_id($cn);

                        $newCreateChapter[$chapter_id] = $value['chapter'];
                    }
                }
            }

            $contentQuery = "INSERT INTO content_master (syear, sub_institute_id, grade_id, standard_id, subject_id, chapter_id, title, file_folder, filename, file_type, file_size, url ) VALUES ( '" . $_REQUEST['syear'] . "', '" . $_REQUEST['sub_institute_id'] . "', '" . mysqli_real_escape_string($cn, $value['grade_id']) . "', '" . mysqli_real_escape_string($cn, $value['standard_id']) . "', '" . mysqli_real_escape_string($cn, $value['subject_id']) . "', '" . $chapter_id . "', '" . mysqli_real_escape_string($cn, $value['title']) . "', '" . mysqli_real_escape_string($cn, $value['file_folder']) . "', '" . mysqli_real_escape_string($cn, $value['filename']) . "', '" . mysqli_real_escape_string($cn, $value['file_type']) . "', '" . mysqli_real_escape_string($cn, $value['file_size']) . "', '" . mysqli_real_escape_string($cn, $value['url']) . "')";

            $contentInsert = mysqli_query($cn, $contentQuery);

            if ($contentInsert) {
                $records_uploaded++;
            } else {
                $records_not_uploaded++;
                $problematic_reocords .= "Question -  " . $value['Question'] . "<br><br>";
            }
        }
        echo "<h4 style='color:green'>Records Uploaded -  " . $records_uploaded . "
    <br><br><span style='color:green'>Create Chapter Records -  " . $chapter_create . "</span>
	<br><br><span style='color:red'>Problematic Records -  " . $records_not_uploaded . "</span>
    <br><br><span style='color:green'><br><br>" . $problematic_reocords . "</span>
	</h4>";
    }
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

    input[type=text],
    select,
    textarea {
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
</style>

<div class="container">
    <form method="post" enctype="multipart/form-data">
        <div style="height:40px;background-color: #41b3f9;">
            <a style="float:right;" href="SampleContentUpload.xlsx" download>Sample Excel File</a>
            <h4>Bulk Question Upload</h4>
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
        </select>

        <!-- <label for="subject"><b>Subject</b></label>
        <select name="subject" id="subject" required>
            <option value=""> Select Subject </option>
        </select> -->

        <!-- <label for="chapter"><b>Chapter</b></label>
        <select name="chapter" id="chapter" required>
            <option value=""> Select Chapter </option>
        </select>

        <label for="topic"><b>Topic</b></label>
        <select name="topic" id="topic" required>
            <option value=""> Select Topic </option>
        </select> -->


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

    $("#subject").change(function () {
        var sub_institute_id = $("#sub_institute_id").val();
        var subject = $("#subject").val();
        var standard = $("#standard").val();
        var syear = $("#syear").val();

        $.ajax({
            type: 'POST',
            url: "ajax.php",
            data: {
                'subject': subject,
                'standard': standard,
                'sub_institute_id': sub_institute_id,
                'syear': syear,
                'action': 'getChapter'
            },
            cache: false,
            success: function (result) {
                $("#chapter").html(result);
            }
        });
    });
    $("#chapter").change(function () {
        var sub_institute_id = $("#sub_institute_id").val();
        var subject = $("#subject").val();
        var standard = $("#standard").val();
        var chapter = $("#chapter").val();
        //var chapter = $(this).val();
        //var topic= $("#topic").val();

        $.ajax({
            type: 'POST',
            url: "ajax.php",
            data: {
                'subject': subject,
                'standard': standard,
                //'topic' :topic,
                'chapter': chapter,
                'sub_institute_id': sub_institute_id,
                'action': 'getTopic'
            },
            cache: false,
            success: function (result) {
                $("#topic").html(result);
            }
        });
    });
</script>
