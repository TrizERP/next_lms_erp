@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Whatsapp User Details</h4>
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
                @if ($data['is_hidden'] == false)
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('whatsapp_user_details.create') }}" class="btn btn-info add-new"><i
                                class="fa fa-plus"></i> Whatsapp User Details </a>
                    </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>User Whatsapp No</th>
                                    <th>User Whatsapp SID</th>
                                    <th>User Whatsapp Token</th>
                                    <th>Created By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                            @endphp
                            @foreach($data['data'] as $key => $data)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data->user_whatsapp_no}}</td>
                                    <td>{{$data->user_whatsapp_sid}}</td>
                                    <td>{{$data->user_whatsapp_token}}</td>
                                    <td>{{$data->created_by_name ?? '-'}}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ url('whatsapp-user-details/create/'.$data->id)}}"
                                               class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        {{-- <form class="d-inline" action="{{ route('whatsapp_user_details.destroy', $data->id)}}" method="post">
                                             @csrf
                                             @method('DELETE')
                                             <button type="submit" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                         </form>--}}
                                    </td>
                                </tr>
                                @php
                                    $j++;
                                @endphp
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
