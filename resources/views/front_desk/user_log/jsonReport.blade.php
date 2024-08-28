@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid"> 
        <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Detailed User Log Repot</h4> </div>
            </div>     
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert @if($data['status_code']==1) alert-success @else alert-danger @endif alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>SR No</th>
                                    <th>Date Time</th>
                                    <th>Full Name</th>
                                    <th>User Profile</th>
                                    <th>Module</th>
                                    <th>Event</th>
                                    <th>Description</th>
                                    <th class="text-left">IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['jsonData'] as $key => $value)
                                <!-- for admin  -->
                                @if($value['userprofile']=="Admin")
                                <tr>
                                <td>{{$key+1}}</td>
                                    <td>{{$value['datetime']}}</td>
                                    <td>{{$value['fullname']}}</td>
                                    <td>{{$value['userprofile']}}</td>
                                    <td>{{$value['module']}}</td>
                                    <td>{{$value['event']}}</td>
                                    <td>
                                        @if(isset($value['description']['query']))
                                        <span class="hover-trigger-{{$key+1}}" onmouseover="openModal({{$key+1}})">{{ substr($value['description']['query'], 0, 200) }}</span> 
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td>{{$value['ip_address']}}</td>
                                </tr>
                                <!-- for other users  -->
                                @else if($value['fullname']==session()->get('name'))
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value['datetime']}}</td>
                                    <td>{{$value['fullname']}}</td>
                                    <td>{{$value['userprofile']}}</td>
                                    <td>{{$value['module']}}</td>
                                    <td>{{$value['event']}}</td>
                                    <td>
                                        @if(isset($value['description']['query']))
                                        <span class="hover-trigger-{{$key+1}}" onmouseover="openModal({{$key+1}})">{{ substr($value['description']['query'], 0, 200) }}</span> 
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td>{{$value['ip_address']}}</td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>      
    </div>
</div>
<!-- Modal for detail pop up-->
@foreach($data['jsonData'] as $key => $value)
<div class="modal fade" id="model_{{$key+1}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{{$value['module'].' - '.$value['datetime']}}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        @if(isset($value['description']['query']))
            <!-- dispaly whole query  -->
            <h6>Query : </h6>
            <p>{{ $value['description']['query'] }}</p>
            <!-- display binding values or requested values -->
            @if(isset($value['description']['bindings']))
            <h6>Changed Values : </h6>
            <ul>
            @foreach($value['description']['bindings'] as $k => $v)
                <li>{{$k+1}}) {{$v}} </li>
            @endforeach
            </ul>
            @endif
        @else
        -
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endforeach
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
                    title: 'Detailed User Log Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Detailed User Log Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Detailed User Log Report'},
                {extend: 'print', text: ' PRINT', title: 'Detailed User Log Report'},
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
                        .search(this.value)
                        .draw();
                }
            });
        });
      
    });

    function openModal(key) {
        $('.hover-trigger-'+key).hover(function() {
            $('#model_'+key).modal('show');
        }, function() {
            $('#model_'+key).modal('hide');
        });
    }

</script>
@include('includes.footer')
@endsection