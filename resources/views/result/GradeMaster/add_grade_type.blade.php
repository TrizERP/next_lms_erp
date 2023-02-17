@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

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
                    <form action="
                          @if (isset($data['Id']))
                          {{ route('grade_master.update', $data->Id) }}
                          @else
                          {{ route('grade_master.store') }}
                          @endif

                          " enctype="multipart/form-data" method="post">

                        @if(!isset($data['Id']))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif


                        {{csrf_field()}}
                        <div class="row">                            
                            <div class="col-md-6 form-group">
                                <label>Grade Name</label>
                                <input type="text" required name="grade_name" value="@if(isset($data->grade_name)) {{ $data->grade_name }} @endif" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Sort Order</label>
                            <input type="text" required name="sort_order" value="@if(isset($data->sort_order)) {{$data->sort_order}} @endif" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
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
