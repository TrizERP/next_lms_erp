@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Receipt Book</h4>
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
			<div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3 mb-30">
                    <a href="{{ route('fees_receipt_book_master.create') }}?implementation=1" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add New Receipt Book 
                    </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Rec line 1</th>
                                    <th>Rec line 2</th>
                                    <th>Rec line 3</th>
                                    <th>Rec line 4</th>
                                    <th>Rec prefix</th>
                                    <th>Rec postfix</th>
                                    <th>Adm. section</th>
                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                    <th>Fees title</th>
                                    <th>Sort order</th>
                                    <th>Status</th>
                                    <th>Account number</th>
                                    <th>Action</th>
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
                                    <td>{{$data->receipt_line_1}}</td>
                                    <td>{{$data->receipt_line_2}}</td>
                                    <td>{{$data->receipt_line_3}}</td>
                                    <td>{{$data->receipt_line_4}}</td>
                                    <td>{{$data->receipt_prefix}}</td>
                                    <td>{{$data->receipt_postfix}}</td>
                                    <td>{{$data->grade}}</td>
                                    <td>{{$data->standard}}</td>
                                    <td>{{$data->fees_head}}</td>
                                    <td>{{$data->sort_order}}</td>
                                    <td>{{$data->status}}</td>
                                    <td>{{$data->account_number}}</td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-end">                                            
                                            <a href="{{ route('fees_receipt_book_master.edit',$data->receipt_id)}}" class="btn btn-info btn-outline mr-1">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        
                                            <form action="{{ route('fees_receipt_book_master.destroy', $data->receipt_id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                                <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">    <i class="ti-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
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

</div>

@include('includes.footerJs')

@if(Session::get('erpTour')['fees_receipt']==0)
<script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
        <!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
        <script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
        <script src="../../../tooltip/bower_components/backbone/backbone.js"></script>
        <script src="../../../tooltip/bower_components/backbone.localStorage/backbone.localStorage.js"></script>
        <script src="../../../tooltip/js/models/todo.js"></script>
        <script src="../../../tooltip/js/collections/todos.js"></script>
        <script src="../../../tooltip/js/views/todo-view.js"></script>
        <script src="../../../tooltip/js/views/app-view.js"></script>
        <script src="../../../tooltip/js/routers/router.js"></script>
        <script src="../../../tooltip/js/app.js"></script>
        <script src="../../../tooltip/enjoyhint/enjoyhint.js"></script>
        <script src="../../../tooltip/enjoyhint/jquery.enjoyhint.js"></script>
        <script src="../../../tooltip/enjoyhint/kinetic.min.js"></script>
    <!-- <script>
      localStorage.clear();
      var enjoyhint_script_data = [
        {
            onBeforeStart: function(){
            $('.add-new').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'.add-new',
          event:'new_todo',
          event_type:'custom',
          description:'Click here to add new receipt book.'
        }
      ];
      var enjoyhint_instance = null;
      $(document).ready(function(){
        enjoyhint_instance = new EnjoyHint({});
        enjoyhint_instance.setScript(enjoyhint_script_data);
        enjoyhint_instance.runScript();
      });
    </script> -->
@endif

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script>
@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@endif
@include('includes.footer')
