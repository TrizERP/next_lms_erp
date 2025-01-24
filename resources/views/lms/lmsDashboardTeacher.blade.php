@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">LMS Teacher Dashboard</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';
            if(isset($data['grade'])){
                $grade_id = $data['grade'];
                $standard_id = $data['standard'];
                $division_id = $data['division'];
            }
            $currentStandard = $data['currentStandard'] ?? 0;
            $currentStudentId = $data['currentStudentId'] ?? 0;
        @endphp
        <div class="card">
            <div class="col-md-12">
                <div class="form-group">
                    <div class="row">
                        {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Search Student: </label>
                                <select name="student_id" id="student_id" class="form-control">
                                    <option value="">select student</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- @if(isset($data['studentData']) && $data['studentData']==1)
        <div class="lmsDashboard" id="lmsDashboard">
            @include('lms.lmsDashboardCommon')
        </div>
        @endif --}}
        @include('lms.lmsDashboardCommon')
    </div>    
</div>

@include('includes.footerJs')
<script> 
$(document).ready(function(){
    @if(isset($data['grade']))
    console.log('if');
        var grade = "{{$data['grade']}}";
        var standard ="{{$data['standard']}}";
        var division ="{{$data['division']}}";
        var student_id = "{{$data['students_id']}}";
        if(division){
            getStudent(grade,standard,"",student_id);
        }else{
            getStudent(grade,standard,division,student_id);
        }
   @else
        $('#standard').on('change',function(){
            var grade = $('#grade').val();
            var standard = $('#standard').val();
            var division = '';
            getStudent(grade,standard,division,'');
        }) 
        
        $('#division').on('change',function(){
            var grade = $('#grade').val();
            var standard = $('#standard').val();
            var division = $('#division').val();
            getStudent(grade,standard,division,'');
        })
   @endif
    $('#student_id').on('change',function(){
        var grade = $('#grade').val();
        var standard = $('#standard').val();
        var division = $('#division').val();
        var student_id = $('#student_id').val();
        getLMSDashboard(grade,standard,division,student_id);
    })
    
})

