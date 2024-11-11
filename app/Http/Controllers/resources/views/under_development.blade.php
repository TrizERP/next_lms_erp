@include('includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">ERP SYSTEM SETTINGS</h4></div>
            <!-- /.col-lg-12 -->
        </div>
        <!-- /.row -->
        <!-- ============================================================== -->
        <!-- Different data widgets @if(!empty($data['message'])){{ $data['message'] }} @endif -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="row">
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="white-box analytics-info">
                    <h3 class="box-title">ERP SETTINGS.</h3>
                </div>
            </div>
        </div>

    </div>
    <!-- /.container-fluid -->
<!-- ============================================================== -->
<!-- End Page Content -->
<!-- ============================================================== -->
</div>

@include('includes.footerJs')

@if(Session::get('erpTour')['school_sidebar']==0)
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
            $('.mdi-school').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'.mdi-school',
          event:'new_todo',
          event_type:'custom',
          description:'You can do school settings here.'
        },
        {
          onBeforeStart: function(){
            $('#academicYears').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'.S',
          event:'new_todo',
          event_type:'custom',
          description:'Click on Student Quota.',
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
        var url = "http://202.47.117.124/tourUpdate?module=school_sidebar";
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
