@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Exam</h4>
            </div>            
        </div>      
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('exam_creation.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">
                            
                            {{ App\Helpers\TermDD() }}
                            

                            <div class="col-md-4 form-group">
                                <label>Medium : </label>
                                <select name="medium" class="form-control">
                                    <option value="">Select</option>
                                    <option value="CBSE">CBSE</option>
                                    <option value="GSEB">GSEB</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Exam : </label>
                                <select name="exam_id" class="form-control">
                                    <option value="">Select</option>
                                    @foreach ($data as $key => $value)
                                    <option value="{{ $key }}"
                                            >{{ $value }}</option>
                                    @endforeach

                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                {{ App\Helpers\SearchChainSubject('4','multiple','grade,std,sub') }}
                            </div>

                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Convert Marks: </label>
                                <input type="text" name="con_point" class="form-control">
                            </div>

                            <div class="col-md-4 form-group ml-0">
                                <label>App Status : </label>
                                <input type="radio" name="app_disp_status" value="Y" checked> Yes
                                <input type="radio" name="app_disp_status" value="N"> No
                            </div>


                            <div class="col-md-12 form-group">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Marks</th>
                                            <th>Marks/Grade</th>
                                            <th>Report Card Status</th>
                                            <th>Sort Order</th>
                                            <th>Exam Date</th>
                                            <!--<td>Action</td>-->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text" name="title" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="points" class="form-control" />
                                            </td>
                                            <td>
                                                <select name="marks_type" class="form-control">
                                                    <option value="">Select</option>
                                                    <option value="MARKS">MARKS</option>
                                                    <option value="GRADE">GRADE</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="report_card_status" class="form-control">
                                                    <option value="">Select</option>
                                                    <option value="Y">Yes</option>
                                                    <option value="N">No</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="sort_order" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="exam_date" class="form-control mydatepicker" autocomplete="off" />
                                            </td>
    <!--                                        <td class="col-sm-2"><a class="deleteRow"></a>
                                                <input type="button" class="addRow btn btn-md btn-success "  value="+">
                                            </td>-->
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
    <!--                                        <td colspan="5" style="text-align: left;">
                                                <input type="button" class="addRow btn btn-lg btn-block " id="addrow" value="Add Row" />
                                            </td>-->
                                        </tr>
                                        <tr>
                                        </tr>
                                    </tfoot>
                                </table>
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
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>       
    </div>
</div>


@include('includes.footerJs')
<script>
    $(document).ready(function () {
        var counter = 0;

        $(".addrow").on("click", function () {
            var newRow = $("<tr>");
            var cols = "";

            cols += '<td><input type="text" name="title[]" class="form-control" /></td>';
            cols += '<td><input type="text" name="points[]" class="form-control" /></td>';
            cols += '<td> <select name="marks_type[]" class="form-control"><option value="">Select</option><option value="CBSE">MARKS</option><option value="GSEB">GRADE</option></select></td>';
            cols += '<td><select name="report_card_status[]" class="form-control"><option value="">Select</option><option value="Y">Yes</option><option value="N">No</option></select></td>';

            cols += '<td><input type="text" name="sort_order[]" class="form-control" /></td>';
            cols += '<td><input type="text" name="exam_date[]" class="form-control" /></td>';

            cols += '<td><input type="button" class="ibtnDel btn btn-md btn-danger "  value="-"></td>';
            newRow.append(cols);
            $("table.order-list").append(newRow);
            counter++;
        });



        $("table.order-list").on("click", ".ibtnDel", function (event) {
            $(this).closest("tr").remove();
            counter -= 1
        });


    });

</script>
@include('includes.footer')
