@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Available Room Report</h4> </div>
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
                    <form action="{{ route('show_room_report') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            @if(isset($data['hostelList']))
                            <div class="col-md-4 form-group">
                                <label for="hostel">Hostel</label>
                                <select name="hostel" id="hostel" onchange="getRooms(this.value)" class="form-control">
                                    <option value=""> Select Hostel  </option>
                                        @foreach($data['hostelList'] as $key => $value)
                                        @php
                                                $selected = '';
                                            @endphp
                                        @if(isset($data['hostel']))
                                            @if($data['hostel'] == $key)
                                                @php
                                                $selected = 'selected="selected"';
                                                @endphp
                                            @endif
                                        @endif
                                        <option value="{{$key}}" {{$selected}}> {{$value}} </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if(isset($data['hostelList']))
                            <div class="col-md-4 form-group">
                                <label for="room">Room</label>
                                <select name="room" id="room"  class="form-control">
                                    <option value=""> Select Room  </option>
                                </select>
                                </div>
                            @endif

                            <div class="col-md-4 form-group mt-4">
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
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
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Room Name</th>
                            <th>Floor Name</th>
                            <th>Building Name</th>
                            <th>Hostel Name</th>
                            <th>Hostel Type</th>
                        </tr>
                        </thead>

                        <tbody>
                        @php
                            $j=1;
                        @endphp
                        @foreach($data['data'] as $keyData => $valueData)
                            <tr>
                                <td>{{$j}}</td>
                                <td>{{$valueData['room_name']}}</td>
                                <td>{{$valueData['floor_name']}}</td>
                                <td>{{$valueData['building_name']}}</td>
                                <td>{{$valueData['name']}}</td>
                                <td>{{$valueData['hostel_type']}}</td>
                                @php
                                    $j++;
                                @endphp
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        @endif


</div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Available Room Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Available Room Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Available Room Report'},
                {extend: 'print', text: ' PRINT', title: 'Available Room Report'},
                'pageLength'
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
<script>
    function getRooms(hostel_id,room_id = 0){
        var path = "{{ route('hostelWiseRoomList') }}";
        var roomId = "#room";
        $(roomId).find('option').remove().end().append('<option value=""> Select Room </option>').val('');
        $.ajax({
            type: 'POST',
            url: path,
            data: {hostel_id: hostel_id},
            success: function (data) {
                for (var i = 0; i < data.length; i++) {

                    if (data[i]['id'] == room_id) {
                        $(roomId).append($("<option value=''> Select Room </option>").val(data[i]['id']).html(data[i]['room_name']).attr("selected", "selected"));
                    } else {
                        $(roomId).append($("<option value=''> Select Room </option>").val(data[i]['id']).html(data[i]['room_name']));
                    }
                }
            }
        });
    }

    $(document).ready(function () {
        var hostel = @if(isset($data['hostel'])) {{$data['hostel']}} @else '0' @endif;
        var room_id = @if(isset($data['room'])) {{$data['room']}} @else '0' @endif;
        if(hostel != 0)
        {
            getRooms(hostel,room_id);
        }
    });
</script>
@include('includes.footer')
