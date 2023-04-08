<?php
$host= "192.168.0.2";
$db_user="root";
$db_password="Triz@R@jesh";

$database='triz_erp_2';

$cn = mysqli_connect($host, $db_user, $db_password) or die("asd");
mysqli_set_charset($cn,"utf8");
mysqli_select_db($cn, $database) or die("Database not found");

$sql = "select st.name AS standard,ss.display_name AS subject,c.sort_order AS chapter_order,c.chapter_name AS chapter,lqm.id AS id,lqm.question_title AS title
from chapter_master c 
join subject s on s.id = c.subject_id and s.sub_institute_id = c.sub_institute_id 
join standard st on st.id = c.standard_id and st.sub_institute_id = c.sub_institute_id 
join sub_std_map ss on ss.subject_id = s.id and ss.standard_id = st.id and ss.status = 1 
join academic_section a on a.id = c.grade_id and st.grade_id = a.id and a.sub_institute_id = c.sub_institute_id 
join school_setup sa on sa.Id = c.sub_institute_id and sa.Id = a.sub_institute_id 
join lms_question_master lqm on sa.Id = lqm.sub_institute_id and lqm.grade_id = a.id and lqm.standard_id = st.id and lqm.subject_id = s.id and lqm.chapter_id = c.id and lqm.status = 1 
where sa.Id = 195 and lqm.question_type_id = 1 and st.id in (2268)
order by a.sub_institute_id,a.sort_order,st.sort_order,ss.sort_order,c.sort_order
";
//

// AND c.id=23 AND lqm.id IN (15207,53397,53398,53399)
//echo $sql;die();

$query = mysqli_query($cn, $sql) or die(mysqli_error($cn));
echo "<table border='1'>";
echo "<tr><td>standard</td><td>subject</td><td>chapter_no</td><td>chapter_name</td><td>Question</td><td>Description</td><td>Points</td><td>QuestionType</td><td>ShowHide</td><td>QuestionLevel</td><td>QuestionCategory</td><td>Concept</td><td>SubConcept</td><td>Competancy_Skill</td><td>Options1</td><td>Options2</td><td>Options3</td><td>Options4</td><td>RightOption</td><td>Answer</td><td>Hint</td>";

while ($data = mysqli_fetch_assoc($query)) {

echo "<tr>";
echo "<td>".$data['standard']."</td>";
echo "<td>".$data['subject']."</td>";
echo "<td>".$data['chapter_order']."</td>";
echo "<td>".$data['chapter']."</td>";
echo "<td>".$data['title']."</td>";
echo "<td>Euro_".$data['standard']."_".$data['subject']."_".$data['chapter_order']."_".$data['chapter']."</td>";
echo "<td>1</td><td>multiple</td><td>1</td><td></td><td></td><td></td><td></td><td></td>";

    $sql_answer = "SELECT * FROM answer_master WHERE question_id = ".$data['id'];
    $query_answer = mysqli_query($cn, $sql_answer) or die(mysqli_error($cn));
    $count = mysqli_num_rows($query_answer);

    $i = 1;$j='';
    while ($data_answer = mysqli_fetch_assoc($query_answer)) {
            echo "<td>".$data_answer['answer']."</td>";
            if($data_answer['correct_answer'] == 1)
                $j = $i;
            $i++;
    }
    if($count == 2)
        echo "<td></td><td></td><td>".$j."</td><td></td><td></td>";
    elseif($count == 3)
        echo "<td></td><td>".$j."</td><td></td><td></td>";
    elseif($count == 4)    
        echo "<td>".$j."</td><td></td><td></td>";
    
    echo "</tr>";
}
echo "</table>";

//echo $html;
?>