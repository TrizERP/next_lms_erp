@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
    		<div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Admission Category</h4> </div>
            </div>       
            <div class="card">
                <div class="panel-body">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                       
                    <div class="col-lg-12 col-sm-12 col-xs-12">  
                        <form action="
                          @if (isset($data))
                          {{ route('add_admission_category_master.update', $data->id) }}
                          @else
                          {{ route('add_admission_category_master.store') }}
                          @endif

                          " method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif


                        {{csrf_field()}}
                        
                            <div class="row">
                                <div class="col-md-4 form-group ml-0 mr-0">
                                    @csrf
                                    <label>Title </label>
                                    <input type="text" id='title' required name="title" value="@if(isset($data->title)) {{ $data->title }} @endif" class="form-control">
                                </div>
                                <div class="col-md-4 form-group ml-0">
                                    <label>Description </label>
                                    <textarea class="form-control" rows="2" id='description' required name="description">@if(isset($data->description)) {{ $data->description }} @endif</textarea>  
                                </div>

                                <div class="col-md-12 form-group">
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
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
</div>

@include('includes.footerJs')
@include('includes.footer')
