@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Circular</h4> </div>
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
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                    @endif
            <form action="{{ route('fees_circular.show_student') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    @if(isset($data['months']))
                    <div class="col-md-4 form-group">
                        <label>Months:</label>
                        <select name="month[]" class="form-control" required="required" multiple="multiple">
                            @foreach($data['months'] as $key => $value)
                                <option value="{{$key}}" @if(isset($data['month']))
                                @if(in_array($key,$data['month']))
                                    SELECTED
                                @endif
                                @endif>{{$value}}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @if(isset($data['receipt_books']))
                    <div class="col-md-4 form-group">
                        <label>Receipt Book Name:</label>
                        <select name="receipt_id" class="form-control" required="required">
                            <option>Select Receipt Book Name</option>
                            @foreach($data['receipt_books'] as $k => $v)
                               <option value="{{$v['receipt_id']}}" @if(isset($data['receipt_id'])) {{$data['receipt_id'] == $v['receipt_id'] ? 'selected' : ''}} @endif> {{$v['receipt_line_1']}}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['data']))
        @php
            if(isset($data['data'])){
                $student_data = $data['data'];
            }
        @endphp
        <div class="card">
            <form method="POST" action="show_circular">
                @csrf
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>{{ App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                        @if (Session::get('sub_institute_id') == '201' || Session::get('sub_institute_id') == '202' || Session::get('sub_institute_id') == '203' || Session::get('sub_institute_id') == '204')
                                        <th>Fees Breakoff</th>
                                        <th>Fees Circular Amount</th>
                                        <th>Fees Circular Remarks</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>

                                @php
                                $j=1;
                                $month_id = implode(',',$data['month']);
                                @endphp
                                @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $value)
                                    <tr>
                                        <td><input id="{{$value['stu_data']['student_id']}}" value="{{$value['stu_data']['student_id']}}" name="students[]" type="checkbox"></td>
                                        <td>{{$value['stu_data']['enrollment']}}</td>
                                            <td>{{$value['stu_data']['name']}}</td>
                                            <td>{{$value['stu_data']['stddiv']}}</td>
                                            @if (Session::get('sub_institute_id') == '201' || Session::get('sub_institute_id') == '202' || Session::get('sub_institute_id') == '203' || Session::get('sub_institute_id') == '204')
                                        <td>@foreach($value['total_fees'] as $i=>$val)
                                            @if($value['total_fees'][$i]['month_id'] == $month_id)
                                            {{$val['remain']}}
                                            @endif
                                        @endforeach
                                    </td>
                                            <td>
                                                <input type="text" name="fees_circular_amount[{{$value['stu_data']['student_id']}}]" class="form-control" placeholder="Amount">
                                        </td>
                                        <td>
                                            <input type="text" name="fees_circular_remarks[{{$value['stu_data']['student_id']}}]" class="form-control" placeholder="Remarks">
                                        </td>
                                        @endif
                                    </tr>
                                @php
                                $j++;
                                @endphp
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="hidden" name="month" @if(isset($data['month'])) value="{{implode(',',$data['month'])}}" @endif">
                                <input type="hidden" name="receipt_id" @if(isset($data['receipt_id'])) value="{{$data['receipt_id']}}" @endif">
                                <input type="hidden" name="grade_id" @if(isset($data['grade_id'])) value="{{$data['grade_id']}}" @endif">
                                <input type="hidden" name="standard_id" @if(isset($data['standard_id'])) value="{{$data['standard_id']}}" @endif
                                ">
                                <input type="submit" name="submit" value="Submit" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    function checkAll(ele) {
         var checkboxes = document.getElementsByTagName('input');
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script>
@include('includes.footer')
