@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Hostel Room Allocation</h4></div>
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
                    <form action="{{ route('student_hostel_room_allocation') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label for="gender">Select Gender:</label>
    	                        <select name="gender" id="gender" class="form-control">
    	                            <option value=""> Select Gender </option>
    	                            <option value="M" @if(isset($data['gender'])){{ $data['gender'] == 'M' ? 'selected' : '' }}@endif> Male </option>
    	                            <option value="F" @if(isset($data['gender'])){{ $data['gender'] == 'F' ? 'selected' : '' }}@endif> Female </option>
    	                        </select>
                            </div>
                            @if(isset($data['profiles']))
                            <div class="col-md-4 form-group ml-0">
                                <label for="user">Select User:</label>
                                <select name="user" required id="user" class="form-control">
                                    <option value=""> Select User </option>
                                        @foreach($data['profiles'] as $key => $value)
                                        @php
                                                $selected = '';
                                            @endphp
                                        @if(isset($data['userProfile']))
                                            @if($data['userProfile'][0]['id'] == $value['id'])
                                                @php
                                                $selected = 'selected="selected"';
                                                @endphp
                                            @endif
                                        @endif
                                        <option value="{{$value['id']}}" {{$selected}}> {{$value['name']}} </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-md-6 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                    </div>
        </div>
        @if(isset($data['data']))
        @php
            if(isset($data['data'])){
                $student_data = $data['data'];
            }
        @endphp
            <div class="card">
                <div class="panel-body">
                    <div class="table-responsive">
                        <form method="POST" action="{{ route('hostel_room_allocation.store') }}">
                            @csrf
                        <table id="example" class="table table-striped">
                            @if(isset($data['tableHeads']))
                            <thead>
                                <tr>
                                    <th>
                                    <input id="checkall" onclick="checkedAll();" name="checkall" type="checkbox">
                                    </th>
                                    @foreach($data['tableHeads'] as $key => $value)
                                        <th>{{ucfirst(str_replace("_"," ",$value))}}</th>
                                    @endforeach
                                    <th>Admission Category Id</th>
                                    <th>Hostel</th>
                                    <th>Room No</th>
                                    <th>Bed No</th>
                                    <th>Locker No</th>
                                    <th>Table No</th>
                                    <th>Bedsheet No</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($data['data'] as $keyData => $valueData)
                                <tr>
                                        <td>
                                            <input id="{{$valueData['id']}}" value="{{$valueData['id']}}" name="students[]" type="checkbox">
                                        </td>
                                        @foreach($data['tableHeads'] as $key => $value)

                                            <td>{{$valueData[$value]}}</td>
                                        @endforeach
                                        <td>
                                            <select class="form-control" name="admission_category[{{$valueData['id']}}]">
                                                <option value=""> Select Academic Category </option>
                                            @foreach($data['admissionCategoryList'] as $akey => $avalue)
                                            @php
                                            $selected='';
                                            if($avalue['id'] == $valueData['admission_category_id'])
                                            {
                                                $selected='selected="selected"';
                                            }
                                            @endphp
                                                <option value="{{$avalue['id']}}" {{$selected}}>{{$avalue['title']}}</option>
                                            @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control" name="hostelno[{{$valueData['id']}}]" onchange="getRooms(this.value,{{$valueData['id']}})">
                                                <option value=""> Select Hostel </option>
                                                @foreach($data['hostelList'] as $hkey => $hvalue)
                                                    @php
                                                    $selected='';
                                                    if($hvalue['id'] == $valueData['hostel_id'])
                                                    {
                                                        $selected='selected="selected"';
                                                    }
                                                    @endphp
                                                    <option value="{{$hvalue['id']}}" {{$selected}} >{{$hvalue['name']}}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select  class="form-control" id="roomno{{$valueData['id']}}"  name="roomno[{{$valueData['id']}}]">
                                                <option value=""> Select Room </option>
                                            </select>
                                        </td>
                                        <td><input type="textbox" class="form-control" name="bedno[{{$valueData['id']}}]" value="{{$valueData['bed_no']}}"></td>
                                        <td><input type="textbox" class="form-control" name="lockerno[{{$valueData['id']}}]" value="{{$valueData['locker_no']}}"></td>
                                        <td><input type="textbox" class="form-control" name="tableno[{{$valueData['id']}}]" value="{{$valueData['table_no']}}"></td>
                                        <td><input type="textbox" class="form-control" name="bedsheetno[{{$valueData['id']}}]" value="{{$valueData['bedsheet_no']}}"></td>

                                    </tr>
                                    @endforeach
                            </tbody>
                            @endif

                        </table>
                            <input type="hidden" name="user_group_id" value="{{$data['userProfile'][0]['id']}}">
                            <div class="col-md-4 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @include('includes.footerJs')
    <script>
        var checked = false;

        function checkedAll() {
            if (checked == false) {
                checked = true
            } else {
                checked = false
            }
            for (var i = 0; i < document.getElementsByName('students[]').length; i++) {
                document.getElementsByName('students[]')[i].checked = checked;
            }
        }
    </script>
    <script>
        $(document).ready(function () {
            $('#example').DataTable();
        });
    </script>
    <script>
        function getRooms(hostel_id, user_id) {
            var path = "{{ route('hostelWiseRoomList') }}";
            var roomId = "#roomno" + user_id;
            $(roomId).find('option').remove().end().append('<option value=""> Select Room  </option>').val('');
            $.ajax({
                type: 'POST',
                url: path,
                data: {hostel_id: hostel_id},
                success: function (data) {
                    for (var i = 0; i < data.length; i++) {
                        $(roomId).append($("<option value=''> Select Room </option>").val(data[i]['id']).html(data[i]['room_name']));
                    }
                }
            });
        }
    </script>
@include('includes.footer')
