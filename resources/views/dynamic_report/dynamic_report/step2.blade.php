@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style>
    .breadcrumb1-arrow {
        min-height: 36px;
        /*padding: 0; */
        line-height: 36px;
        list-style: none;
        overflow: auto;
        /*background-color: #e6e9ed*/
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
<!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" /> -->
<!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.css" rel="stylesheet" /> -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/3.5.3/select2.min.css" rel="stylesheet" />
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
                    <form action="{{ route('dynamic_report_step3') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <textarea name="old_data" style="display:none;"><?php echo serialize($data); ?></textarea>
                        <div class="form-group row">
                            <label class="col-form-label">Select Fields</label>
                            <input type="hidden" name="selected_fields" >
                            <!-- <select class="form-control select2 select2-hidden-accessible" id="fieldSelect" name="selected_fields[]" multiple="" data-placeholder="Select a State" style="width: 100%;" tabindex="-1" aria-hidden="true"> -->
                            <select class="form-control select2-container" autofocus id="fieldSelect"  multiple=""  data-placeholder="Select a Field" style="width: 100%;">
                                <!-- <option value="">-Select-</option> -->
                                <?php foreach ($data["all_fields"] as $id => $val) { ?>
                                    <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group row">
                            <div class="col-sm-6">
                                <label class="col-form-label" style="padding-bottom: 10px;">Group By</label>
                                <select class="form-control" style="margin-bottom: 20px;" id="g1" name="group_by1">
                                    <option value="">-Select-</option>
                                    <?php foreach ($data["all_fields"] as $id => $val) { ?>
                                        <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                    <?php } ?>
                                </select>
                                <select class="form-control" style="margin-bottom: 20px;" id="g2" name="group_by2">
                                    <option value="">-Select-</option>
                                    <?php foreach ($data["all_fields"] as $id => $val) { ?>
                                        <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                    <?php } ?>
                                </select>
                                <select class="form-control" style="margin-bottom: 20px;" id="g3" name="group_by3">
                                    <option value="">-Select-</option>
                                    <?php foreach ($data["all_fields"] as $id => $val) { ?>
                                        <option value="<?php echo $id; ?>"><?php echo $val; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="col-form-label" style="padding-bottom: 10px;">Sort Order</label>
                                <div class="row" style="margin-bottom: 48px;">
                                    <label class="radio-inline">
                                        <input type="radio" name="sort_order1" value="asc">Ascending
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" name="sort_order1" value="desc">Descending
                                    </label>
                                </div>
                                <div class="row" style="margin-bottom: 48px;">
                                    <label class="radio-inline">
                                        <input type="radio" name="sort_order2" value="asc">Ascending
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" name="sort_order2" value="desc">Descending
                                    </label>
                                </div>
                                <div class="row" style="margin-bottom: 48px;">
                                    <label class="radio-inline">
                                        <input type="radio" name="sort_order3" value="asc">Ascending
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" name="sort_order3" value="desc">Descending
                                    </label>
                                </div>

                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-sm-10">
                                <a href="{{ route('dynamic_report.index') }}" class="btn btn-info pull-right"> Cancle</a>
                                <button type="submit" class="btn btn-info pull-right" style="margin-right: 10px;">Next</button>
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
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script> -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.js"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/3.5.3/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sortable/0.9.7/jquery-sortable-min.js"></script>
<script>
    $(document).ready(function() {
        $('#example').DataTable({

        });
        // $('.select2').select2({
        // $("#fieldSelect").select2();
        $('#fieldSelect').select2({
            // closeOnSelect: false,
            maximumSelectionSize: 25,
            // closeOnSelect: false,
            // allowClear: true
        });
        $("#fieldSelect").select2("container").find("ul.select2-choices").sortable({
            containment: 'parent',
            start: function() {
                $("#fieldSelect").select2("onSortStart");
            },
            update: function() {
                $("#fieldSelect").select2("onSortEnd");
            },

        });
        $("#fieldSelect").on('change', function() {
            var data = $(this).select2('data');
            var array = [];
            $.each(data, function(index, val) {
                array[index] = val.id;
            });
            array.join(',');
            $("input[name=selected_fields").val(array);
        });

        // $("#fieldSelect").on('select2:open', function() {
        //     alert("asda");
        //     $(document.activeElement).blur()
        // });
        // $('.select2-selection', $('#id').next()).focus();
        // $("#fieldSelect").on('select2:open', function () {
        //     $('.select2-selection', $('#id').next()).focus();
        //     $('#fieldSelect option:first-child').focus();
        // });
        // $('#fieldSelect').val($('#fieldSelect option:first-child').val()).trigger('change');
    });

    // $("#fieldSelect").on("select2:select", function(evt) {
    // console.log(evt);
    // var element = evt.params.data.element;
    // var id = evt.params.data._resultId;
    // $("#"+id).css({"display": "none"});
    // console.log(id);
    // var $element = $(element);
    // $(this).trigger("change");
    // $element.detach();
    // $element.hide();
    // $(this).append($element);
    // var t = $("#fieldSelect").val().substr($("#fieldSelect").val().length - 1);

    // });

    // function loadSubModule(selectedVal) {
    //     alert(selectedVal);
    // }
    if ($("#main_module").length != 0) {
        $('#main_module').change(function() {
            var main_moduleID = $(this).val();
            if (main_moduleID) {
                $.ajax({
                    type: "GET",
                    url: "/api/get-sub_module-list?main_module_id=" + main_moduleID,
                    success: function(res) {
                        if (res) {
                            $("#sub_module").empty();
                            $("#sub_module").append('<option value="">Select</option>');
                            $.each(res, function(key, value) {
                                $("#sub_module").append('<option value="' + key + '">' + value + '</option>');
                            });


                        } else {
                            $("#sub_module").empty();
                        }
                    }
                });
            } else {
                $("#sub_module").empty();
            }
        });
    }
</script>
@include('includes.footer')