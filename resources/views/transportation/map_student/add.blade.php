@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Student Mapping</h4>            
            </div>                    
        </div>
        <div class="card">
            <div class="panel-body white-box">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('map_student.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                
                        <div class="table-responsive">
                            <table class="table-bordered table" id="myTable" width="100%">
                                <tr>
                                    <th></th>
                                    <th>Sr. No.</th>
                                    <th>Student Name</th>
                                    <th>Std/Div</th>
                                    <th>Enrollment No.</th>
                                    <th>Mobile</th>
                                    <th>From Shift</th>
                                    <th>From Bus</th>
                                    <th>From</th>
                                    <th>Amount</th>
                                    <th>To Shift</th>
                                    <th>To Bus</th>
                                    <th>To</th>
                                </tr>
                                @php
                                $arr = $data['stu_data'];
                                foreach ($arr as $id=>$col_arr){
                                @endphp
                                <tr>
                                    <td><input type="checkbox" name="@php echo 'values['.$col_arr['student_id'].'][ckbox]'; @endphp" class="ckbox1">  </td>
                                    <td>@php echo $id+1; @endphp</td>
                                    <td>@php echo $col_arr['name']; @endphp</td>
                                    <td>{{$col_arr['std-div']}}</td>
                                    <td>{{$col_arr['enrollment_no']}}</td>
                                    <td class="{{ $col_arr['from_shift_id'] }}">@php echo $col_arr['mobile']; @endphp</td>
                                    <td>
                                        <select name="values[{{$col_arr['student_id']}}][from_shift]" disabled="true" id="from_shift" data-from_shift="$col_arr['from_shift_id']" class="form-control from_shift" required>
                                            <option value="">--Select--</option>
                                            @foreach ($col_arr['ddShift'] as $id => $arr) 
                                                <option @if($arr->id == $col_arr['from_shift_id']) selected @endif value='{{$arr->id}}' data-fromshiftkm="{{$arr->km_amount}}" data-fromshiftrate="{{$arr->shift_rate}}" data-stud="{{$col_arr['student_id']}}">{{$arr->shift_title}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="values[{{$col_arr['student_id']}}][from_bus]" disabled="true" id="from_bus" data-from_bus="$col_arr['from_bus_id']" class="from_bus form-control" required data-studentid="{{$col_arr['student_id']}}">
                                            <option value="">--Select--</option>
                                            @php
                                            foreach ($col_arr['ddFromBus'] as $id => $arr) {
                                                $selected = "";
                                                if ($id == $col_arr['from_bus_id'])
                                                    $selected = "selected=selected";
                                                echo "<option $selected value='$id'>$arr</option>";
                                            }
                                            @endphp
                                        </select>
                                        <span class="remain_capacity_success"></span>
                                    </td>
                                    <td>
                                        <select name="values[{{ $col_arr['student_id'] }}][from_stop]" disabled="true" id="from_stop" class="from_stop form-control" required data-studentid="{{$col_arr['student_id']}}">
                                            <option value="">--Select--</option>
                                            @php
                                            foreach ($col_arr['ddFrom'] as $id => $arr) {
                                                $selected = "";
                                                if ($id == $col_arr['from_stop'])
                                                    $selected = "selected=selected";
                                                echo "<option $selected value='$id'>$arr</option>";
                                            }
                                            @endphp
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" class="form-control distance" disabled="true" name="values[{{ $col_arr['student_id'] }}][distance]" id="distance_{{ $col_arr['student_id'] }}" value="{{ $col_arr['distance']?? 1 }}" readonly>

                                        <input type="text" class="form-control distance_amount" disabled="true" name="values[{{ $col_arr['student_id'] }}][distance_amount]" id="distance_amount_{{ $col_arr['student_id'] }}" value="{{ $col_arr['total_amount']?? 0}}" readonly>
                                    </td>                                    
                                    <td>
                                        <select name="values[{{$col_arr['student_id']}}][to_shift]" disabled="true" id="to_shift" class="form-control to_shift" required>
                                            <option value="">--Select--</option>
                                           
                                            @foreach ($col_arr['ddShift'] as $id => $arr)
                                                <option @if($arr->id == $col_arr['to_shift_id']) selected @endif value='{{$arr->id}}' data-toshiftkm="{{$arr->km_amount}}" data-toshiftrate="{{$arr->shift_rate}}">{{$arr->shift_title}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="values[{{$col_arr['student_id']}}][to_bus]" disabled="true" id="to_bus" class="to_bus form-control" required>
                                            <option value="">--Select--</option>
                                            @php
                                            foreach ($col_arr['ddToBus'] as $id => $arr) {
                                                $selected = "";
                                                if ($id == $col_arr['to_bus_id'])
                                                    $selected = "selected=selected";
                                                echo "<option $selected value='$id'>$arr</option>";
                                            }
                                            @endphp
                                        </select>
                                    </td>
                                    <td>
                                        <select name="values[{{$col_arr['student_id']}}][to_stop]" disabled="true" id="to_stop" class="to_stop form-control" required>
                                            <option value="">--Select--</option>
                                            @php
                                            foreach ($col_arr['ddTo'] as $id => $arr) {
                                                $selected = "";
                                                if ($id == $col_arr['to_stop'])
                                                    $selected = "selected=selected";
                                                echo "<option $selected value='$id'>$arr</option>";
                                            }
                                            @endphp
                                        </select>
                                    </td>
                                </tr>
                                @php
                                }
                                @endphp
                            </table>
                        </div>

                        <div class="w-100 d-block form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                    @php
                    }else{
                    echo "No Student Found.";
                    }
                    @endphp
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
    </div>
</div>


@include('includes.footerJs')
<script>

    $(function () {

        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
        $(".ckbox1").on("click", function () {
            var row = $(this).closest('tr');
            var from_bus = row.find('.from_bus'); // get the other select in the same row
            var from_shift = row.find('.from_shift'); // get the other select in the same row
            var from_stop = row.find('.from_stop'); // get the other select in the same row
            var to_bus = row.find('.to_bus'); // get the other select in the same row
            var to_shift = row.find('.to_shift'); // get the other select in the same row
            var to_stop = row.find('.to_stop'); // get the other select in the same row
            var distance = row.find('.distance'); // get the other select in the same row
            var distance_amount = row.find('.distance_amount'); // get the other select in the same row            

            from_bus.prop('disabled', function (i, v) {
                return !v;
            });
            from_shift.prop('disabled', function (i, v) {
                return !v;
            });
            from_stop.prop('disabled', function (i, v) {
                return !v;
            });
            to_bus.prop('disabled', function (i, v) {
                return !v;
            });
            to_shift.prop('disabled', function (i, v) {
                return !v;
            });
            to_stop.prop('disabled', function (i, v) {
                return !v;
            });
            distance.prop('disabled', function (i, v) {
                return !v;
            });
            distance_amount.prop('disabled', function (i, v) {
                return !v;
            });
//            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });

    });

    $('#myTable').on('change', '.from_shift', function () {
        var selectedValue = $(this).val();
        var row = $(this).closest('tr'); // get the row
        var from_bus = row.find('.from_bus'); // get the other select in the same row
        var from_stop = row.find('.from_stop'); // get the other select in the same row
//        var to_stop = row.find('.to_stop'); // get the other select in the same row
        from_bus.empty();
        from_bus.append('<option value="">--Select--</option>');
        from_stop.empty();
        from_stop.append('<option value="">--Select--</option>');
//        to_stop.empty();
//        to_stop.append('<option value="">--Select--</option>');


        $.ajax({
            url: "/api/get-bus-list?shift_id=" + selectedValue,
            type: "GET",
            success: function (res) {
                if (res) {
                    from_bus.empty();
                    from_bus.append('<option value="">--Select--</option>');
                    $.each(res, function (key, value) {
                        from_bus.append('<option value="' + key + '">' + value + '</option>');
                    });
                }
            }

        });
    });
    $('#myTable').on('change', '.from_bus', function () {
        var targetMSG = $(this).parent().find('span');
        var selectedValue = $(this).val();

        //START SET from bus value in to bus combo box and trigger change event to load "to_stop" combo box
        
        var student_id = $(this).attr("data-studentid");
        to_bus_selectbox = "values["+student_id+"][to_bus]";        
        $("[name='"+to_bus_selectbox+"']").val(selectedValue);       
        $("[name='"+to_bus_selectbox+"']").trigger( "change" );
        //END SET from bus value in to bus combo box and trigger change event to load "to_stop" combo box

        var row = $(this).closest('tr'); // get the row

        var from_stop = row.find('.from_stop'); // get the other select in the same row
        var from_shift = row.find('.from_shift'); // get the other select in the same row
//        var to_stop = row.find('.to_stop'); // get the other select in the same row

        from_stop.empty();
        from_stop.append('<option value="">--Select--</option>');
//        to_stop.empty();
//        to_stop.append('<option value="">--Select--</option>');

        var from_busID = $(this).val();
        var from_shiftID = from_shift.val();

        if (from_busID && from_shiftID) {
            $.ajax({
                type: "GET",
                url: "/api/get-stop-list?bus_id=" + from_busID + "&shift_id=" + from_shiftID,
                success: function (res) {
                    if (res) {
                        from_stop.empty();
                        from_stop.append('<option value="">--Select--</option>');
                        $.each(res, function (key, value) {
                            from_stop.append('<option value="' + key + '">' + value + '</option>');
                        });

//                        to_stop.empty();
//                        to_stop.append('<option value="">--Select--</option>');
//                        $.each(res, function (key, value) {
//                            to_stop.append('<option value="' + key + '">' + value + '</option>');
//                        });

                    }
                }
            });

            // check remain capacity
            var checkRemainCapacityPath = "{{ route('ajaxCheckRemainCapacity') }}";
            $.ajax({
                type: "GET",
                url: checkRemainCapacityPath,
                data: "bus_id=" + from_busID + "&shift_id=" + from_shiftID,
                success: function (res) {
                    if ( res.status == 200 ) {
                        console.log(res);
                        targetMSG.text('Total Capacity : '+ res.total_capacity +' / Remaining Capacity : '+ res.total_remain_capacity);

                        var textColor;
                        if ( res.total_remain_capacity > 0) {
                            textColor = 'green';
                        } else {
                            textColor = 'red';
                        }

                        targetMSG.css('color', textColor);
                      
                                                                                                               
                    }
                    // console.log(res);
                    /* if (res) {
                        from_stop.empty();
                        from_stop.append('<option value="">--Select--</option>');
                        $.each(res, function (key, value) {
                            from_stop.append('<option value="' + key + '">' + value + '</option>');
                        });

//                        to_stop.empty();
//                        to_stop.append('<option value="">--Select--</option>');
//                        $.each(res, function (key, value) {
//                            to_stop.append('<option value="' + key + '">' + value + '</option>');
//                        });

                    } */
                }
            });
        }


    });
    $('#myTable').on('change', '.to_shift', function () {
        var selectedValue = $(this).val();
        var row = $(this).closest('tr'); // get the row
        var to_bus = row.find('.to_bus'); // get the other select in the same row
        var to_stop = row.find('.to_stop'); // get the other select in the same row
//        var to_stop = row.find('.to_stop'); // get the other select in the same row
        to_bus.empty();
        to_bus.append('<option value="">--Select--</option>');
        to_stop.empty();
        to_stop.append('<option value="">--Select--</option>');
//        to_stop.empty();
//        to_stop.append('<option value="">--Select--</option>');


        $.ajax({
            url: "/api/get-bus-list?shift_id=" + selectedValue,
            type: "GET",
            success: function (res) {
                if (res) {
                    to_bus.empty();
                    to_bus.append('<option value="">--Select--</option>');
                    $.each(res, function (key, value) {
                        to_bus.append('<option value="' + key + '">' + value + '</option>');
                    });
                }
            }


        });
    });
    $('#myTable').on('change', '.to_bus', function () {

        var selectedValue = $(this).val();
        var row = $(this).closest('tr'); // get the row

        var to_stop = row.find('.to_stop'); // get the other select in the same row
        var to_shift = row.find('.to_shift'); // get the other select in the same row
//        var to_stop = row.find('.to_stop'); // get the other select in the same row

        to_stop.empty();
        to_stop.append('<option value="">--Select--</option>');
//        to_stop.empty();
//        to_stop.append('<option value="">--Select--</option>');

        var to_busID = $(this).val();
        var to_shiftID = to_shift.val();

        if (to_busID && to_shiftID) {
            $.ajax({
                type: "GET",
                url: "/api/get-stop-list?bus_id=" + to_busID + "&shift_id=" + to_shiftID,
                success: function (res) {
                    if (res) {
                        to_stop.empty();
                        to_stop.append('<option value="">--Select--</option>');
                        $.each(res, function (key, value) {
                            to_stop.append('<option value="' + key + '">' + value + '</option>');
                        });

//                        to_stop.empty();
//                        to_stop.append('<option value="">--Select--</option>');
//                        $.each(res, function (key, value) {
//                            to_stop.append('<option value="' + key + '">' + value + '</option>');
//                        });

                    }
                }
            });
        }
    });

    $('#myTable').on('change', '.from_stop', function () {
        var selectedValue = $(this).val();
        //START SET from stop value in to stop combo box        
        var student_id = $(this).attr("data-studentid");        
        to_stop_selectbox = "values["+student_id+"][to_stop]";          
        $("[name='"+to_stop_selectbox+"']").val(selectedValue);           
        //END SET from stop value in to stop combo box
    });

    // $('#myTable').on('change', '.from_shift', function () {
    //     console.log('change event called');
          
    //     var km_amount =$(this).data("fromshiftkm");
    //     var shift_rate = $(this).data("fromshiftrate");
    //     var stu_id = $(this).data("stud");
        
    //     var shifts =$(this).val()
    //     var distance = $('#distance\\[' + stu_id + '\\]').val();

    //     console.log(stu_id);                    
    //     console.log(distance);                    

    //     var distance_amt = (shift_rate + (distance * km_amount) );
    //     $('#distance_amount\\[' + stu_id + '\\]').val(distance_amt);
    // })
    $('#myTable').on('change', '.from_shift', function () {
    console.log('change event called');
    
    var selectedOption = $(this).find(':selected');
    
    var km_amount = selectedOption.data("fromshiftkm");
    var shift_rate = selectedOption.data("fromshiftrate");
    var stu_id = selectedOption.data("stud");
    
    var shifts = selectedOption.val();
    var distance = $('#distance_' + stu_id).val();
    
    var distance_amt = (shift_rate + (distance * km_amount));
    console.log(distance_amt);
    $('#distance_amount_' + stu_id).val(distance_amt);
});

</script>
@include('includes.footer')
