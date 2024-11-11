@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Books</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-md-12 mt-2">
                <div class="row">
                    <div class="col-lg-12 col-sm-3 col-xs-3 row">
                        <div class="col-md-3 pull-right">
                            <select id="cmbyear" class="form-control" name="cmbyear"
                                onchange="getyearwise_holiday(this.value);">
                                <option value="">Select Year</option>
                                <option value="2023">2023-2024</option>
                                <option value="2022">2022-2023</option>
                                <option value="2021">2021-2022</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a class="btn btn-danger delete-all"><i class="fa fa-trash"></i>
                                Delete </a>
                            <a class="btn btn-info print-barcode"><i class="fa fa-barcode"></i>
                                Print Barcode</a>
                        </div>
                    </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12 mt-5">
                        <div class="table-responsive">
                            <table id="tblBooks" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th data-toggle="tooltip" title="Select All"><input type="checkbox"
                                                name="" id="checkedAll"></th>
                                        <th data-toggle="tooltip" title="No">No</th>
                                        <th data-toggle="tooltip" title="Image">Image</th>
                                        <th data-toggle="tooltip" title="Title">Title</th>
                                        <th data-toggle="tooltip" title="Subject">Subject</th>
                                        <th data-toggle="tooltip" title="Sub Title">Sub Title</th>
                                        <th data-toggle="tooltip" title="Publisher Name">Publisher Name</th>
                                        <th data-toggle="tooltip" title="Publish Year">Publish Year</th>
                                        <th data-toggle="tooltip" title="Auther Name">Auther Name</th>
                                        <th data-toggle="tooltip" title="Action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tabs content -->
        </div>
    </div>
</div>

@include('includes.footerJs')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<script>
    $(document).ready(function() {});
</script>
@include('includes.footer')
