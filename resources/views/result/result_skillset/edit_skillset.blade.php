@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Skillset</h4>
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
                    <form action="{{ route('result_skillset.update',$data['result_skillset']->id) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    @csrf
                        <div class="row">                            
                            <div class="col-md-4 form-group">
                                <label>Main Title </label>
                                <input type="text" id='main_title' value="@if(isset($data['result_skillset']->main_title)){{ $data['result_skillset']->main_title }}@endif" name="main_title" class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='title' value="@if(isset($data['result_skillset']->title)){{ $data['result_skillset']->title }}@endif" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Group</label>
                                <select id="group" name="group" class="form-control" required>
                                @php
                                    $uniqueGroups = [];
                                @endphp

                                @foreach ($data['get_result_activity_groups'] as $group)
                                    @if (!in_array($group->group, $uniqueGroups))
                                        <option value="{{ $group->group }}" @if(isset($data['result_skillset']->group) && $data['result_skillset']->group == $group->group) selected @endif>
                                            Group {{ $group->group }} ({{ implode(', ', array_column(array_filter($data['get_result_activity_groups'], function($item) use ($group) {
                                                return $item->group == $group->group;
                                            }), 'title')) }})
                                        </option>
                                        @php
                                            $uniqueGroups[] = $group->group;
                                        @endphp
                                    @endif
                                @endforeach
                                </select>
                            </div>  
                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' value="@if(isset($data['result_skillset']->sort_order)){{ $data['result_skillset']->sort_order }}@endif" name="sort_order" class="form-control">
                            </div>              
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
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
