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
                    <form action="{{ route('result_remark_master.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">                            
                            {{ App\Helpers\TermDD() }}                            
                            <div class="col-md-4 form-group">
                                <label>Title</label>
                                <input type="text" id='title' required name="title" value="" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order</label>
                                <input type="text" id='sort_order' required name="sort_order" value="" class="form-control">
                            </div>                        
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Remark Status : </label>
                                <input type="radio" name="result_status" value="Y" checked> On
                                <input type="radio" name="result_status" value="N"> Off
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
