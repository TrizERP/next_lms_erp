{{--
@include('../includes.headcss')
--}}
@extends('layout')
@section('container')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
{{--@include('../includes.header')
@include('../includes.sideNavigation')--}}


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Map Year</h4>
            </div>
        </div>
            <div class="card">
                <div class="col-lg-12 col-sm-12 col-xs-12">

                    <form action="{{ route('map_year.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="fee_interval">Select Fee Type:</label>
                                <select name="fee_type" id="fee_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="yearly_fees">Yearly Fees</option>
                                    <option value="half_year_fees">Half Year Fees</option>
                                    <option value="quarterly_fees">Quarterly Fees</option>
                                    <option value="monthly_fees">Monthly Fees</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Starting Month</label>
                                <select name="start_month" id="start_month" class="form-control" required>
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddMonth'] as $id => $arr) {
                                        echo "<option value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group ml-0">
                                <label>Ending Month</label>
                                <select name="end_month" id="end_month" class="form-control" required>
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddMonth'] as $id => $arr) {
                                        echo "<option value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-12 form-group ml-0">
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </div>
                        </div>

                    </form>
                </div>
            </div>
    </div>
</div>


@include('includes.footerJs')

@if(Session::get('erpTour')['fees_map']==0)
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
            $('#start_month').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#start_month',
          event:'new_todo',
          event_type:'custom',
          description:'Select start month here.'
        },
        {
            onBeforeStart: function(){
            $('#end_month').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#end_month',
          event:'new_todo',
          event_type:'custom',
          description:'Enter end month here.'
        },
        {
          selector:'.btn-success',
          event:'click',
          description:'Please press save to add new title.',
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
        var url = "http://202.47.117.124/tourUpdate?module=fees_map";
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

@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    // document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    // document.getElementById('main-header').style.display = 'none';
</script>
@endif
@include('includes.footer')
@endsection
