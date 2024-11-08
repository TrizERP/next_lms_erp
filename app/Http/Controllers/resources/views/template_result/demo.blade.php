

<?php 

   $header_data = $data['header_data'];
   $footer_data = $data['footer_data'];


foreach ($data['data'] as $stuent_id => $all_data) {


 $templateResult_html_content = str_replace(htmlspecialchars("{{STUDENT-ROLL_NO}}"), $all_data['roll_no'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{STUDENT_NAME}}"), $all_data['name'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{GUARDIAN_NAME}}"), $all_data['father_name'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{STUDENT_BIRTHDATE}}"), $all_data['date_of_birth'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{STUDENT-STD_SECTION}}"), $all_data['class']."/".$all_data['division'], $templateResult_html_content);


    $mark_selection = View::make('template_result/scholastic_areas',compact('all_data'));
    $templateResult_html_content = str_replace(htmlspecialchars("{{SCHOLASTIC-SECTION}}"), $mark_selection, $templateResult_html_content);

    $grd_selection = View::make('template_result/grading_scale_for_scholastic_areas',compact('all_data'));
    $templateResult_html_content = str_replace(htmlspecialchars("{{PERCENTAGE_GRADING_SYSTEM_SECTION}}"), $grd_selection, $templateResult_html_content);

    $templateResult_html_content = str_replace(htmlspecialchars("{{PRINCIPAL_SIGN}}"), '<img height="50px" width="100px" src="/storage/result/principle_sign/'.$footer_data['principal_sign'].'" />', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{DIRECTOR_SIGN}}"), '<img height="50px" width="100px" src="/storage/result/principle_sign/'.$footer_data['director_signatiure'].'" />', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{SCHOOL_TEACHER_SIGN}}"), '<img height="50px" width="100px" src="/storage/result/principle_sign/'.$footer_data['teacher_sign'].'" />', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{DATE_SECTION}}"), $footer_data['reopen_date'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{PROMOTED_SECTION}}"), 'Promoted to Class : '.$all_data['term'], $templateResult_html_content);
   
    $templateResult_html_content = str_replace(htmlspecialchars("{{CLASS_TEACHER-REMARKS.SECTION}}"), "Class Teacher's remarks: Aaditya has shown good initiation in the class and studies. He is a well spirited student. Keep it up!", $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{RESULT_END_NOTES}}"), '* This is a computer generated report card. Do not print until absolutely necessary.', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{SCHOOL ADDRESS}}"), $header_data['line1'].','.$header_data['line2'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{ACADEMIC-YEAR}}"), $header_data['syear'], $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{SCHOOL-EMAIL}}"), 'info@hillshigh.com', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{SCHOOL_PHONE}}"), '9033093477', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{SCHOOL-AFILATION}}"), '430228', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{STD_SHORT_NAME}}"), 'VI', $templateResult_html_content);
    $templateResult_html_content = str_replace(htmlspecialchars("{{REPORT-CARD_TERM}}"), 'Term2', $templateResult_html_content);

      $templateResult_html_content = str_replace(htmlspecialchars("{{CO-SCHOLASTIC SECTION}}"), View::make('template_result/co_scholastic_areas'), $templateResult_html_content);

        $templateResult_html_content = str_replace(htmlspecialchars("{{CO-SCHOLASTIC-GRADING_SYSTEM_SECTION}}"), View::make('template_result/grading_scale_for_co_scholastic_areas'), $templateResult_html_content);
    
}
print_r($templateResult_html_content);
die;
?>

{{$templateResult_html_content}}


