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
                            <div class="row" style="padding:0px 10px;">
                                {{-- @if(isset($data['profiles']))
                                    <div class="col-md-3 form-group ml-0">
                                        <label>User</label>
                                        <select name="profile" id="profile" required="required" class="form-control">
                                            <option value=""> Select User Profile</option>
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
                                @endif --}}
                                @php 
                                    $profileIds = $empIds = '';
                                    if(isset($data['profileIds']) && !empty($data['profileIds'])){
                                        $profileIds = $data['profileIds'];
                                    }
                                    if(isset($data['employeeIds']) && !empty($data['employeeIds'])){
                                        $empIds = $data['employeeIds'];
                                    }
                                @endphp

                                {!! App\Helpers\userProfileEmployee(3, $profileIds, 'multiple', $empIds, 'multiple') !!}

                                <!-- added on 17-04-24 by uma -->
                                <div class="col-md-3 form-group ml-0">
                                        <label>Status</label>
                                        <select name="status" id="status" required="required" class="form-control">
                                          <option value="1">Active</option>
                                          <option @if(isset($data['status']) && $data['status']==0) selected @endif value="0">In-Active</option>
                                        </select>
                                    </div>
                                <!-- end by uma -->
                                <div class="col-md-12">
                                <div class="dataDiv" style="padding:10px;max-height:350px;overflow-y:scroll;border:1px solid #ddd;">
                                @php 
                                    $i=0;
                                 @endphp
                                    @foreach($data['data'] as $header => $headerValue)

                                    @if(isset($data['data'][$header]) && !empty($data['data'][$header]))
                                          <div class="col-md-12 form-group py-8">
                                             <div class="row mb-4"  style="border-bottom:1px solid black">
                                                <div class="col-md-4"><h4><b>{{$header}}</b></h5></div>
                                                <div class="col-md-2">Check All <input type="checkbox" onclick="checkAll('chkClass{{$i}}')" class="chkClass{{$i}}"></div>
                                             </div>
                                             <div class="row"  style="width:80%">
                                             @foreach($data['data'][$header] as $key => $value)
                                             @php $val = $value->field_name.'/'.$value->id;
                                                $joinVal = str_replace(' ','',$value->field_label);
                                                $lowerCase = strtolower($joinVal);
                                                // norm clature
                                                $checkNorm = DB::table('app_language')->where('sub_institute_id',session()->get('sub_institute_id'))->where('string',$joinVal)->first(); 

                                                   if(!empty($checkNorm)){
                                                      $value->field_label = $checkNorm->value;
                                                   }
                                             @endphp
                                             <div class="col-md-2 pb-2"><input type="checkbox" name="dynamicFields[]" class="chkClass{{$i}}" value="{{$val}}" @if(isset($data['dynamicFields']) && in_array($val,$data['dynamicFields'])) checked @endif> {{$value->field_label}}</div>
                                             @endforeach
                                             </div>
                                          </div>
                                    @endif
                                    @php 
                                          $i++;
                                    @endphp
                                    @endforeach
                                </div>
                                </div>
                                <div class="col-md-12 pt-2 form-group">
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
                        <div class="card table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    @foreach($data['headers'] as $hkey => $header)
                                        @php 
                                        $joinVal = str_replace(' ','',$header);
                                        $lowerCase = strtolower($joinVal);
                                        // norm clature
                                        $checkNorm = DB::table('app_language')->where('sub_institute_id',session()->get('sub_institute_id'))->where('string',$joinVal)->first(); 

                                            if(!empty($checkNorm)){
                                                $header = $checkNorm->value;
                                            }
                                        @endphp
                                        <th class="text-left"> {{$header}} </th>
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
        // var checked = false;

        // function checkedAll() {
        //     if (checked == false) {
        //         checked = true
        //     } else {
        //         checked = false
        //     }
        //     for (var i = 0; i < document.getElementsByName('dynamicFields[]').length; i++) {
        //         document.getElementsByName('dynamicFields[]')[i].checked = checked;
        //     }
        // }
        function checkAll(chkName){
            $('.'+chkName).each(function() {
                    $(this).prop('checked', !$(this).prop('checked'));
                });
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
                            .search(this.value)
                            .draw();
                    }
                });
            });
        });
    </script>
@include('includes.footer')
