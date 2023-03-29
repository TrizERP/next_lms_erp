@include('includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Collect</h4>
            </div>
        </div>

        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            
            <form action="{{ route('studentresult.show') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <div class="row">
                              {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 form-group">
                        <label>Name</label>
                        <input type="text" id="stu_name" placeholder="Name" name="stu_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>UniqueID/Adm.No</label>
                        <input type="text" id="uniqueid" placeholder="UniqueID/Adm.No" name="uniqueid" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile</label>
                        <input type="text" id="mobile" placeholder="Mobile" name="mobile" class="form-control">
                    </div>                        
                    <div class="col-md-4 form-group">
                        <label>Gr No</label>
                        <input type="text" id="grno" placeholder="Gr No." name="grno" class="form-control">
                        @if(app('request')->input('implementation') == 1)
                        <input type="hidden" name="implementation" value="1">
                        @endif
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search Student" class="btn btn-success triz-btn" >
                            <!-- <input onclick="location.reload();" type="button" value="Flust Trial" class="btn btn-success triz-btn" > -->
                        </center>
                    </div>
                </div>
            </form>
            
        </div>

        <?php if (isset($data['stu_data'])) {?>
            <div class="card">
                <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Only those students will be displayed here whose Fees Structure is added.">
                  <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled>Note</button>
                </span>
                <form action="{{ route('studentresult.show') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="table-responsive">
                    <table class="table table-box table-bordered">
                        <tr>
                            <th>Sr No.</th>
                            <th>Name</th>
                            <th>GR.No.</th>
                            <th>Standard</th>
                            <th>Division</th>                            
                            <th>Student Quota</th>
                            <th>Mobile</th>
                            <th>UniqueID/Adm.No</th>
                            <th>Remaining Fees</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($data['stu_data'] as $id => $arr) {?>
                            <tr>
                                <td><?php echo $id + 1; ?></td>
                                <td><?php echo $arr->first_name . ' ' . $arr->middle_name . ' ' . $arr->last_name; ?></td>
                                <td><?php echo $arr->enrollment_no; ?></td>            
                                <td><?php echo $arr->standard_name; ?></td>
                                <td><?php echo $arr->division_name; ?></td>                                 
                                <td><?php echo $arr->stu_quota; ?></td>                                 
                                <td><?php echo $arr->mobile; ?></td>
                                <td><?php echo $arr->uniqueid ?></td>                                        
                                <td><?php echo $arr->bkoff; ?></td>
                                <td>
                                    <a href="{{ route('result.show',$arr->student_id)}}"><button style="float:left;" type="button" class="btn btn-info btn-outline ">Result Show</button></a>
                                </td>

                            </tr>
                        <?php }?>
                    </table>
                </div>
                </form>
            </div>
        <?php }?>
    </div>
</div>

@include('includes.footerJs')

@if (!isset($data['stu_data']))
@if(isset(Session::get('erpTour')['fees_collect']) && Session::get('erpTour')['fees_collect'] == 0)
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
            $('#grade').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#grade',
          event:'new_todo',
          event_type:'custom',
          description:'Select Grade Here.'
        },
        {
            onBeforeStart: function(){
            $('#standard').change(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#standard',
          event:'new_todo',
          event_type:'custom',
          description:'Select Standard Here.'
        },
        {
          selector:'#division',
          event:'change',
          description:'Select Division Here.',
          timeout:100
        },
        {
          selector:'.btn-success',
          event:'click',
          description:'Please press to search students.',
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
        var url = "http://dev.triz.co.in/tourUpdate?module=fees_collect";
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
@endif
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
//    $("#grade").val("6");
});

</script>
@if(app('request')->input('implementation') == 1)
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@endif
@include('includes.footer')
