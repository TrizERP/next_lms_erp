{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<link href="{{ asset('/plugins/bower_components/switchery/dist/switchery.min.css') }}" rel="stylesheet" />

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Review Student Assignment</h4>
            </div>
        </div>

        <div class="card">
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-4">
                    <div class="video-box mb-4">
                        <div style="margin-top:10% !important;"> <!-- embed-responsive embed-responsive-16by9 -->
                            <div style="position: relative; display: flex; flex-direction: column; align-items: center; padding-top: 50px;">
                                <!-- we are putting a copy of the original image under the result image so it's always annotation-free -->
                                <img src="../../../storage/lms_assignment_submission/{{$data['assignment_data']['submission_image']}}" style="max-width: 600px;" onload="setSourceImage1(this);" />
                                <img src="../../../storage/lms_assignment_submission/{{$data['assignment_data']['submission_image']}}" style="max-width: 600px; position: absolute;" id="assignment_image" onclick="showMarkerArea(this);" />
                              </div>

                            <!-- <iframe autoplay="false" class="embed-responsive-item" src="../../../storage/lms_assignment_submission/{{$data['assignment_data']['submission_image']}}" allowfullscreen></iframe> -->
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <form action="{{ route('lmsAnnotate_assignment.store') }}" method="post" enctype='multipart/form-data'>
                        {{ method_field("POST") }}
                        @csrf

                        <div class="row">
                            <div class="col-md-8">
                                <label for="description"><b> Paper Name :</b></label> {{$data['questionpaper_data']['paper_name']}}
                            </div>
                            <div class="col-md-8">
                                <label for="description"><b>Total Marks :</b></label> {{$data['questionpaper_data']['total_marks']}}
                            </div>
                            <div class="col-md-8">
                                <div class="table-responsive">
                                <table id="example" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th data-toggle="tooltip" title="Question List">Question List</th>
                                        <th data-toggle="tooltip" title="Marks">Marks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @if(count($data['questionData']) > 0)
                                        @foreach($data['questionData'] as $k => $v)
                                            @if($v['question_type_id'] ==  1)
                                            <tr>
                                                <td>Question {{$j}}</td>
                                                <td>
                                                    <div class="switchery-demo m-b-30">
                                                        <input type="checkbox" class="js-switch mcqmarks" data-color="#13dafe" name="questions[{{$v['id']}}]" value="{{$v['points']}}" onchange="add_total();" />  / {{$v['points']}}
                                                    </div>
                                                </td>
                                            </tr>
                                            @else
                                            <tr>
                                                <td>Question {{$j}}</td>
                                                <td><input type="number" class="marks" name="questions[{{$v['id']}}]" min="0" max="{{$v['points']}}" onchange="add_total();" required> / {{$v['points']}}</td>
                                            </tr>
                                            @endif
                                            @php
                                            $j++;
                                            @endphp
                                        @endforeach
                                        <tr>
                                            <td class="font-weight-bold">Total</td>
                                            <td><input type="number" readonly name="obtain_marks" id="obtain_marks" min="0" max="{{$data['questionpaper_data']['total_marks']}}"> / {{$data['questionpaper_data']['total_marks']}}</td>
                                        </tr>
                                    @endif

                                </tbody>
                                </table>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label for="description" class="mt-4">Remarks</label>
                                <textarea class="form-control" name="teacher_remarks" id="teacher_remarks"></textarea>
                            </div>
                            <div class="col-md-8">
                                <input type="text" name="hid_question_paper_id" id="hid_question_paper_id" value="{{$data['questionpaper_data']['id']}}">
                                <input type="text" name="hid_assignment_id" id="hid_assignment_id" value="{{$data['assignment_data']['id']}}">
                                <input type="text" name="hid_student_id" id="hid_student_id" value="{{$data['assignment_data']['student_id']}}">
                                <button class="btn btn-primary" type="submit">Submit</button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

@include('includes.lmsfooterJs')
<script src="{{asset('/plugins/bower_components/switchery/dist/switchery.min.js')}}"></script>
<script src="https://unpkg.com/markerjs2/markerjs2.js"></script>
<script type="text/javascript">

let sourceImage, targetRoot, maState;

// save references to the original image and its parent div (positioning root)
function setSourceImage1(source) {
  sourceImage = source;
  targetRoot = source.parentElement;
}

function showMarkerArea(target) {
  const markerArea = new markerjs2.MarkerArea(sourceImage);
  // since the container div is set to position: relative it is now our positioning root
  // end we have to let marker.js know that
  markerArea.targetRoot = targetRoot;
  markerArea.addRenderEventListener((imgURL, state) => {
    target.src = imgURL;
    // save the state of MarkerArea
    maState = state;

    alert(maState);
    var path = "{{ route('ajax_SaveAnnotations') }}";
    var d = JSON.stringify(maState);
    alert('a');
    alert(d);
    $.ajax({
        url: path,
        contentType: "application/json",
        data:'maState=sonika',
        success: function(result){
            alert(result);
        },
        fail:function(result)
        {
          alert("fail"+result);
        }
    });
    alert('b');
  });

    markerArea.show();

  // if previous state is present - restore it
  if (maState) {
    markerArea.restoreState(maState);
  }
}


function add_total()
{
    var sum = 0;
    $('.marks').each(function () {
        var amount;
        amount = parseInt($(this).val());
        if (!isNaN(amount)) {
            sum += amount;
        }
    });

    $('.mcqmarks').each(function () {
        amount = parseInt($(this).val());
        if ($(this).prop('checked') == true)
        {
            if (!isNaN(amount))
            {
                sum += amount;
            }
        }
    });

    $("#obtain_marks").val(sum);
}

$(function() {
    // Switchery
    var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
    $('.js-switch').each(function() {
        new Switchery($(this)[0], $(this).data());
    });
});
</script>
@include('includes.footer')
@endsection
