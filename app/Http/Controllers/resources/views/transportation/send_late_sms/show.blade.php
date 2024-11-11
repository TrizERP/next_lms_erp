@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Send Late SMS</h4>            
            </div>                    
        </div>
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">            
                    <form action="{{ route('send_late_sms.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    Select Shift 
                                    <select name="shift" id="shift" class="form-control shift">
                                        <option value="">--Select--</option>
                                        <?php
                                        foreach ($data['data']['ddShift'] as $id => $arr) {
                                            echo "<option value='$id'>$arr</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    Select Bus 
                                    <select name="bus" id="bus" class="bus form-control">
                                        <option value="">--Select--</option>
                                        <option value="">1</option>
                                        <option value="">2</option>
                                        <option value="">3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    Select Stop 
                                    <select name="stop[]" id="from_stop" multiple class="from_stop form-control">
                                        <option value="">--Select--</option>
                                        <option value="">Katargam</option>
                                        <option value="">varachha</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
});
$('#myTable').on('change', '.shift', function () {
    var selectedValue = $(this).val();
    var row = $(this).closest('tr'); // get the row
    var bus = row.find('.bus'); // get the other select in the same row
    var from_stop = row.find('.from_stop'); // get the other select in the same row
    var to_stop = row.find('.to_stop'); // get the other select in the same row
    bus.empty();
    bus.append('<option value="">--Select--</option>');
    from_stop.empty();
    from_stop.append('<option value="">--Select--</option>');
    to_stop.empty();
    to_stop.append('<option value="">--Select--</option>');


    $.ajax({
        url: "/api/get-bus-list?shift_id=" + selectedValue,
        type: "GET",
        success: function (res) {
            if (res) {
                bus.empty();
                bus.append('<option value="">--Select--</option>');
                $.each(res, function (key, value) {
                    bus.append('<option value="' + key + '">' + value + '</option>');
                });
            }
        }


    });
});
$('#myTable').on('change', '.bus', function () {

    var selectedValue = $(this).val();
    var row = $(this).closest('tr'); // get the row

    var from_stop = row.find('.from_stop'); // get the other select in the same row
    var shift = row.find('.shift'); // get the other select in the same row
    var to_stop = row.find('.to_stop'); // get the other select in the same row

    from_stop.empty();
    from_stop.append('<option value="">--Select--</option>');
    to_stop.empty();
    to_stop.append('<option value="">--Select--</option>');

    var busID = $(this).val();
    var shiftID = shift.val();

    if (busID && shiftID) {
        $.ajax({
            type: "GET",
            url: "/api/get-stop-list?bus_id=" + busID + "&shift_id=" + shiftID,
            success: function (res) {
                if (res) {
                    from_stop.empty();
                    from_stop.append('<option value="">--Select--</option>');
                    $.each(res, function (key, value) {
                        from_stop.append('<option value="' + key + '">' + value + '</option>');
                    });

                    to_stop.empty();
                    to_stop.append('<option value="">--Select--</option>');
                    $.each(res, function (key, value) {
                        to_stop.append('<option value="' + key + '">' + value + '</option>');
                    });

                }
            }
        });
    }
});
</script>
@include('includes.footer')
