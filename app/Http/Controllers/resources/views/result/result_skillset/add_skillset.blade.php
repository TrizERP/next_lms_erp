@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Skillset</h4>
            </div>
        </div>
        <div class="card">
		    <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($sessionData = Session::get('data'))
            @if($sessionData['status_code'] == 1)
            <div class="alert alert-success alert-block">
            @else
            <div class="alert alert-danger alert-block">
            @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('result_skillset.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}                        
                    @csrf
                        <div class="row"> 
                        <div class="col-md-4 form-group">
                            <label for="standard_list">Standard</label>
                            <select name="standard" id="standard" class="form-control" required>
                                <option value="">Select Standard</option>
                                @foreach($data['standardLists'] as $key=>$value)
                                <option value="{{$value->id}}">{{$value->name}}</option>
                                @endforeach
                            </select>
                        </div>               
                            <div class="col-md-4 form-group">
                                <label>Main Title </label>
                                <input type="text" id='main_title' name="main_title" class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='title' name="title" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Group</label>
                                <select id="group" name="group" class="form-control" required>
                                @foreach ($data['get_result_activity_groups'] as $item)
                                    @if ($loop->first || $item->group !== ($data['get_result_activity_groups'][$loop->index - 1]->group ?? null))
                                        @php
                                            $titles = collect($data['get_result_activity_groups'])->where('group', $item->group)->pluck('title')->implode(', ');
                                        @endphp
                                        <option value="{{ $item->group }}">
                                            Group {{ $item->group }} ({{ $titles }})
                                        </option>
                                    @endif
                                @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' name="sort_order" class="form-control">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
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
