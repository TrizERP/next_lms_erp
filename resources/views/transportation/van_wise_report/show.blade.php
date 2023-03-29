@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    h4 
    {    
        font-size: 13px !important;
        font-weight: 600 !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Van wise Report</h4>            
            </div>                    
        </div>
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('van_wise_report.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <h4>Shift :</h4>
                                <select name="shift" id="shift" class="form-control shift">
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddShift'] as $id => $arr) {
                                        echo "<option value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <h4>Van :</h4>
                                <select name="van" id="van" class="form-control van">
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddVan'] as $id => $arr) {
                                        echo "<option value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <h4>GR No :</h4>
                                <input type="text" name="grno" class="form-control" value="">

                            </div>
                            <div class="col-md-4 form-group">
                                <h4>Route :</h4>
                                <select name="route" id="van" class="form-control van">
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddRoute'] as $id => $arr) {
                                        echo "<option value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <h4>Stop :</h4>
                                <select name="stop" id="stop" class="form-control shift">
                                    <option value="">--Select--</option>
                                    <?php
                                    foreach ($data['data']['ddStop'] as $id => $arr) {
                                        echo "<option value='$id'>$arr</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <h4>Data Of :</h4>
                                <select name="pickup" required class="form-control shift">
                                    <option value="">--Select--</option>
                                    <option value="pick">Pick Up</option>
                                    <option value="drop">Drop</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
});
</script>
@include('includes.footer')
