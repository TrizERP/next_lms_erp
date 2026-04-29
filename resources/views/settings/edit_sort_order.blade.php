@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Sort Order</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code']==0)
                    <div class="alert alert-danger alert-block">
                    @else
                    <div class="alert alert-success alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <h3 class="box-title h4 pb-2 border-bottom mb-3">Edit Sort Order</h3>

                    <div class="row">
                        <div class="col-md-6">
                            <form action="{{ route('add_fields.update', $data->id) }}" method="post">
                                @csrf
                                @method('PUT')
                                
                                <div class="form-group">
                                    <label>Module Name</label>
                                    <input type="text" class="form-control" value="{{$data->table_name}}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Field Name</label>
                                    <input type="text" class="form-control" value="{{$data->field_name}}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Field Label</label>
                                    <input type="text" class="form-control" value="{{$data->field_label}}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Field Type</label>
                                    <input type="text" class="form-control" value="{{$data->field_type}}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Field Message</label>
                                    <input type="text" class="form-control" value="{{$data->field_message}}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Sort Order <span class="text-danger">*</span></label>
                                    <input type="number" name="sort_order" required class="form-control" value="{{$data->sort_order}}">
                                </div>
                                
                                <div class="form-group">
                                    <input type="submit" name="submit" value="Update" class="btn btn-success">
                                    <a href="{{ route('add_fields.index') }}" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')