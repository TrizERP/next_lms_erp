@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Standard Division Mapping</h4>
            </div>
        </div>
        <div class="card">
            <div class="panel-body">
                @if ($message = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('std_div_map.store') }}" enctype="multipart/form-data" method="post">
                    @csrf
                            <!-- <div class="col-md-12 form-group">                                                                             -->
                            <div class="row">
                            <div class="col-md-4 form-group h3">
                                <label>Standard</label>
                            </div>
                            <div class="col-md-6 form-group h3">
                                <label>Division</label>
                            </div>
                                @foreach($data['data']['std_data'] as $std_val)
                                <div class="col-md-4 form-group">
                                    <input type="text" class="form-control" readonly id='standard_id[]' name="standard_id[]" value="{{ $std_val->name }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    @foreach($data['data']['div_data'] as $div_val)
                                      @php
                                        $checked = '';
                                        if(isset ($data['data']['std_div_map_data'][$std_val->id])){
                                            if( in_array( $div_val->id,$data['data']['std_div_map_data'][$std_val->id] ) )
                                            {
                                                $checked = 'checked';
                                            }
                                        }
                                      @endphp
                                        <input {{ $checked }} type="checkbox" id='division_id[{{ $std_val->id }}][]' name="division_id[{{ $std_val->id }}][]" value="{{ $div_val->id }}">
                                        {{ $div_val->name }}
                                    @endforeach
                                </div>
                                <div class="clearfix"></div>
                                @endforeach
                                <div class="col-md-12 form-group">
                                    <input type="hidden" name="sub_institute_id" value="{{ Session::get('sub_institute_id') }}">

                                    @if(isset($data['isImplementation']))
                                        <input type="hidden" name="isImplementation" value="{{ $data['isImplementation'] }}">
                                    @endif
                                    <center>
                                        <input class="btn btn-info" type="submit" name="submit" value="Save">
                                    </center>
                                </div>
                                </div>
                            <!-- </div> -->
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
