@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Update Fees Structure</h4>      
            </div>     
        </div>
        <div class="card">
        <div class="col-md-12 form-group">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                <th class="text-left">Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>    
                                <td>
                                <span class="badge badge-success mb-1">
                                    {{$data['grade']}}</span>
                                </td>
                                <td>
                                <span class="badge badge-info mb-1">
                                    {{$data['standard']}}</span>
                                </td>
                                <td> <span class="badge badge-dark mb-1">
                                    {{$data['month']}} </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>      
            <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Pink color indicates records for which fees is already taken , so you are not allowed to edit that fees structure">
            <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled="">Note</button>
            </span>
            <form action="{{ route('update_fees_breackoff.store') }}" enctype="multipart/form-data" method="post">
                @csrf
                <input type="hidden" value="insert" name="action">
                <div class="row">
                    <div class="col-md-12 form-group">
                        <div class="h4 mt-2 text-primary font-weight-bold">New Student</div>
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ App\Helpers\get_string('studentquota','request')}}</th>
                                        <?php foreach ($data['data']['title_arr'] as $id => $val) { ?>
                                            <th><?php echo $val; ?></th>
                                        <?php } ?>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <?php
                                foreach ($data['data']['quota_arr'] as $quota_id => $quota_val) {
                                    $total = 0;
//                                                    $amount_val = 0;
//                                                    $amount_val = "";
                                    //START If fees collected breakoff cant be edited
                                    if(isset($data['data']['paid_arr']['new'][$quota_id]))
                                    {
                                        $disable_new_edit = "disabled";
                                        $disable_new_tr = "background-color : #f09898 !important;";
                                    }
                                    else
                                    {
                                        $disable_new_edit = "";  
                                        $disable_new_tr = "";  
                                    }
                                    //END If fees collected breakoff cant be edited
                                    ?>
                                    <tr style="{{$disable_new_tr}}">
                                        <td>
                                            <?php echo $quota_val; ?>
                                        </td>
                                        <?php
                                        foreach ($data['data']['title_arr'] as $id => $val) {
                                            $amount_val = 0;
                                            if (isset($data['data']['bk_arr']['new'][$quota_id][$id])) {
                                                $amount_val = $data['data']['bk_arr']['new'][$quota_id][$id];
                                                $total += $amount_val;
                                            }
                                            ?>
                                            <td>
                                                <input {{$disable_new_edit}} type="text" class="form-control" value="<?php echo $amount_val; ?>" name="<?php echo 'NewValues[' . $quota_id . '][' . $id . ']'; ?>">
                                            </td>
                                        <?php } ?>
                                        <td class="total">
                                            <input {{$disable_new_edit}} type="text" class="form-control w-auto" value="<?php echo $total; ?>" name="total">
                                        </td>
                                    <?php } ?>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="col-md-12 form-group">
                        <div class="h4 mt-2 text-primary font-weight-bold">Old Student</div>
                        <div class="table-responsive">
                            <table id="example" class="table table-striped" style="margin-top:10px; ">
                                <thead>
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
                                </thead>
                                <?php
                                foreach ($data['data']['quota_arr'] as $quota_id => $quota_val) {
                                    $total = 0;
//                                                    $amount_val = 0;
                                    //START If fees collected breakoff cant be edited
                                    if(isset($data['data']['paid_arr']['old'][$quota_id]))
                                    {
                                        $disable_old_edit = "disabled";
                                        $disable_old_tr = "background-color : #f09898 !important;";
                                    }
                                    else
                                    {
                                        $disable_old_edit = "";  
                                        $disable_old_tr = "";  
                                    }
                                    //END If fees collected breakoff cant be edited
                                    ?>
                                    <tr style="{{$disable_old_tr}}">
                                        <td>
                                            <?php echo $quota_val; ?>
                                        </td>
                                        <?php
                                        foreach ($data['data']['title_arr'] as $id => $val) {
                                            $amount_val = 0;
                                            if (isset($data['data']['bk_arr']['old'][$quota_id][$id])) {
                                                $amount_val = $data['data']['bk_arr']['old'][$quota_id][$id];
                                                $total += $amount_val;
                                            }
                                            ?>
                                            <td>
                                                <input {{$disable_old_edit}} type="text" class="form-control" value="<?php echo $amount_val; ?>" name="<?php echo 'OldValues[' . $quota_id . '][' . $id . ']'; ?>">
                                            </td>
                                        <?php } ?>
                                        <td class="total">
                                            <input {{$disable_old_edit}} type="text" class="form-control w-auto" name="total" value="<?php echo $total; ?>">
                                        </td>
                                    <?php } ?>
                                </tr>
                                       
                            </table>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">                        
                        <center>
                            <button type="submit" class="btn btn-info">Submit</button>
                        </center>
                    </div>
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
