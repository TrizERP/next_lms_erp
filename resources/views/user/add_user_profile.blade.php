@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">User Profile</h4> </div>
            </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data))
                          {{ route('add_user_profile.update', $data['id']) }}
                          @else
                          {{ route('add_user_profile.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                          @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                            @csrf
                        <div class="col-md-6 form-group">
                            <label>User Profile Name </label>
                            <input type="text" value="@if(isset($data['name'])){{ $data['name'] }}@endif" id='profile_name' required name="profile_name" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>User Profile Description </label>
                            <input type="text" id='profile_description' value="@if(isset($data['description'])){{ $data['description'] }}@endif" required name="profile_description" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Parent Profile</label>
                            
                            <select name="parent_id" id="parent_id" class="form-control">
                                <option value="0"> Select Parent Profile </option>
                                
                                @if(!empty($menu))  
                                @foreach($menu as $key => $value)
                               
                                    <option value="{{ $value['id'] }}" @if(isset($data)) @if($value['id'] == $data['parent_id']) selected @endif  @endif  > {{ $value['name'] }} </option>
                                @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>User Sort Order</label>
                            <input type="number" value="@if(isset($data['sort_order'])){{$data['sort_order']}}@endif" id='user_sort_order' required name="sort_order" class="form-control">
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>

                </div>
            </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
