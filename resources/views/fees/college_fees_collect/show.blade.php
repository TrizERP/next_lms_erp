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

        <div class="white-box">
            <div class="panel-body p-0">
                <form action="{{ route('college_fees_collect.show_student') }}" enctype="multipart/form-data"
                    method="post">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="row">
                        <div class="form-group">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ App\Helpers\get_string('studentname','request')}}</label>
                            <input type="text" id="stu_name" placeholder="Name" name="stu_name" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Mobile</label>
                            <input type="text" id="mobile" placeholder="Mobile" name="mobile" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ App\Helpers\get_string('grno','request')}}</label>
                            <input type="text" id="grno" placeholder="Gr No." name="grno" class="form-control">
                            @if(app('request')->input('implementation') == 1)
                            <input type="hidden" name="implementation" value="1">
                            @endif
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search BreackOff"
                                    class="btn btn-success triz-btn">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($data['stu_data'])) {?>
        <div class="row">
            <div class="white-box">
                <div class="panel-body">
                    <form action="{{ route('college_fees_collect.show_student') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf
                        <table class="table table-box table-bordered table-responsive">
                            <tr>
                                <th>
                                {{ App\Helpers\get_string('grno','request')}}
                                </th>
                                <th>
                                {{ App\Helpers\get_string('studentname','request')}}
                                </th>
                                <th>
                                {{ App\Helpers\get_string('standard','request')}}
                                </th>
                                <th>
                                {{ App\Helpers\get_string('division','request')}}
                                </th>
                                <th>
                                    Mobile
                                </th>
                                <th>
                                    Action
                                </th>
                            </tr>

                            <?php foreach ($data['stu_data'] as $id => $arr) {?>
                            <tr>
                                <td>
                                    <?php echo $id + 1; ?>
                                </td>
                                <td>
                                    <?php echo $arr->first_name . ' ' . $arr->middle_name . ' ' . $arr->last_name; ?>
                                </td>
                                <td>
                                    <?php echo $arr->standard_name; ?>
                                </td>
                                <td>
                                    <?php echo $arr->standard_name; ?>
                                </td>
                                <td>
                                    <?php echo $arr->mobile; ?>
                                </td>
                                <td>
                                    <a href="{{ route('college_fees_collect.edit',$arr->student_id)}}"><button
                                            style="float:left;" type="button" class="btn btn-info btn-outline ">Collect
                                            Fees</button></a>
                                </td>

                            </tr>
                            <?php }?>
                        </table>
                    </form>
                </div>
            </div>
        </div>
        <?php }?>
    </div>
</div>

@include('includes.footerJs')

@if (!isset($data['stu_data']))
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