function getStudent(grade,standard,division,student_id){
    $('#student_id').empty();
    if(division===''){
        dataList = {grade:grade,standard:standard};
    }else{
        dataList = {grade:grade,standard:standard,division:division};
    }
    $.ajax({
        url : "{{route('studentLists')}}",
        data : dataList,
        type : 'GET',
        success : function(result){
            $('#student_id').append(
                    $('<option></option>')
                    .val('')
                    .text('select student') 
                );
            if(result){
                result.forEach(function(student) {
               
                let fullName = `${student.first_name} ${student.middle_name} ${student.last_name}`;
               
                let option = $('<option></option>')
                    .val(student.id)
                    .text(fullName);

                if(student_id && student_id == student.id) {
                    option.attr('selected', 'selected');
                }

                $('#student_id').append(option);
                
                });
            }
        }
    })
    // console.log(dataList);
}
function getLMSDashboard(grade,standard,division,student_id){
    if(student_id!==''){
        dataList = {grade:grade,standard:standard,division:division,students_id:student_id};
        $.ajax({
            url : "{{route('lmsdashboard.index')}}",
            data : dataList,
            type : 'GET',
            success : function(result){
                window.location.href = '/lms/lmsdashboard?grade=' + grade + '&standard=' + standard + '&division=' + division + '&students_id=' + student_id;
            }
        })
    }
}
</script>
<script>
$(document).ready(function() {
        // Cache commonly used elements
        var $chapData = $('.chapdata');
        var $recommendation = $('.recommendation');
        var $curveData = $('.curveData');
        var $rankData = $('.rankData');

        // Handle showing collapse sections on page load
        $('.row.PreSubcollapse.collapse.show').each(function() {
            var firstHref = $(this).find('a:first').attr('aria-controls');
            if (firstHref) {
                $('.' + firstHref).toggleClass('show');
            }
        });

        // Activate the first row and toggle respective classes for active circles and tables
        $('.ProgressCircle.activeCircle').each(function() {
            var divId = $(this).data('val');
            var $currentTable = $('.CurrentTable[data-val="collapseExample2_' + divId + '"]');
            
            $currentTable.toggleClass('active');
            
            var $firstRow = $currentTable.find('tbody tr:first');
            if ($firstRow.length) {
                $firstRow.addClass('activeChapter');
                var ch = $firstRow.data('val');
                var chapter = $firstRow.data('ch');
                
                // Show or hide sections based on row data
                toggleSections(divId, ch);
                getMapValue(divId, chapter, 'regular');
            } else {
                hideSections();
            }
        });

        // Event delegation for dynamic content
        $('.circle').on('click', function() {
            hideSections();
        });
        $('.showPal').hide();
    });

    function toggleSections(divId, ch) {
        $('.chapdata').hide();
        $('#collapseExample3_' + divId + '_' + ch).show();

        $('.recommendation').hide();

        var Realistic= $('#input_Realistic_' + divId + '_' + ch).val();
        var Investigative= $('#input_Investigative_' + divId + '_' + ch).val();
        var Artistic= $('#input_Artistic_' + divId + '_' + ch).val();
        var Social= $('#input_Social_' + divId + '_' + ch).val();
        var Enterprising= $('#input_Enterprising_' + divId + '_' + ch).val();
        var Conventional= $('#input_Conventional_' + divId + '_' + ch).val();
        $('#recommendationDiv_'+ divId + '_' + ch).empty();
        // $.ajax({
        //     url: 'https://erp.triz.co.in/intrestEnterScore',
        //     data: {
        //         Realistic: Realistic,
        //         Investigative: Investigative,
        //         Artistic: Artistic,
        //         Social: Social,
        //         Enterprising: Enterprising,
        //         Conventional: Conventional
        //     },
        //     type: 'GET',
        //     success: function(response) {
        //         console.log(response);

        //         if (response.career && response.career.length > 0) {
        //             // Loop through each career item in the response
        //         console.log(response.career);

        //             response.career.forEach(function(rval) {
        //                 // Create the HTML structure for each career recommendation
        //                 const careerHtml = `<a href="${rval.href}" class="d-flex" target="_blank">
        //                             <div style="width:90%">${rval.title}</div>
        //                             <div style="width:10%">
        //                                 <span class="mdi mdi-arrow-right-drop-circle-outline"></span>
        //                             </div>
        //                         </a>`;
        //                 // Append to the container
        //                 $('#recommendationDiv_'+ divId + '_' + ch).append(careerHtml);
        //             });
        //         } else {
        //             $('#recommendationDiv_'+ divId + '_' + ch).html('<p>No career recommendations found.</p>');
        //         }
        //     },
        //     error: function() {
        //         $('#recommendationDiv_'+ divId + '_' + ch).html('<p>Failed to load recommendations. Please try again.</p>');
        //     }
        // });

        $('#recommendation_' + divId + '_' + ch).show();

        $('.curveData').hide();
        $('#curveData_' + divId + '_' + ch).show();

        $('.rankData').hide();
        $('#rankData_' + divId + '_' + ch).show();
    }

    function hideSections() {
        $('.chapdata').hide();
        $('.recommendation').hide();
        $('.curveData').hide();
        $('.rankData').hide();
    }

    function PreviousCircle(std) {
        $('.circle1').removeClass('active');
        $('.PreSubcollapse, .bar-graph, .subject_col').removeClass('show');
        $('#lastStd').text(std);
        
        var $currentCircle = $('.circle1[data-val="' + std + '"]');
        $currentCircle.addClass('active');
        
        var $preSubcollapse = $('.PreSubcollapse[data-val="collapseExample_' + std + '"]');
        $preSubcollapse.toggleClass('show');
        
        var firstHref2 = $preSubcollapse.find('a:first').attr('aria-controls');
        if (firstHref2) {
            $('.' + firstHref2).toggleClass('show');
        }
    }

    function PreSubCollepse(subId, btn) {
        $('.SelectPreSub a').removeClass('activeSub active-border');
        $(btn).addClass('activeSub active-border');
        
        $('.subject_col').each(function() {
            var id = $(this).attr('id');
            $(this).collapse(id === subId ? 'show' : 'hide');
        });
    }

    function currentCircle(sub) {
      $('.CurrentTable').removeClass('show');
      $('.CurrentTable').removeClass('active');

      var $currentTable = $('.CurrentTable[data-val="collapseExample2_' + sub + '"]');
      $currentTable.toggleClass('active');

      // Update the progress circle to the active state
      $('.ProgressCircle').removeClass('activeCircle');
      $('.ProgressCircle[data-val="' + sub + '"]').toggleClass('activeCircle');

      // Get the first row from the current table
      var $firstRow = $currentTable.find('tbody tr:first');

      // If there is a first row, trigger activeTr function for it
      if ($firstRow.length) {
         var ch = $firstRow.data('val');
         var sub_id = sub;
         var chapter = $firstRow.data('ch');
         $firstRow.addClass('activeChapter');
         activeTr($firstRow.attr('id'), ch, sub_id,chapter);
      } else {
         hideSections();
      }
   }

   function activeTr(trsub, ch_id, sub_id,chapterId) {
        $('.trsub').removeClass('activeChapter');
        $('#tr' + ch_id + '_' + sub_id).toggleClass('activeChapter');
        
        toggleSections(sub_id, ch_id);
        getMapValue(sub_id,chapterId,'regular');
        // console.log('sub_id='+sub_id);
        // console.log('chapterId='+chapterId);
    }
    function activeTrPal(trsub, ch_id, sub_id,chapterId) {

        $('.trsubPal').removeClass('activeChapter');
        $('.trsubPal'+ch_id+'_'+sub_id).toggleClass('activeChapter');
       
        toggleSections(sub_id, ch_id);
        getMapValue(sub_id,chapterId,'pal');
    }

   function displayRegular(){
      $('.showPal').hide();
      $('.hideForPal').show();
      $('.hideOnPal').show();
   }

   function displayPal(subject_id, standard_id, student_id) {
    $('.hideOnPal').hide();
    $('.hideForPal').hide();
    $('.showPal').empty();
    $.ajax({
        url: "{{route('getPalMarks')}}",
        type: 'GET',
        data: {
            subject_id: subject_id,
            standard_id: standard_id,
            student_id: student_id
        },
        success: function(result) {
         $(".showPal").show();
            $.each(result, function(index, data) {
                // Calculate percentage
                let totalMarks = parseFloat(data.total_marks);
                let obtainMarks = parseFloat(data.obtain_marks);
                let percentage = (totalMarks > 0) ? (obtainMarks / totalMarks * 100) : 0;

                // Append the row to the table
                var active = '';
                if(index==0){
                  var active = 'activeChapter';
                }
                let row = `
                    <tr class="trsubPal ${active} trsubPal${data.chapter_id}_${subject_id}" onclick="activeTrPal('trPal${data.chapter_id}',${data.chapter_id},${subject_id},${data.chapter_id})" id="trPal${data.chapter_id}_${subject_id}" data-val="${data.chapter_id}">
                        <td style="width:70%">${data.chapter_name ?? '-'}</td>
                        <td style="width:10%">${totalMarks}</td>
                        <td style="width:10%">${obtainMarks}</td>
                        <td style="width:10%">${percentage}</td>
                    </tr>
                `;
                $(".showPal").append(row);
            });
         },
         error: function(xhr, status, error) {
               console.error("Error: " + error);
         }
      });
   }

   function getMapValue(sub_id, chapterId, examType) {
    var standard = "{{$currentStandard}}";
    var student = "{{$currentStudentId}}";
    $('.mapping_parts').empty(); // Clear the container before appending new data

    $.ajax({
        url: "{{route('getMapValue')}}",
        type: 'GET',
        data: {
            standard_id: standard,
            student_id: student,
            subject_id: sub_id,
            chapter_id: chapterId,
            exam_type: examType
        },
        success: function(result) {
         $('.mapping_parts').empty();
            let mapData = ``;

            // Check and append for each key
            if (result['Abilities'] && result['Abilities'].length > 0) {
                mapData += `
                    <div class="divMap1" style="width:50%">
                        <div class="mapTitle1">Abilities</div>
                        <div class="mapList1">
                            <ul>
                                ${result['Abilities'].map(item => `<li>${item.value_name}</li>`).join('')}
                            </ul>
                        </div>
                    </div>`;
            }

            if (result['Skills'] && result['Skills'].length > 0) {
                mapData += `
                    <div class="divMap2" style="width:50%">
                        <div class="mapTitle2">Skills</div>
                        <div class="mapList2">
                            <ul>
                                ${result['Skills'].map(item => `<li>${item.value_name}</li>`).join('')}
                            </ul>
                        </div>
                    </div>`;
            }

            if (result['Knowledge'] && result['Knowledge'].length > 0) {
                mapData += `
                    <div class="divMap3" style="width:50%">
                        <div class="mapTitle3">Knowledge</div>
                        <div class="mapList3">
                            <ul>
                                ${result['Knowledge'].map(item => `<li>${item.value_name}</li>`).join('')}
                            </ul>
                        </div>
                    </div>`;
            }

            if (result['Interests'] && result['Interests'].length > 0) {
                mapData += `
                    <div class="divMap4" style="width:50%">
                        <div class="mapTitle4">Interests</div>
                        <div class="mapList4">
                            <ul>
                                ${result['Interests'].map(item => `<li>${item.value_name}</li>`).join('')}
                            </ul>
                        </div>
                    </div>`;
            }

            // Append the generated HTML to the container
            $('.mapping_parts').append(mapData);
        },
        error: function(xhr, status, error) {
            console.error("Error: " + error);
        }
    });
}
</script>
@include('includes.footer')
@endsection
