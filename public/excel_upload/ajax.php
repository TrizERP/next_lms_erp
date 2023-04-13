<?php
include('db.php');

if ($_POST['action'] == 'getStandard') {

    $grade = isset($_REQUEST['grade']) ? $_REQUEST['grade'] : 0;
    $sub_institute_id = isset($_REQUEST['sub_institute_id']) ? $_REQUEST['sub_institute_id'] : 0;

    $stdArr = mysqli_query($cn, "SELECT * FROM standard WHERE sub_institute_id = '" . $sub_institute_id . "' AND grade_id = '" . $grade . "'");

    $response = '<option value="">Select Standard</option>';
    while ($data = mysqli_fetch_assoc($stdArr)) {
        $response .= '<option value=' . $data["id"] . ' >' . $data["name"] . '</option>';
    }
    echo $response;
    exit;
}

if ($_POST['action'] == 'getSubject') {

    $standard = isset($_REQUEST['standard']) ? $_REQUEST['standard'] : 0;
    $sub_institute_id = isset($_REQUEST['sub_institute_id']) ? $_REQUEST['sub_institute_id'] : 0;
//    echo "SELECT * FROM sub_std_map WHERE sub_institute_id = '".$sub_institute_id."' AND standard_id = '".$standard."'";
//	die();
    $subArr = mysqli_query($cn, "SELECT * FROM sub_std_map WHERE sub_institute_id = '" . $sub_institute_id . "' AND standard_id = '" . $standard . "'");

    $response = '<option value="">Select Subject</option>';
    while ($data = mysqli_fetch_assoc($subArr)) {
        $response .= '<option value=' . $data["subject_id"] . ' >' . $data["display_name"] . '</option>';
    }
    echo $response;
    exit;
}

if ($_POST['action'] == 'getChapter') {

    $standard = isset($_REQUEST['standard']) ? $_REQUEST['standard'] : 0;
    $subject = isset($_REQUEST['subject']) ? $_REQUEST['subject'] : 0;
    $sub_institute_id = isset($_REQUEST['sub_institute_id']) ? $_REQUEST['sub_institute_id'] : 0;
    //$syear = isset($_REQUEST['syear']) ? $_REQUEST['syear'] : 0;

    $chapterArr = mysqli_query($cn, "SELECT * FROM chapter_master WHERE sub_institute_id = '" . $sub_institute_id . "'
	AND standard_id = '" . $standard . "' AND subject_id = '" . $subject . "' ");

    $response = '<option value="">Select Chapter</option>';
    while ($data = mysqli_fetch_assoc($chapterArr)) {
        $response .= '<option value=' . $data["id"] . ' >' . $data["chapter_name"] . '</option>';
    }
    echo $response;
    exit;
}

if ($_POST['action'] == 'getTopic') {

    $standard = isset($_REQUEST['standard']) ? $_REQUEST['standard'] : 0;
    $subject = isset($_REQUEST['subject']) ? $_REQUEST['subject'] : 0;
    $chapter = isset($_REQUEST['chapter']) ? $_REQUEST['chapter'] : 0;
    $sub_institute_id = isset($_REQUEST['sub_institute_id']) ? $_REQUEST['sub_institute_id'] : 0;
    $sql = "SELECT * FROM topic_master WHERE sub_institute_id = '" . $sub_institute_id . "'
    AND chapter_id = '" . $chapter . "'";
    $topicArr = mysqli_query($cn, $sql);
    //topic master query here

    $response = '<option value="">Select Topic</option>';

    while ($data = mysqli_fetch_assoc($topicArr)) {
        $response .= '<option value=' . $data["id"] . ' >' . $data["name"] . '</option>';
    }
    echo $response;
    exit;
}

?>
