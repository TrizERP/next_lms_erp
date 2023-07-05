@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Fees Breakoff</h4>            
            </div>                    
        </div>
        <div class="card">
            <div class="row">                
                <div class="col-lg-3 col-sm-3 col-xs-3 mb-3">
                    <a href="{{ route('fees_breackoff.create') }}" class="btn btn-info add-new"><i class="fa fa-plus mr-1"></i> Reset</a>
                </div>
                <div class="col-md-12 form-group">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                <th>Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>    
                                <td>
                                    <!-- <ul class="list-icons"> -->
                                        <?php foreach ($data['data']['grade_arr'] as $id => $val) { ?>
                                            <span class="badge badge-success mb-1">
                                                <?php echo $val; ?>
                                            </span>
                                        <?php } ?>
                                    <!-- </ul> -->
                                </td>
                                <td>
                                    <!-- <ul class="list-icons"> -->
                                        <?php foreach ($data['data']['std_arr'] as $id => $val) { ?>
                                            <span class="badge badge-info mb-1">
                                                <?php echo $val; ?>
                                            </span>
                                        <?php } ?>
                                    <!-- </ul> -->
                                </td>
                                <td>
                                    <!-- <ul class="list-icons"> -->
                                        <?php foreach ($data['data']['month_arr'] as $id => $val) { ?>
                                            <span class="badge badge-dark mb-1">
                                                <?php echo $val; ?>
                                            </span>
                                        <?php } ?>
                                    <!-- </ul> -->
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <form action="{{ route('fees_breackoff.store') }}" enctype="multipart/form-data" method="post">
            @csrf
                <input type="hidden" value="insert" name="action">
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <!-- <table id="example" class="table table-striped">
                                    <tr>
                                        <td>New Student</td>
                                    </tr>
                                    <tr>
                                        <td class="px-0">
                                        </td>
                                    </tr>
                                </table> -->
                                <div class="h4">New Student</div>
                                <table id="example" class="table table-bordered mb-4">
                                    <tr>
                                        <th>
                                        {{ App\Helpers\get_string('studentquota','request')}}
                                        </th>
                                        <?php foreach ($data['data']['title_arr'] as $id => $val) { ?>
                                            <th>
                                                <?php echo $val; ?>
                                            </th>
                                        <?php } ?>
                                        <th>
                                            Total
                                        </th>
                                    </tr>
                                    <?php foreach ($data['data']['quota_arr'] as $quota_id => $quota_val) { ?>
                                        <tr>
                                            <td>
                                                <?php echo $quota_val; ?>
                                            </td>
                                            <?php foreach ($data['data']['title_arr'] as $id => $val) { ?>
                                                <td>
                                                    <input style="width: 60pt;" type="text" class="form-control" name="<?php echo 'NewValues[' . $quota_id . '][' . $id . ']'; ?>">
                                                </td>
                                            <?php } ?>
                                            <td class="total">
                                                <input style="width: 60pt;" readonly="readonly" type="text" class="form-control" name="total">
                                            </td>
                                        <?php } ?>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <!-- <table id="example" class="table table-striped">
                                    <tr>
                                        <td>Old Student</td>
                                    </tr>
                                    <tr>
                                        <td>
                                        </td>
                                    </tr>
                                </table> -->
                                <div class="h4">Old Student</div>
                                <table id="example" class="table table-bordered mb-4">
                                    <tr>
                                        <th>
                                        {{ App\Helpers\get_string('quota','request')}}
                                        </th>
                                        <?php foreach ($data['data']['title_arr'] as $id => $val) { ?>
                                            <th>
                                                <?php echo $val; ?>
                                            </th>
                                        <?php } ?>
                                        <th>
                                            Total
                                        </th>
                                    </tr>
                                    <?php foreach ($data['data']['quota_arr'] as $quota_id => $quota_val) { ?>
                                        <tr>
                                            <td>
                                                <?php echo $quota_val; ?>
                                            </td>
                                            <?php foreach ($data['data']['title_arr'] as $id => $val) { ?>
                                                <td>
                                                    <input style="width: 60pt;" type="text" class="form-control" name="<?php echo 'OldValues[' . $quota_id . '][' . $id . ']'; ?>">
                                                </td>
                                            <?php } ?>
                                            <td class="total">
                                                <input style="width: 60pt;" readonly="readonly" type="text" class="form-control" name="total">
                                            </td>
                                        <?php } ?>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">                        
                        <center>
                            <button type="submit" class="btn btn-info">Submit</button>
                        </center>
                    </div>
                </form>
        </div>
    </div>
</div>


@include('includes.footerJs')
<script>
    $(document).ready(function () {
        $("input").each(function () {
            var that = this; // fix a reference to the <input> element selected
            $(this).keyup(function () {
//                alert("asdsa");
                newSum.call(that); // pass in a context for newsum():
                // call() redefines what "this" means
                // so newSum() sees 'this' as the <input> element
            });
        });
    });
    function newSum() {
        var sum = 0;
        var thisRow = $(this).closest('tr');

        thisRow.find('td:not(.total) input:text').each(function () {
            if (this.value != '') {
                sum += parseFloat(this.value); // or parseInt(this.value,10) if appropriate
            }
        });

        thisRow.find('td.total input:text').val(sum); // It is an <input>, right?
    }
</script>
@include('includes.footer')
