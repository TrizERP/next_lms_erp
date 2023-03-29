@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('result_master.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\TermDD() }}
                            {{ App\Helpers\SearchChain('4','multiple','grade,std') }}
                            <div class="col-md-4 form-group">
                                <label>Result Date</label>
                                <input type="text" id='result_date' required name="result_date" value="" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>School Re-Open Date</label>
                                <input type="text" id='reopen_date'  name="reopen_date" value="" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Vaction Start Date</label>
                                <input type="text" id='vaction_start_date' required name="vaction_start_date" value="" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Vaction End Date</label>
                                <input type="text" id='vaction_end_date' required name="vaction_end_date" value="" class="form-control mydatepicker" autocomplete="off">
                            </div>
                             <div class="col-md-4 form-group">
                                <label>Remove Fail Percentage : </label>
                                <input type="radio" name="remove_fail_per" value="y" checked> Yes
                                <input type="radio" name="remove_fail_per" value="n"> No
                            </div>
                             <div class="col-md-4 form-group">
                                <label>Display Option Subject In Mark Sheet : </label>
                                <input type="radio" name="optional_subject_display" value="y" checked> Yes
                                <input type="radio" name="optional_subject_display" value="n"> No
                            </div>
                            <div class="col-md-12 form-group">
                                <label>Result Remark : </label>
                                <input type="radio" name="result_remark" value="grade_master" checked> From Grade Master
                                <input type="radio" name="result_remark" value="individual"> Individual Student Wise
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Class Teacher Signature</label>
                                <input type="file" name="teacher_sign" id="teacher_sign"  accept="image/*" >
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Principle Sign</label>
                                <input type="file" name="principal_sign" id="principal_sign"  accept="image/*">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Director Signature</label>
                                <input type="file" name="director_signatiure" id="director_signatiure"  accept="image/*">
                            </div>
                            
                           
                           
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
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
@include('includes.footer')
