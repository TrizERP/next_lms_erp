@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Mobile App Menu Rights</h4>
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
                    <a href="{{ route('mobile_app_menu_rights.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Manage Mobile App Menu Rights
                    </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>User Profile Title</th>
                                    <th>Sub Title of Main</th>
                                    <th>Main Title</th>
                                    <th>Screen Name</th>
                                    <th><center>Rights</center></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $j=1;
                                @endphp
                                @foreach($data['get_mobile_homescreens'] as $key => $get_mobile_homescreen)
                                    <tr>    
                                        <td>{{$j}}</td>
                                        <td>{{ $get_mobile_homescreen->user_profile_name }} </td>
                                        <td>{{ $get_mobile_homescreen->sub_title_of_main }}</td>
                                        <td>{{ $get_mobile_homescreen->main_title }}</td>
                                        <td>{{ $get_mobile_homescreen->screen_name }}</td>
                                        <td><center>
                                        <i class="fa fa-check text-success"></i>
                                        </center></td>
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
        $('#example').DataTable({});
    });
</script>
@include('includes.footer')
