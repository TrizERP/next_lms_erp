
if ($("#grade").length != 0) {
    $('#grade').change(function () {
        var gradeID = $(this).val();
        if (gradeID) {
            $.ajax({
                type: "GET",
                url: "/api/get-standard-list?grade_id=" + gradeID,
                success: function (res) {
                    if (res) {
                        $("#standard").empty();
                        $("#standard").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#standard").append('<option value="' + key + '">' + value + '</option>');
                        });
                        $("#division").empty();

                    } else {
                        $("#standard").empty();
                    }
                }
            });
        } else {
            $("#standard").empty();
            $("#division").empty();
        }
    });
}
if ($("#standard").length != 0) {
    $('#standard').on('change', function () {
        var standardID = $(this).val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-division-list?standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#division").empty();
                        $("#division").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#division").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#division").empty();
                    }
                }
            });
        } else {
            $("#division").empty();
        }

    });
}


if ($("#gradeS").length != 0) {
    $('#gradeS').change(function () {
        var gradeID = $(this).val();
        if (gradeID) {
            $.ajax({
                type: "GET",
                url: "/api/get-standard-list?grade_id=" + gradeID,
                success: function (res) {
                    if (res) {
                        $("#standardS").empty();
                        $("#standardS").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#standardS").append('<option value="' + key + '">' + value + '</option>');
                        });
                        $("#subject").empty();

                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#standardS").empty();
            $("#subject").empty();
        }
    });
}
if ($("#standardS").length != 0) {
    $('#standardS').on('change', function () {
        var standardID = $(this).val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID,
                success: function (res) {                    
                    if (res) {                        
                        $("#subject").empty();
                        $("#subject").append('<option value="">Select Subject</option>');
                        $.each(res, function (key, value) {                            
                            $("#subject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#subject").empty();
        }

    });
}

/* START PRE Topic */
if ($("#prestandard").length != 0) {
    $('#prestandard').on('change', function () {
        var standardID = $(this).val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID,
                success: function (res) {                    
                    if (res) {                        
                        $("#presubject").empty();
                        $("#presubject").append('<option value="">Select Subject</option>');
                        $.each(res, function (key, value) {                            
                            $("#presubject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#presubject").empty();
                        $("#presubject").append('<option value="">Select Subject</option>');
                    }
                }
            });
        } else {
            $("#presubject").empty();
            $("#presubject").append('<option value="">Select Subject</option>');           

            $("#prechapter").empty();
            $("#prechapter").append('<option value="">Select Chapter</option>');

            $("#pretopic").empty();
            $("#pretopic").append('<option value="">Select Topic</option>');
        }

    });
}
if ($("#presubject").length != 0) {
    $('#presubject').on('change', function () {
        var subjectID = $(this).val();
        var standardID = $('#prestandard').val();
        if (subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#prechapter").empty();
                        $("#prechapter").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {                           
                            $("#prechapter").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#prechapter").empty();
                        $("#prechapter").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#prechapter").empty();
            $("#prechapter").append('<option value="">Select Chapter</option>');
        }

    });
}
if ($("#prechapter").length != 0) {
    $('#prechapter').on('change', function () {
        var chapterID = $(this).val();        
        if (chapterID) {
            $.ajax({
                type: "GET",
                url: "/api/get-topic-list?chapter_id=" + chapterID,
                success: function (res) {
                    if (res) {
                        $("#pretopic").empty();
                        $("#pretopic").append('<option value="">Select Topic</option>');
                        $.each(res, function (key, value) {                           
                            $("#pretopic").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#pretopic").empty();
                        $("#pretopic").append('<option value="">Select Topic</option>');
                    }
                }
            });
        } else {
            $("#pretopic").empty();
            $("#pretopic").append('<option value="">Select Topic</option>');
        }

    });
}
/* END PRE Topic */

/* START POST Topic */
if ($("#poststandard").length != 0) {
    $('#poststandard').on('change', function () {
        var standardID = $(this).val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID,
                success: function (res) {                    
                    if (res) {                        
                        $("#postsubject").empty();
                        $("#postsubject").append('<option value="">Select Subject</option>');
                        $.each(res, function (key, value) {                            
                            $("#postsubject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#postsubject").empty();
                        $("#postsubject").append('<option value="">Select Subject</option>');
                    }
                }
            });
        } else {
            $("#postsubject").empty();
            $("#postsubject").append('<option value="">Select Subject</option>');

            $("#postchapter").empty();
            $("#postchapter").append('<option value="">Select Chapter</option>');

            $("#posttopic").empty();
            $("#posttopic").append('<option value="">Select Topic</option>');
        }

    });
}
if ($("#postsubject").length != 0) {
    $('#postsubject').on('change', function () {
        var subjectID = $(this).val();
        var standardID = $('#poststandard').val();
        if (subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#postchapter").empty();
                        $("#postchapter").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {                           
                            $("#postchapter").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#postchapter").empty();
                        $("#postchapter").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#postchapter").empty();
            $("#postchapter").append('<option value="">Select Chapter</option>');
        }

    });
}
if ($("#postchapter").length != 0) {
    $('#postchapter').on('change', function () {
        var chapterID = $(this).val();        
        if (chapterID) {
            $.ajax({
                type: "GET",
                url: "/api/get-topic-list?chapter_id=" + chapterID,
                success: function (res) {
                    if (res) {
                        $("#posttopic").empty();
                        $("#posttopic").append('<option value="">Select Topic</option>');
                        $.each(res, function (key, value) {                           
                            $("#posttopic").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#posttopic").empty();
                        $("#posttopic").append('<option value="">Select Topic</option>');
                    }
                }
            });
        } else {
            $("#posttopic").empty();
            $("#posttopic").append('<option value="">Select Topic</option>');
        }

    });
}
/* END POST Topic */

/* START Cross Curriuculum */
if ($("#cross-curriculumstandard").length != 0) {
    $('#cross-curriculumstandard').on('change', function () {
        var standardID = $(this).val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID,
                success: function (res) {                    
                    if (res) {                        
                        $("#cross-curriculumsubject").empty();
                        $("#cross-curriculumsubject").append('<option value="">Select Subject</option>');
                        $.each(res, function (key, value) {                            
                            $("#cross-curriculumsubject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#cross-curriculumsubject").empty();
                        $("#cross-curriculumsubject").append('<option value="">Select Subject</option>');
                    }
                }
            });
        } else {
            $("#cross-curriculumsubject").empty();
            $("#cross-curriculumsubject").append('<option value="">Select Subject</option>');

            $("#cross-curriculumchapter").empty();
            $("#cross-curriculumchapter").append('<option value="">Select Chapter</option>');

            $("#cross-curriculumtopic").empty();
            $("#cross-curriculumtopic").append('<option value="">Select Topic</option>');
        }

    });
}
if ($("#cross-curriculumsubject").length != 0) {
    $('#cross-curriculumsubject').on('change', function () {
        var subjectID = $(this).val();
        var standardID = $('#cross-curriculumstandard').val();
        if (subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#cross-curriculumchapter").empty();
                        $("#cross-curriculumchapter").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {                           
                            $("#cross-curriculumchapter").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#cross-curriculumchapter").empty();
                        $("#cross-curriculumchapter").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#cross-curriculumchapter").empty();
            $("#cross-curriculumchapter").append('<option value="">Select Chapter</option>');
        }

    });
}
if ($("#cross-curriculumchapter").length != 0) {
    $('#cross-curriculumchapter').on('change', function () {
        var chapterID = $(this).val();        
        if (chapterID) {
            $.ajax({
                type: "GET",
                url: "/api/get-topic-list?chapter_id=" + chapterID,
                success: function (res) {
                    if (res) {
                        $("#cross-curriculumtopic").empty();
                        $("#cross-curriculumtopic").append('<option value="">Select Topic</option>');
                        $.each(res, function (key, value) {                           
                            $("#cross-curriculumtopic").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#cross-curriculumtopic").empty();
                        $("#cross-curriculumtopic").append('<option value="">Select Topic</option>');
                    }
                }
            });
        } else {
            $("#cross-curriculumtopic").empty();
            $("#cross-curriculumtopic").append('<option value="">Select Topic</option>');
        }

    });
}

/* End Cross Curriuculum */

/* Start Send single Email */

if ($("#ajax_sendEmail").length != 0) 
{
    $('#ajax_sendEmail').on('click', function () 
    {
        $("#overlay").css("display","block");
        var action = $("#action").val();
        var student_id = $("#student_id").val();

        if(action == 'imprest_ledger_view'){
            var receipt_id_html = '';
        }else{
            var receipt_id_html = $("#receipt_id_html").val();
        }
    
        $.ajax({
                url: "/ajax_sendEmailFeesReceipt?action="+action+"&student_id="+student_id+"&receipt_id_html="+receipt_id_html,                
                success: function(result){ 
                    if(result == 1){
                        alert('Fees Receipt Mail Sent Successfully.');                       
                    }
                    if(result == 2){
                        alert('Imprest Ledger Mail Sent Successfully.');                       
                    }
                    $("#overlay").css("display","none");
                }
        });
    });
}

/* End Send single Email */

/* Start Send Bulk Email */
if ($("#ajax_sendBulkEmail").length != 0) 
{
    $('#ajax_sendBulkEmail').on('click', function () 
    {
        $("#overlay").css("display","block");
        var inserted_ids = $("#last_inserted_ids").val();
        var action = $("#action").val();
        $.ajax({
                url: '/ajax_sendBulkEmailFeesReceipt?action='+action+'&inserted_ids='+inserted_ids,                
                success: function(result){ 
                    if(result == 1){
                        alert('Other Fees Receipt Mail Sent Successfully.');
                    }
                    if(result == 2){
                        alert('Fees Circular Mail Sent Successfully.');
                    }
                    $("#overlay").css("display","none");
                }
        });
    });
}
/* End Send Bulk Email */

/* Start Open Fees Receipt PDF instead of print receipt */

if ($("#ajax_PDF").length != 0) 
{
    $('#ajax_PDF').on('click', function () 
    {
        $("#overlay").css("display","block");
        var action = $("#action").val();
        var student_id = $("#student_id").val();
        var receipt_id_html = $("#receipt_id_html").val();
        var paper_size = $("#paper_size").val();
    
        $.ajax({
                url: "/ajax_PDF_FeesReceipt?action="+action+"&student_id="+student_id+"&receipt_id_html="+receipt_id_html+"&paper_size="+paper_size,                
                success: function(result){ 
                    window.open(result, '_blank');
                    $("#overlay").css("display","none");
                }
        });
    });
}

/* End Open Fees Receipt PDF instead of print receipt */

/* Start Open Other Fees Receipt Bulk PDF instead of print receipt */
if ($("#ajax_PDFBulk").length != 0) 
{
    $('#ajax_PDFBulk').on('click', function () 
    {
        $("#overlay").css("display","block");
        var inserted_ids = $("#last_inserted_ids").val();
        var action = $("#action").val();
        $.ajax({
                url: '/ajax_PDF_Bulk_OtherFeesReceipt?action='+action+'&inserted_ids='+inserted_ids,                
                success: function(result){ 
                    window.open(result, '_blank');
                    $("#overlay").css("display","none");
                }
        });
    });
}
/* End Open Other Fees Receipt Bulk PDF instead of print receipt */

/* Start Open Student Certificate PDF instead of print receipt */
if ($("#ajax_PDF_Certificate").length != 0) 
{
    $('#ajax_PDF_Certificate').on('click', function () 
    {
        $("#overlay").css("display","block");
        var action = $("#action").val();
        var insert_student_ids = $("#insert_ids").val();
        var template_name = $("#template_name").val();
        var path = '/ajax_saveData?insert_student_ids='+insert_student_ids+'&template='+template_name;
        $.ajax({
                url: path,
                success: function(result){
                    $.ajax({
                            url: '/ajax_PDF_Bulk_OtherFeesReceipt?action='+action+'&inserted_ids='+result,                
                            success: function(result){ 
                                window.open(result, '_blank');
                                $("#overlay").css("display","none");
                            }
                    });   
                }
        });
    });
}
/* End Open Student Certificate Bulk PDF instead of print receipt */