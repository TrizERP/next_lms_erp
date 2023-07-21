@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Result Marks Excel</h4>
            </div>
        </div>
        @php
            $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

        @endphp
            <div class="card">
                <div class="card-body">
                    @if ($sessionData = Session::get('data'))
                        <div class="alert alert-success alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
                    <form action="{{ route('result-marks-excel.create') }}">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('3','required','grade,std,div',$grade_id,$standard_id,$division_id) }}
                            <div class="col-md-3 form-group">
                                <label for="subject">Select Subject</label>
                                <select name="subject" id="subject" class="cust-select form-control mb-0">
                                    @if(empty($data['subject_data']))
                                        <option value="">Select Subject</option>
                                    @endif
                                    @if(!empty($data['subject_data']))
                                        @foreach($data['subject_data'] as $k1 => $v1)
                                            <option
                                                value="{{$v1['subject_id']}}" @if(isset($data['subject_id'])){{$data['subject_id'] == $v1['subject_id'] ? 'selected' : '' }} @endif>{{$v1['display_name']}} </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="exam">Select Exam</label>
                                <select class="cust-select form-control mb-0" name="exam_id[]" multiple="multiple"
                                        required="required">
                                    @if(!empty($data['exams_data']))
                                        @foreach($data['exams_data'] as $k => $v)
                                            <option
                                                value="{{$v->id}}" @if(isset($data->exam_id)){{$data->exam_id == $v->id ? 'selected=selected' : '' }} @endif>{{$v->paper_name}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-2 form-group mt-4">
                                <br>
                                <input type="submit" name="submit" value="Download Excel" class="btn btn-success">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            </div>
       
@include('includes.lmsfooterJs')
<script>
    $("#standard").change(function () {
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({
            url: path, data: 'std_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                }
            }
        });
    })

    $("#subject").change(function(){
        var std_id = $("#standard").val();
        var sub_id = $("#subject").val();
        var path = "{{ route('ajax_LMS_SubjectWiseExam') }}";
        $.ajax({
            url: path,
            data: 'std_id=' + std_id + '&sub_id=' + sub_id,
            success: function (result) {
                var e = $('select[name="exam_id[]"]');
                $(e).find('option').remove().end();
                for (var i = 0; i < result.length; i++) {
                    $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['paper_name']));
                }
            }
        });
    })
</script>

@include('includes.footer')
