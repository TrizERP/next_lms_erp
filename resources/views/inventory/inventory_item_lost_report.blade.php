@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Lost Item Report</h4> </div>
            </div>
        
        <div class="row">
            <div class="white-box">
                <div class="panel-body">
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
                    <form action="{{ route('inventory_item_lost_report.create') }}" enctype="multipart/form-data">
                        
                            @csrf
                        
                        <div class="col-md-4 form-group">
                            <label>From Date</label>
                            <input type="text" name="from_date" class="form-control mydatepicker" placeholder="Please select from date." required="required" value="@if(isset($data['from_date'])) {{$data['from_date']}} @endif">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>To Date</label>
                            <input type="text" name="to_date" class="form-control mydatepicker" placeholder="Please select to date." required="required" value="@if(isset($data['to_date'])) {{$data['to_date']}} @endif">
                        </div>
                       
                         <div class="col-md-4 form-group">
                            <label for="item_id">Item</label>
                            <select name="lost_item_id" id="lost_item_id" class="form-control">
                                <option value="">Select Item</option>
                                @foreach($data['lost_item'] as $k => $v  )
                                    <option value="{{$v->ITEM_ID}}"
                                    @if(isset($data['lost_item_id']))
                                        @if($data['lost_item_id'] == $v->ITEM_ID)
                                        selected='selected'
                                        @endif
                                    @endif
                                    >{{ $v->TITLE }}</option>
                                @endforeach
                            </select>
                        </div>

                        <center>
                            <div class="col-md-12 form-group">
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                            </div>
                        </center>

                    </form>
                </div>

        @if(isset($data['result_report']))
        @php
        $j = 1;
            if(isset($data['result_report'])){
                $result_report = $data['result_report'];
            }
        @endphp
                <div class="table-responsive">
                    <table id="example" class="table table-striped table-bordered display">
                        <thead>
                            <tr>
                                <th>SR NO</th>
                                <th>Name of Item</th>
                                <th>Staff Responsible</th>
                                <th>Lost Date</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result_report as $key => $value)
                                <tr>
                                    <td>{{$j++}}</td>
                                    <td> {{$value->ITEM_NAME}} </td>
                                    <td> {{$value->REQUISITION_BY_NAME}} </td>
                                    <td> {{$value->LOST_DATE}} </td>
                                    <td> {{$value->ITEM_QTY}} </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>


@include('includes.footerJs')
<script>
$(document).ready(function() {
    $('#example').DataTable( {
        dom: 'Bfrtip',
        buttons: [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5'
        ]
    } );
    $('.buttons-copy, .buttons-csv, .buttons-print, .buttons-pdf, .buttons-excel').addClass('btn btn-primary m-r-10');
    //$('.paginate_button').addClass('btn btn-info m-r-10');
} );
</script>
@include('includes.footer')

