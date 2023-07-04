@include('includes.headcss')
    <!-- <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css"> -->
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Structure</h4>
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
                <div class="col-lg-3 col-sm-3 col-xs-3 mb-20">
                    <a href="{{ route('fees_breackoff.create') }}?implementation=1" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add New
                    </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Syear</th>
                                    <th>Fee Head</th>
                                    <th>Admission</th>
                                    <th>{{ App\Helpers\get_string('studentquota','request')}}</th>
                                    <th>Grade</th>
                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                    {{-- <th>Division</th> --}}
                                    <th>Month</th>
                                    <th>Amount</th>
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
                                    <td>{{$data->syear}}</td>
                                    <td>{{$data->fees_head}}</td>
                                    <td>{{$data->admission_year}}</td>
                                    <td>{{$data->quota}}</td>
                                    <td>{{$data->grade_name}}</td>
                                    <td>{{$data->sta_name}}</td>
                                    <td>{{$data->month_id}}</td>
                                    <td>{{$data->amount}}</td>
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

@if(Session::get('erpTour')['fees_structure']==0)
<!-- <script src="../../../tooltip/bower_components/todomvc-common/base.js"></script> -->
<!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
<!-- <script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
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
<script src="../../../tooltip/enjoyhint/kinetic.min.js"></script> -->
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
          description:'Click here to add new Breakoff.'
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

<!-- <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script> -->
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
});

</script>

@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@endif

@include('includes.footer')
