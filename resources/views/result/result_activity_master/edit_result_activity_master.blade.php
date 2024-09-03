@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
   <div class="container-fluid">
      <div class="row bg-title">
         <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title">Edit Result Activity Master</h4>
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
                  <form action="{{ route('result_activity_master.update',$data['result_activity_masters']->id) }}" enctype="multipart/form-data" method="post">
                     {{ method_field("PUT") }}
                     @csrf
                     <div class="row">
                        <div class="col-md-4 form-group">
                           <label>Title </label>
                           <input type="text" id='title' value="@if(isset($data['result_activity_masters']->title)){{ $data['result_activity_masters']->title }}@endif" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                           <label>Skill Name</label>
                           <select id="skill_id" name="skill_id" class="form-control" required>
                           @php
                           $uniqueTitles = [];
                           @endphp
                           @foreach ($data['result_skillsets'] as $item)
                           @php
                           $titleCombo = $item->main_title . '(' . $item->title . ')';
                           @endphp
                           @if (!in_array($titleCombo, $uniqueTitles))
                           <option value="{{ $item->id }}" @if(isset($data['result_activity_masters']->skill_id) && $data['result_activity_masters']->skill_id == $item->id) selected @endif>
                           {{ $titleCombo }}
                           </option>
                           @php
                           $uniqueTitles[] = $titleCombo;
                           @endphp
                           @endif
                           @endforeach
                           </select>
                        </div>
                        <div class="col-md-4 form-group">
                           <label>Sort Order </label>
                           <input type="text" id='sort_order' value="@if(isset($data['result_activity_masters']->sort_order)){{ $data['result_activity_masters']->sort_order }}@endif" name="sort_order" class="form-control">
                        </div>
                     </div>
                     @if(!empty($data['result_sub_activity_masters']))
                     <input type="hidden" name="hasSubActivity" value="1">
                     <h6><b>Sub Activities: </b></h6>
                     @foreach($data['result_sub_activity_masters'] as $k=>$v)
                     <div class="row">
                     <input type="hidden" name="subData[id][]" value="@if(isset($v->id)){{ $v->id }}@endif">
                        <div class="col-md-4 form-group">
                           <label>Sub Title </label>
                           <input type="text" id='sub_title' value="@if(isset($v->title)){{ $v->title }}@endif" name="subData[title][]" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                           <label>Sort Order </label>
                           <input type="text" id='sub_sort_order' value="@if(isset($v->sort_order)){{ $v->sort_order }}@endif" name="subData[sort_order][]" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                        <label></label>
                            <a type="submit" onclick="return confirmDelete();" href="{{ route('result_sub_activity_destroy')}}?sub_id={{$v->id}}" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></a>
                        </div>
                    </div>
               @endforeach
               @endif
               <div class="col-md-12 form-group">
               <center>                                    
               <input type="submit" name="submit" value="Update" class="btn btn-success" >
               </center>
               </div>
               </form>
            </div>
         </div>
      </div>
   </div>
</div>
@include('includes.footerJs')
@include('includes.footer')