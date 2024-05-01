@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Whatsapp Send Messages</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('whatsapp_send_message.create') }}" class="btn btn-info add-new"><i
                            class="fa fa-plus"></i> Whatsapp Send Message </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Sr No</th>
                                <th>Standard</th>
                                <th>Division</th>
                                <th>Student Name</th>
                                <th>Mobile</th>
                                <th>Created By</th>
                                <th class="text-left">Message</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($data['data'] as $key => $data)
                                <tr>
                                    <td>{{$key + 1}}</td>
                                    <td>{{$data['standard_id']}}</td>
                                    <td>{{$data['division_id']}}</td>
                                    <td>{{ (isset($data['student'][0])) ? $data['student'][0]['first_name'].' '.$data['student'][0]['last_name'] : '-'}}</td>
                                    <td>{{ (isset($data['student'][0])) ? $data['student'][0]['mobile'] : '-' }}</td>
                                    <td>{{$data['created_by_name'] ?? '-'}}</td>
                                    <td>{{$data['message'] ?? '-'}}</td>
                                </tr>
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

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
    $(document).ready(function () {
        $('#example').DataTable();
    });

</script>
@include('includes.footer')
