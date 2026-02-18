@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Update Fees Structure</h4>            
            </div>                    
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif            
            <form action="{{ route('update_fees_breackoff.store') }}" enctype="multipart/form-data" method="post">
            {{ method_field("POST") }}
            @csrf
                <div class="row">                    
                    {{ App\Helpers\SearchChain('4','single','grade,std') }}
                                                 
                    <div class="col-md-4 form-group">
                        <label>Month</label>
    					{{-- <div class="custom-select"> --}}
                        <select name="month_id" class="form-control" required>
                            <option value="">--Select--</option>
                            <?php
                            foreach ($data['data']['ddMonth'] as $id => $val) {
                                ?>
                                <option value="<?php echo $id ; ?>"><?php echo $val; ?></option>
                                <?php
                            }
                            ?>
                        </select>
    					{{-- </div> --}}
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search Fees Structure" class="btn btn-success" >
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
    $('#example').DataTable({

    });
});

</script>
<script>
    $("#division").parent('.form-group').hide();
    $("#grade").attr('required', true);
    $("#standard").attr('required', true);
</script>
@include('includes.footer')
