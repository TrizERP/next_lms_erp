@include('includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Tilte</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3 m-30">
                    <a href="{{ route('fees_title.create') }}?implementation=1" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add New
                    </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Display Name</th>
                                    <th>Sort Order</th>
                                    <th>Cumulative Name</th>
                                    <th>Append Name</th>
                                    <th>Mandatory</th>
                                    <th>Syear</th>
                                    <th>Fee Type</th>                                    
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
                                    <td>{{$data['display_name']}}</td>
                                    <td>{{$data['sort_order']}}</td>                                    
                                    <td>{{$data['cumulative_name']}}</td>
                                    <td>{{$data['append_name']}}</td>
                                    <td>{{$data['mandatory']}}</td>
                                    <td>{{$data['syear']}}</td>
                                    <td>{{$data['other_fee_id']}}</td>
                                    <td>
                                        <!-- <div class="d-inline">                                        
                                            <a href="{{ route('fees_title.edit',$data['id'])}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div> -->
                                        <form action="{{ route('fees_title.destroy', $data['id'])}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
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

@include('includes.footerJs')

@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@endif

@if(Session::get('erpTour')['fees_title']==0)
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
    <script>
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
          description:'Click here to add new title.'
        }
      ];
      var enjoyhint_instance = null;
      $(document).ready(function(){
        enjoyhint_instance = new EnjoyHint({});
        enjoyhint_instance.setScript(enjoyhint_script_data);
        enjoyhint_instance.runScript();
      });
    </script>
@endif
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
                                                    $(document).ready(function () {
                                                        $('#example').DataTable({

                                                        });
                                                    });

</script>

@include('includes.footer')
