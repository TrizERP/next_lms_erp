@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style>
    .imageTD img{
        width:100px !important;
        height: 100px !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Whatsapp Send Messages</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert {{ ($sessionData['status_code']==0) ? 'alert-danger' :  'alert-success'}}  alert-block">
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
                                <th>Status</th>
                                <th class="text-left">Message</th>
                                <th class="text-left">Attachment</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($data['data'] as $key => $data)
                                <tr>
                                    <td>{{$key + 1}}</td>
                                    <td>{{ (isset($data['standard'][0])) ? $data['standard'][0]['name'] : '-'}}</td> <!-- added on 22-08-2024 -->
                                    <td>{{ (isset($data['division'][0])) ? $data['division'][0]['name'] : '-'}}</td> <!-- added on 22-08-2024 -->
                                    <td>{{ (isset($data['student'][0])) ? $data['student'][0]['first_name'].' '.$data['student'][0]['last_name'] : '-'}}</td>
                                    <td>{{ (isset($data['student'][0])) ? $data['student'][0]['mobile'] : '-' }}</td>
                                    <td>{{$data['created_by_name'] ?? '-'}}</td>
                                    <td>{!! $data['message_status'] ?? '-' !!}</td>
                                    <td class="imageTD">{!! $data['message'] ?? '-' !!}</td>
                                    <td><a href="{{$data['attachment']}}" target="_blank">View</a></td>
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
