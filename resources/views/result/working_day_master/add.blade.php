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
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('working_day_master.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">
                            
                            {{ App\Helpers\SearchChain('4','multiple','grade,std') }}                        
                            
                            {{ App\Helpers\TermDD() }}
                            
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Total Working Days: </label>
                                <input type="text" name="total_working_day" value="" class="form-control" />
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
    CKEDITOR.replace('line1');
    CKEDITOR.replace('line2');
    CKEDITOR.replace('line3');
    CKEDITOR.replace('line4');
</script>
@include('includes.footer')
