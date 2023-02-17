@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style>
    .breadcrumb1-arrow {
        min-height: 36px;
        /*     padding: 0; */
        line-height: 36px;
        list-style: none;
        overflow: auto;
        /*    background-color: #e6e9ed*/
        /*background: linear-gradient(to right, #eaeaea 0%,#ffffff 100%);*/
    }

    .breadcrumb1-arrow li:first-child a {
        border-radius: 4px 0 0 4px;
        -webkit-border-radius: 4px 0 0 4px;
        -moz-border-radius: 4px 0 0 4px;
    }

    .breadcrumb1-arrow li,
    .breadcrumb1-arrow li a,
    .breadcrumb1-arrow li span {
        display: inline-block;
        /*vertical-align: top;*/
    }

    .breadcrumb1-arrow li:not(:first-child) {
        margin-left: -5px;
    }

    .breadcrumb1-arrow li+li:before {
        padding: 0;
        content: "";
    }

    .breadcrumb1-arrow li span {
        padding: 0 10px;
    }

    .breadcrumb1-arrow li a,
    .breadcrumb1-arrow li:not(:first-child) span {
        height: 36px;
        padding: 0 10px 0 25px;
        line-height: 36px;
    }

    .breadcrumb1-arrow li:first-child a {
        padding: 0 10px;
    }

    .breadcrumb1-arrow li a {
        position: relative;
        color: #fff;
        text-decoration: none;
        background-color: #343a40;
        border: 1px solid #343a40;
    }

    .breadcrumb1-arrow li:first-child a {
        padding-left: 10px;
    }

    .breadcrumb1-arrow li a:after,
    .breadcrumb1-arrow li a:before {
        position: absolute;
        top: -1px;
        width: 0;
        height: 0;
        content: '';
        border-top: 18px solid transparent;
        border-bottom: 18px solid transparent;
    }

    .breadcrumb1-arrow li a:before {
        right: -10px;
        z-index: 3;
        border-left-color: #343a40;
        border-left-style: solid;
        border-left-width: 10px;
    }

    .breadcrumb1-arrow li a:after {
        right: -11px;
        z-index: 2;
        border-left: 11px solid #fff;
    }

    .breadcrumb1-arrow li a:focus,
    .breadcrumb1-arrow li a:hover {
        background-color: #40474e;
        border: 1px solid #40474e;
    }

    .breadcrumb1-arrow li a:focus:before,
    .breadcrumb1-arrow li a:hover:before {
        border-left-color: #40474e;
    }

    .breadcrumb1-arrow li a:active {
        background-color: #40474e;
        border: 1px solid #40474e;
    }

    .breadcrumb1-arrow li a:active:after,
    .breadcrumb1-arrow li a:active:before {
        border-left-color: #40474e;
    }

    /*set for Last child*/

    .breadcrumb1-arrow li.active span {
        position: relative;
        color: #fff;
        text-decoration: none;
        background-color: #007bff;
        border: 1px solid #007bff;
    }

    .breadcrumb1-arrow li.active:first-child span {
        padding-left: 10px;
    }

    .breadcrumb1-arrow li.active span:after,
    .breadcrumb1-arrow li.active span:before {
        position: absolute;
        top: -1px;
        width: 0;
        height: 0;
        content: '';
        border-top: 18px solid transparent;
        border-bottom: 18px solid transparent;
    }

    .breadcrumb1-arrow li.active span:before {
        right: -10px;
        z-index: 3;
        border-left-color: #007bff;
        border-left-style: solid;
        border-left-width: 11px;
    }

    .breadcrumb1-arrow li.active span:after {
        right: -11px;
        z-index: 2;
        border-left: 10px solid #007bff;
    }
</style>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <ol class="breadcrumb1 breadcrumb1-arrow">
                        <!-- <li><a href="#"><i class="fa fa-fw fa-home"></i></a></li> -->
                        <li class=""><a href="#"><span>Report Detail</span></a></li>
                        <li class="active"><a href="#">Select Coloum</a></li>
                        <li class=""><a href="#">Filters</a></li>
                    </ol>
                </div>                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('dynamic_report.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <textarea name="old_data" style="display:none;"><?php echo serialize($data); ?></textarea>
                        <div class="form-group row">
                            <label class="col-form-label">All Conditions (All conditions must be met)</label>
                        </div>
                        <div class="row mustcon">
                            <div class="form-group row" id="con">
                                <div class="col-md-4">
                                    <select class="form-control" style="margin-bottom: 20px;" name="condition[must][field][]">
                                        <option value="">-Select Field-</option>
                                        <?php foreach ($data["all_fields"] as $id => $val) { ?>
                                            <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-control" style="margin-bottom: 20px;" name="condition[must][con][]">
                                        <option value="">-Select Condition-</option>
                                        <option value="equals">Equal TO</option>
                                        <option value="not_equals">Not Equal</option>
                                        <option value="less_then">Less Then</option>
                                        <option value="grater_then">Grater Then</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="condition[must][val][]" >
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <center>                                
                                <a href="#" class="btn btn-info" onclick="addAllCon('mustcon','con');">Add</a>
                                <a href="#" class="btn btn-danger" onclick="removeAllCon('mustcon');">Remove</a>
                            </center>
                        </div>
                        <div class="form-group row">
                            <label class="col-form-label">Any Conditions (At least one of the conditions must be met)</label>
                        </div>
                        <div class="row anycon">
                            <div class="form-group row" id="acon">
                                <div class="col-md-4">
                                    <select class="form-control" style="margin-bottom: 20px;" name="condition[any][field][]">
                                        <option value="">-Select Field-</option>
                                        <?php foreach ($data["all_fields"] as $id => $val) { ?>
                                            <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-control" style="margin-bottom: 20px;" name="condition[any][con][]">
                                        <option value="">-Select Condition-</option>
                                        <option value="equals">Equal TO</option>
                                        <option value="not_equals">Not Equal</option>
                                        <option value="less_then">Less Then</option>
                                        <option value="grater_then">Grater Then</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="condition[any][val][]">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <center>                            
                                <a href="#" class="btn btn-info" onclick="addAllCon('anycon','acon');">Add</a>
                                <a href="#" class="btn btn-danger" onclick="removeAllCon('anycon');">Remove</a>
                            </center>
                        </div>
                        <div class="form-group row">
                            <div class="col-sm-10">
                            <a href="{{ route('dynamic_report.index') }}" class="btn btn-info pull-right"> Cancle</a>
                                <button type="submit" class="btn btn-info pull-right" style="margin-right: 10px;">Finish</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#example').DataTable({

        });
        $('.select2').select2({
            closeOnSelect: false,
            allowClear: true
        });
    });

    function addAllCon(parentDivClass, childId) {
        var $el = $('#' + childId).clone();
        $('.' + parentDivClass).append($el);
    }

    function removeAllCon(parentDivClass) {
        if ($("." + parentDivClass + " > .row").length > 1) {
            $("." + parentDivClass + " > .row").slice(-1).remove();
        }
    }
</script>
@include('includes.footer')