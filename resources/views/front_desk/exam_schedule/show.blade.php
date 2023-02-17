@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Exam Schedule</h4>            
            </div>                    
        </div>
        <div class="card">
            <form action="{{ route('exam_schedule.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                {{csrf_field()}}
                <div class="row">
                    <div class="col-md-12 form-group">
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                        </div>    
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Date</label>
                        <input type="text" name="date_" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" >
                    </div>
                    <div class="col-md-4 form-group">
                        <label>File</label>
                        <input type="file" name="attechment" class="form-control">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>
                </div>    
            </form>
        </div>  
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Syear</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th>File</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $j=1;
                                @endphp
                                @if(isset($data['data']))
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$data->syear}}</td>  
                                    <td>{{$data->title}}</td>  
                                    <td>{{date('d-m-Y',strtotime($data->date_))}}</td>
                                    <td>{{$data->std_name}}</td>  
                                    <td>{{$data->division_name}}</td> 
                                    <td><a href="<?php echo asset('storage/exam_schedule/' . $data->file_name); ?>" target="_blank">View</a> </td> 
                                </tr>
                                @php
                                $j++;
                                @endphp
                                @endforeach
                                @endif
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
      $(document).ready(function () {
        hiddenTableHeader = "All Data";
        hiddenTableHeader_short = "All Data";
        var table = $('#example').DataTable({
            pageLength: 10,
            orderCellsTop: true,
            fixedHeader: true,
            dom: 'Bfrtip',
            buttons: [{
                    extend: 'excel',
                    title: "Data export",
                    messageTop: hiddenTableHeader
                },
                {
                    extend: 'pdf',
                    title: "Data export",
                    messageTop: hiddenTableHeader_short
                },
                {
                    extend: 'print',
                    title: "Data export",
                    customize: function (win) {
                        $(win.document.body)
                            .css('font-size', '10pt')
                            .prepend(
                                tableheader
                            );

                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
                    }
                }
            ]
        });
    });
//    $("#division").parent('.form-group').hide();
</script>
@include('includes.footer')
