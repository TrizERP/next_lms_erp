@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">User Report</h4>
            </div>
        </div>
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
                        <form action="{{ route('show_user_report') }}" method="post">
                            @csrf
                            <div class="row">
                                @if(isset($data['profiles']))
                                <div class="col-md-3 form-group ml-0">
                                    <label>User</label>
                                    <select name="profile" id="profile" required="required" class="form-control">
                                        <option value=""> Select User Profile </option>
                                        @foreach($data['profiles'] as $key => $value)
                                            @php
                                            $checked = '';
                                            if(isset($data['profile'])){
                                                if($data['profile'] == $key){
                                                    $checked = "selected='selected'";
                                                }
                                            }
                                            @endphp
                                            <option value="{{$key}}" {{$checked}}>{{$value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-12 form-group">
                                    <div class="checkbox checkbox-info">
                                        <input id="checkall" onclick="checkedAll();" name="checkall" type="checkbox">
                                        <label for="checkall"> Check All </label>
                                    </div>
                                </div>
                                @if(isset($data['data']))
                                @php
                                $checkedArray = array();
                                @endphp
                                @foreach($data['data'] as $key => $value)
                                <div class="form-group col-md-2 ml-0 mr-0">
                                    <div class="custom-control custom-checkbox d-flex align-items-center">
                                        @php
                                        $checked = '';
                                        if(in_array($key,$checkedArray)){
                                            $checked = 'checked="checked"';
                                        }
                                        if(isset($data['headers'])){
                                            if(count($data['headers']) > 0){
                                                $headersChecked = array_keys($data['headers']);
                                            }
                                            $checked = '';
                                            if(in_array($key,$headersChecked)){
                                                $checked = 'checked="checked"';
                                            }
                                        }
                                        @endphp
                                        <input id="{{$key}}" {{$checked}} value="{{$key}}" class="custom-control-input" name="dynamicFields[]" type="checkbox">
                                        <label class="custom-control-label mb-0 pt-1" for="{{$key}}"> {{$value}} </label>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                                <div class="col-md-12 form-group">
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </div>
                            </div>
                        </form>
                        </div>

                    @if(isset($data['user_data']))
                @php
                    if(isset($data['user_data'])){
                        $user_data = $data['user_data'];
                    }
                @endphp
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            @foreach($data['headers'] as $hkey => $header)
                                <th> {{$header}} </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($user_data as $key => $value)
                            <tr>
                                @foreach($data['headers'] as $hkey => $header)
                                    @if($hkey == "birthdate")
                                        <td> {{date('d-m-Y',strtotime($value->$hkey))}}</td>
                                    @else
                                        <td> {{$value->$hkey}} </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
        </div>
        @endif
    </div>

    @include('includes.footerJs')
    <script>
        var checked = false;

        function checkedAll() {
            if (checked == false) {
                checked = true
            } else {
                checked = false
            }
            for (var i = 0; i < document.getElementsByName('dynamicFields[]').length; i++) {
                document.getElementsByName('dynamicFields[]')[i].checked = checked;
            }
        }
    </script>
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
                        title: 'User Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'User Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'User Report'},
                    {extend: 'print', text: ' PRINT', title: 'User Report'},
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
@include('includes.footer')
