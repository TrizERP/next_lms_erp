@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
                <div class="panel-body">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <form action="{{ route('indicator_mapping.store') }}" enctype="multipart/form-data"
                            method="post">

                            {{ method_field("POST") }}
                            {{csrf_field()}}

                            {{-- <div class="col-md-6 form-group">
                                <label>Exam Date </label>
                                <input type="text" id='examdate' required name="examdate" value=""
                                    class="form-control mydatepicker">
                            </div> --}}
                            <div class="row" id="addparts">
                                <div class="col-md-4 form-group">
                                    <label>Question Title</label>
                                    <input type="text" required name="question_title[]" value="" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Learning Outcome</label>
                                    <select class="form-control" required name="learning_outcome[]">
                                        <option value="">--Select--</option>
                                        @foreach ($data['lo_dd'] as $item=>$val)
                                        <option value="{{ $item }}">{{ $val }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Total Marks</label>
                                    <input type="number" required name="total_marks[]" value="" class="form-control">
                                </div>
                            </div>


                            {{-- <div class="col-md-4 form-group">
                                <label>Learning Outcome</label>
                                <input type="text"  required name="learning_outcome" value=""
                                    class="form-control">
                            </div> --}}

                            <div class="col-md-12 form-group" id="div_button">
                                <center>
                                    <div class="row" id="div_button">
                                        <input type="button" name="addmore" class="btn btn-info" value="Add More"
                                            id="addMore">
                                        <input type="button" name="minmore" class="btn btn-danger" value="Remove Last"
                                            id="minMore">
                                    </div>
                                    <div class="row" id="div_button">
                                        <div class="col-md-6 form-group mt-10">
                                            <label>Exam Type</label>
                                            <select class="form-control" required name="exam_type">
                                                <option value="">--Select--</option>
                                                @foreach ($data['exam_type_dd'] as $item=>$val)
                                                <option value="{{ $item }}">{{ $val }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>Exam Code</label>
                                            <input type="text" required name="exam_code" value="" class="form-control">
                                        </div>
                                    </div>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success m-t-40">
                                </center>
                            </div>

                        </form>

                    </div>
                    @if (count($errors) > 0)
                    <div class="alert alert-danger">
                        <strong>Whoops!</strong> There were some problems with your input.<br><br>
                        <ul>
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
            <div class="white-box">
                <div class="panel-body">

                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                        <!--<table id="example" class="table table-striped border dataTable" style="width:100%">-->
                        <table id="example" class="table table-striped table-bordered dataTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Date</th>
                                    <th>Medium</th>
                                    <th>Standard</th>
                                    <th>Subject</th>
                                    <th>Question Title</th>
                                    <th>Total Mark</th>
                                    <th>Lerning Outcome</th>
                                    <th>Exam Type</th>
                                    <th>Exam Code</th>
                                    <th>Action</th>

                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $i = 1;
                                @endphp
                                @foreach($data['data'] as $key => $datas)

                                <tr id={{$datas->ID}}>
                                    <td>{{$i}}</td>
                                    <td>{{$datas->DATE}}</td>
                                    <td>{{$datas->MEDIUM}}</td>
                                    <td>{{$datas->STANDARD}}</td>
                                    <td>{{$datas->SUBJECT}}</td>
                                    <td>{{$datas->QUESTION_TITLE}}</td>
                                    <td>{{$datas->QUESTION_OUT_OF}}</td>
                                    <td>{{$datas->INDICATOR}}</td>
                                    <td>{{$datas->EXAM_TYPE}}</td>
                                    <td>{{$datas->EXAM_CODE}}</td>
                                    <td>

                                        <form action="{{ route('indicator_mapping.destroy', $datas->ID)}}"
                                            method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-info btn-outline btn-circle btn m-r-5"
                                                onclick="return confirmDelete();" type="submit"><i
                                                    class="ti-trash"></i></button>
                                        </form>
                                    </td>

                                </tr>
                                @php
                                $i++;
                                @endphp
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function() {

var id = 1;

// get item

var item = $("#addparts");

var before = $('#div_button');

// initalize event click

$('#addMore').on('click', function() {
    // clone addparts
    var clone = item.clone(true);
        // remove id
        clone.attr('id', '');
        // add class duplicate
        clone.attr('class', 'duplicate');
    // insert duplicate before button div
    before.before(clone);


});
$('#minMore').on('click', function() {

    $('.duplicate').children().last().remove();
    $('.duplicate').children().last().remove();
    $('.duplicate').children().last().remove();


});

});
</script>

@include('includes.footer')