@include('../includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Fees Tilte</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('fees_title.store') }}" enctype="multipart/form-data" method="post">
                      {{ method_field("POST") }}
                      @csrf
                        <div class="row">                          
                          <div class="col-md-3 form-group">
                              <label>Fees Title</label>
                              <select name="fees_title_id" id="title" class="form-control van">
                                  <option value="">--Select--</option>
                                  <?php
                                    foreach ($data['data']['ddTtitle'] as $id => $arr) {
                                  	 echo "<option value='$id'>$arr</option>";
                                    }
                                  ?>
                              </select>
                          </div>
                          <div class="col-md-3 form-group">
                              <label>Display Name</label>
                              <input type="text" id='display_name' required name="display_name" class="form-control">
                          </div>
                          <div class="col-md-3 form-group">
                              <label>Cumulative Name</label>
                              <input type="text" id='cumulative_name' name="cumulative_name" class="form-control">
                          </div>
                          <div class="col-md-3 form-group">
                              <label>Append Name</label>
                              <input type="text" id='append_name' name="append_name" class="form-control">
                          </div>
                          <div class="col-md-3 form-group">
                              <label>Sort Order</label>
                              <input type="number" id='sort_order' name="sort_order" class="form-control">
                          </div>
                          <div class="col-md-3 form-group ml-0 mr-0">
                              <label>Mandetory </label>
                              <div class="checkbox checkbox-info">
                                  <input id="mandatory" name="mandatory" value="1" type="checkbox">
                                  <label for="mandatory"> Mandatory </label>
                              </div>
                          </div>                          
                          <div class="col-md-12 form-group">
                              <center>                                
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                              </center>
                          </div>
                        </div>
                    </form>
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
            $('#title').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#title',
          event:'new_todo',
          event_type:'custom',
          description:'Select title here.'
        },
        {
            onBeforeStart: function(){
            $('#display_name').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#display_name',
          event:'new_todo',
          event_type:'custom',
          description:'Enter display name of title.'
        },
        {
          selector:'#mandatory',
          event:'click',
          description:'Check if title is mandatory.',
          timeout:100
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
        var url = "http://202.47.117.124/tourUpdate?module=fees_title";
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
