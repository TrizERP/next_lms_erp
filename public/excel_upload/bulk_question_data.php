<?php
include 'db.php';
require_once 'PHPExcel.php';
session_start();

$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$user_id = $_REQUEST['user_id'];
$topic_id = isset($_REQUEST['topic']) ? $_REQUEST['topic'] : 0;

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

        $records_uploaded = $records_not_uploaded = 0;
        $problematic_reocords = "";

        foreach ($dataArr as $key => $value) {

            if ($value['Question'] != "") {

                //$url = 'https://getbloomslevel-o76ko55i2a-el.a.run.app';
                $url = 'https://getbloomslevel-gyzqqaohja-el.a.run.app';
                $headers = ['Accept: application/json'];

                $fields = ['str' => $value['Question']];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

                $result = json_decode(curl_exec($ch));
                // echo '$result : '.$result;
                // echo '<pre>';
                // print_r(curl_getinfo($ch));
                // print_r($result);
                // die();
                curl_close($ch);

                $bloom_texonomy = $value['QuestionCategory'];
                if ($result) {
                    $bloom_texonomy = $result->prediction;
                }

                $checkQuery = "SELECT * FROM lms_question_master WHERE question_title = '" . mysqli_real_escape_string($cn, $value['Question']) . "'
				AND grade_id = '" . $_REQUEST['grade'] . "' AND standard_id = '" . $_REQUEST['standard'] . "'
				AND sub_institute_id = '" . $_REQUEST['sub_institute_id'] . "' AND chapter_id =  '" . $_REQUEST['chapter'] . "'";
                $result = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));

                if (mysqli_num_rows($result) == 0) {
                    //START Get Question Type ID
                    $questionType = "SELECT * FROM question_type_master WHERE question_type = '" . mysqli_real_escape_string($cn, $value['QuestionType']) . "'";
                    $questionTypeArr = mysqli_query($cn, $questionType) or die(mysqli_error($cn));
                    $question_type_id = mysqli_fetch_assoc($questionTypeArr);
                    $question_type_id = $question_type_id['id'];
                    //END Get Question Type ID

                    //START Get Question Level ID - LMS Mapping type is Difficulty Level
                    $questionLevel = "SELECT * FROM lms_mapping_type WHERE name = '" . mysqli_real_escape_string($cn, $value['QuestionLevel']) . "'
					AND parent_id = 9";
                    $questionLevelArr = mysqli_query($cn, $questionLevel) or die(mysqli_error($cn));
                    $question_level_id = mysqli_fetch_assoc($questionLevelArr);
                    $question_level_id = $question_level_id['id'];
                    //END Get Question Level ID

                    //START Get Question Category ID - LMS Mapping type is Blooms Taxonomy
                    $questionCategory = "SELECT * FROM lms_mapping_type WHERE name = '" . mysqli_real_escape_string($cn, $bloom_texonomy) . "'
					AND parent_id = 82";

                    $questionCategoryArr = mysqli_query($cn, $questionCategory) or die(mysqli_error($cn));
                    $question_category_id = mysqli_fetch_assoc($questionCategoryArr);
                    $question_category_id = $question_category_id['id'];
                    //END Get Question Category ID

                    $insertQuesQuery = "INSERT INTO lms_question_master(question_type_id,grade_id,standard_id,subject_id,chapter_id,topic_id,question_title,description,points,multiple_answer,sub_institute_id,status,created_by,created_on,hint_text)
					VALUES('" . $question_type_id . "','" . $_REQUEST['grade'] . "','" . $_REQUEST['standard'] . "',
					'" . $_REQUEST['subject'] . "','" . $_REQUEST['chapter'] . "','" . $topic_id . "','" . mysqli_real_escape_string($cn, $value['Question']) . "','" . mysqli_real_escape_string($cn, $value['Description']) . "','" . $value['Points'] . "','0','" . $_REQUEST['sub_institute_id'] . "','1','" . $_REQUEST['user_id'] . "',now(),'" . mysqli_real_escape_string($cn, $value['Hint']) . "')";
                    $result = mysqli_query($cn, $insertQuesQuery) or die(mysqli_error($cn));
                    $question_id = mysqli_insert_id($cn);

                    //INSERT LMS Mapping Difficulty Level
                    $insertQueryDL = "INSERT INTO lms_question_mapping(questionmaster_id,mapping_type_id,mapping_value_id)
							VALUES('" . $question_id . "','9','" . $question_level_id . "')";
                    mysqli_query($cn, $insertQueryDL) or die(mysqli_error($cn));

                    //INSERT LMS Mapping Blooms Taxonomy
                    $insertQueryBT = "INSERT INTO lms_question_mapping(questionmaster_id,mapping_type_id,mapping_value_id)
							VALUES('" . $question_id . "','82','" . $question_category_id . "')";
                    mysqli_query($cn, $insertQueryBT) or die(mysqli_error($cn));

                    //START Insert multiple answer
                    for ($i = 1; $i <= 4; $i++) {
                        $option = 'Options' . $i;
                        if ($value[$option] != "") {
                            $correct_answer = 0;
                            if ($value['RightOption'] == $i) {
                                $correct_answer = 1;
                            }
                            $insertQuery = "INSERT INTO answer_master(question_id,answer,correct_answer,sub_institute_id,created_by,created_on)
							VALUES('" . $question_id . "','" . mysqli_real_escape_string($cn, $value[$option]) . "','" . $correct_answer . "',
							'" . $_REQUEST['sub_institute_id'] . "','" . $_REQUEST['user_id'] . "',now())";
                            mysqli_query($cn, $insertQuery) or die(mysqli_error($cn));
                        }
                    }
                    //END Insert multiple answer

                    if ($result) {
                        $records_uploaded++;
                    }
                } else {
                    $records_not_uploaded++;
                    $problematic_reocords .= "Question -  " . $value['Question'] . "<br><br>";
                }
            }
        }

    }

    echo "<h4 style='color:green'>Records Uploaded -  " . $records_uploaded . "
	<br><br><span style='color:red'>Problematic Records -  " . $records_not_uploaded . "
	<br><br>" . $problematic_reocords . "</span>
	</h4>";

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
            <a style="float:right;" href="SampleQuestionUpload.xlsx" download>Sample Excel File</a>
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

        <label for="subject"><b>Subject</b></label>
        <select name="subject" id="subject" required>
            <option value=""> Select Subject</option>
        </select>

        <label for="chapter"><b>Chapter</b></label>
        <select name="chapter" id="chapter" required>
            <option value=""> Select Chapter</option>
        </select>
        <!--
                <label for="topic"><b>Topic</b></label>
                <select name="topic" id="topic">
                    <option value=""> Select Topic </option>
                </select>
        -->

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
