@include('includes.headcss')
	<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Student Quota </h4> </div>
        </div>        
            <div class="card">
            <div class="panel-body">
				<!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
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

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('student_quota.store') }}" enctype="multipart/form-data" method="post">

                        {{ method_field("POST") }}

                            @csrf

                        <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{App\Helpers\get_string('studentquota','request')}}</label>
                            <input type="text" id='title' required name='title' class="form-control" pattern="[a-zA-Z\s]+">
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Sort Order </label>
                            <input type="number" id='sort_order' required name='sort_order' class="form-control" value="{{$max_sort_order}}">
                        </div>

                        <div class="col-md-12 form-group">
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                        </div>
                        </div>


                    </form>
            </div>
            </div>
        </div>

@include('includes.footerJs')
@if(Session::get('erpTour')['student_quota']==0)
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
            $('#title').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#title',
          event:'new_todo',
          event_type:'custom',
          description:'Enter title of quota.'
        },
        {
            onBeforeStart: function(){
            $('#sort_order').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#sort_order',
          event:'new_todo',
          event_type:'custom',
          description:'Enter title of quota.'
        },
        {
          selector:'.btn-success',
          event:'click',
          description:'Please press save to add new quota.',
          timeout:100
        }
      ];
      var enjoyhint_instance = null;
      $(document).ready(function(){
        enjoyhint_instance = new EnjoyHint({});
        enjoyhint_instance.setScript(enjoyhint_script_data);
        enjoyhint_instance.runScript();
      });
    </script>
    <script type="text/javascript">
        var url = "http://202.47.117.124/tourUpdate?module=student_quota";
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
              console.log("success");
            }
        };
        xhttp.open("GET", url, true);
        xhttp.send();
    </script>
@endif
@include('includes.footer')
