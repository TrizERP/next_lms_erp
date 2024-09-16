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
      	
        {{-- @if(isset($data['studentDetails']) && $data['studentDetails']==1)
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
            url : "{{route('teacherIndex')}}",
            data : dataList,
            type : 'GET',
            success : function(result){
                window.location.href = '/lms/lmsdashboard_teacher?grade=' + grade + '&standard=' + standard + '&division=' + division + '&students_id=' + student_id;
            }
        })
    }
}
    $('.row.PreSubcollapse.collapse.show').each(function() {
        var divId = $(this).attr('id');

        var firstHref = $(this).find('a:first').attr('aria-controls');
         $('.'+firstHref).toggleClass('show');
    });


    $('.standardProgressCircle.active').each(function() {
        var divId = $(this).attr('data-val');
        // console.log('sub id'+divId);
        $('.CurrentTable[data-val="collapseExample2_'+divId+'"]').toggleClass('active');
   
         var $firstRow = $('.CurrentTable[data-val="collapseExample2_'+divId+'"] tbody').find('tr:first');
         if ($firstRow.length > 0) {
            $firstRow.toggleClass('activeChapter');
            var ch = $firstRow.attr('data-val');

            $('.chapdata').hide();
            $('#collapseExample3_'+divId+'_'+ch).show();

            $('.recommendation').hide();
            $('#recommendation_'+divId+'_'+ch).show();

            $('.curveData').hide();
            $('#curveData_'+divId+'_'+ch).show();

            $('.rankData').hide();
            $('#rankData_'+divId+'_'+ch).show();
         }else{
            $('.chapdata').hide();
            $('.recommendation').hide();
            $('.rankData').hide();
            $('.curveData').hide();
         }

    });


   $('.circle').on('click',function(){
      $('.chapdata').hide();
      $('.recommendation').hide();
      $('.rankData').hide();
      $('.curveData').hide();
   })

</script>
<script>
  function PreviousCircle(std){
   $('.circle1').removeClass('active');
   $('.PreSubcollapse').removeClass('show');
   $('.bar-graph').removeClass('show');
   $('.subject_col').removeClass('show');
   $('#lastStd').empty();
   $('#lastStd').text(std);
   $('.circle1[data-val="'+std+'"]').toggleClass('active');

   $('.PreSubcollapse[data-val="collapseExample_'+std+'"]').toggleClass('show');
   var firstHref2 = $('.PreSubcollapse[data-val="collapseExample_'+std+'"]').find('a:first').attr('aria-controls');
   if (firstHref2) {
      $('.'+firstHref2).toggleClass('show');
   } else {
      console.log("No href found.");
   }

  }

  function PreSubCollepse(subId) {
   currentId = "collapseExample_'"+subId+"'";
    $('.subject_col').each(function() {
        var $collapse = $(this);
        var id = $collapse.attr('id');

        if (id === subId) {
            $collapse.collapse('show');
        } else {
            $collapse.collapse('hide');
        }
    });
}

function currentCircle(sub){
   $('.CurrentTable').removeClass('show');
   $('.CurrentTable[data-val="collapseExample2_'+sub+'"]').toggleClass('active');
   
   var $firstRow = $('.CurrentTable[data-val="collapseExample2_'+sub+'"] tbody').find('tr:first');
   if ($firstRow.length > 0) {
      $firstRow.toggleClass('activeChapter');
      var ch = $firstRow.attr('data-val');

      $('.chapdata'+sub+'_'+ch).hide();
      $('#collapseExample3_'+sub+'_'+ch).show();

      $('.recommendation').hide();
            $('#recommendation_'+sub+'_'+ch).show();

            $('.curveData').hide();
            $('#curveData_'+sub+'_'+ch).show();

            $('.rankData').hide();
            $('#rankData_'+sub+'_'+ch).show();

   } else {
      console.log("No first row found in table.");
   }
  
}

   function activeTr(trsub,ch_id,sub_id){
        $('.trsub').removeClass('activeChapter');
       $('#'+trsub).toggleClass('activeChapter');
       $('.chapdata').hide();
       $('.recommendation').hide();
       $('.rankData').hide();
       $('.curveData').hide();
       $('#collapseExample3_'+sub_id+'_'+ch_id).show();
       $('#recommendation_'+sub_id+'_'+ch_id).show();
       $('#rankData_'+sub_id+'_'+ch_id).show();
       $('#curveData_'+sub_id+'_'+ch_id).show();
       
   }
</script>
@include('includes.footer')
@endsection
