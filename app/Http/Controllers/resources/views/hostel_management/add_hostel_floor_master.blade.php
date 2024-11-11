@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">        
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
                          {{ route('add_hostel_floor_master.update', $data->id) }}
                          @else
                          {{ route('add_hostel_floor_master.store') }}
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
                                    <label class="control-label">Building</label>
                                    <select class="form-control" required name="building_id">
                                    @if(!empty($menu))  
                                    @foreach($menu as $key => $value)
                                        <option value="{{ $value['id'] }}" @if(isset($data->building_id)) {{ $data->building_id == $value['id'] ? 'selected' : '' }} @endif> {{ $value['building_name'] }} </option>
                                    @endforeach
                                    @endif
                                    </select>
                                </div>
                            
                                <div class="col-md-4 form-group ml-0">
                                    <label>Floor Name </label>
                                    <input type="text" id='floor_name' required name="floor_name" value="@if(isset($data->floor_name)) {{ $data->floor_name }} @endif" class="form-control">
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
