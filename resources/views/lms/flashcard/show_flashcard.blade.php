@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
.image_size img{
    height:100px !important;
    width:100px !important;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">                
                <h1 class="h4 mb-3">Create Flash Card</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>                                 
                        <li class="breadcrumb-item"><a href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id ?? '','subject_id'=>$data['breadcrum_data']->subject_id ?? '']) }}">{{$data['breadcrum_data']->subject_name ?? ''}}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? '']) }}">{{$data['breadcrum_data']->chapter_name ?? ''}}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? '']) }}">{{$data['breadcrum_data']->topic_name ?? ''}}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create Flash Card</li>
                    </ol>
                </nav>
            </div> 
            @php 
            if(isset($_REQUEST['preload_lms'])){
                $preload_lms = "preload_lms=preload_lms";
            }
            @endphp
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('lms_flashcard.create',['content_id' => $_REQUEST['content_id'],$preload_lms ?? '']) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Flash Card</a>
            </div>                      
        </div>
        
    <div class="row">
        <div class="white-box">    
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">				
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif           
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <div class="card">
                        <div class="card-body">
                            <table id="subject_list" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Sr. No.</th>                                        
                                        <th>Standard</th>
                                        <th>Subject</th>
                                        <th>Chapter</th>                                        
                                        <th>Title</th>                                        
                                        <th>Frontend</th>                                
                                        <th>Backend</th>                                
                                        <th>Status</th>                                                                
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['data']) > 0)
                                    @php $i = 1;
                                    @endphp
                                    @foreach($data['data'] as $key => $fcdata)
                               <tr>    
                                        <td>@php echo $i++;@endphp</td>                                        
                                        <td>{{$fcdata->standard_name}}</td> 
                                        <td>{{$fcdata->subject_name}}</td>   								
                                        <td>{{$fcdata->chapter_name}}</td>   								
                                        <td>{!!$fcdata->title!!}</td>                                                                                                      
                                        <td class="image_size">{!!$fcdata->front_text!!}</td>                                                                                                      
                                        <td class="image_size">{!!$fcdata->back_text!!}</td>
                                        <td>                                    
                                            @if($fcdata->status == 1)
                                            Show
                                            @else
                                            Hide
                                            @endif
                                        </td>     
                                        <td>
                                            <div class="d-flex align-items-center justify-content-end">
                                                <a class="btn btn-outline-success" href="{{ route('lms_flashcard.edit',[$fcdata->id])}}">
                                                    <i class="ti-pencil-alt"></i>
                                                </a>                                                                                                            
                                                <form class="d-inline" action="{{ route('lms_flashcard.destroy', $fcdata->id)}}" method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                </form>               
                                            </div>                                              
                                        </td>                               
                                    </tr>
                                    @endforeach
                                    @else
                                        <tr><td colspan="10"><center>No records</center></td></tr>
                                    @endif                           
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
</div>
    </div>
</div>

@include('includes.lmsfooterJs')  
<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js"> 
 MathJax.Hub.Config({ 
   extensions: ["mml2jax.js"], 
   jax: ["input/MathML", "output/HTML-CSS"] 
 }); 
</script>
 
<script>
$( document ).ready(function() {
    $("#standard").change(function(){
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
            }
        }
        });
    })
});
</script>     
@include('includes.footer')
