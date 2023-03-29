@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

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
                    <form action="{{ route('send_sms_staff.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <?php
                        ?>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Select Staff</label>
                                <select name="staff" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php
                                    foreach ($data['data'] as $id => $arr) {
                                        echo "<option value='$arr[id]'>$arr[name]</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <!--<div class="col-md-12 form-group">
                                {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                            </div>-->
                            <div class="col-md-6 form-group mt-4">
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
@include('includes.footer')
