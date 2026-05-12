{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<style>
    .flashTitle, .flashFrontEnd, .flashBackEnd {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    padding: 10px;
    background-color: #fff;
    border-radius: 5px;
    margin-bottom:32px;
}
.btnSearch{
    color:white !important; 
    padding: 4px 4px !important; 
    margin : 8px 4px !important; 
}
.btnactive{
    color:white !important;
    margin-top: 0px !important;
    box-shadow: #ebe1e1 3px 5px !important;
}
.btn:hover{
    color:white;
    margin-top: 0px !important;
}
.btn-0,.btn-5,.btn-10{
    background: #26dad2;
}
.btn-1,.btn-6,.btn-11{
    background:#87c2fe;
}
.btn-2,.btn-7,.btn-12{
    background: #ce9fff;
}
.btn-3,.btn-8,.btn-13{
    background: #8f9ce9;
}
.btn-4,.btn-9,.btn-14{
    background: #8979ff;
}
/* Suggested Level Card */
.suggested-level-card {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.suggested-level-card h5 {
    font-size: 22px;
    font-weight: bold;
    margin-top: 10px;
    text-transform: uppercase;
}

/* Suggested Content Card */
.suggested-content-card {
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Each Content Item */
.content-box {
    border-radius: 10px;
    transition: 0.3s;
    background: #f9fafc;
}

.content-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

/* Button Styling */
.btn-primary {
    background: #4facfe;
    border: none;
    border-radius: 6px;
}

.btn-primary:hover {
    background: #007bff;
}
.badge-info {
    background: #4facfe;
    color: white;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 20px;
}
</style>
<div class="content-main flex-fill">
  
    @php
        $k=0;
        $user_profile = Session::get('user_profile_name');
        $show_block = 'NO';
        if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
        {
            $show_block = 'YES';
        }

        if(isset($_REQUEST['preload_lms'])) {
            $readonly="pointer-events: none";
            $preload_lms = "preload_lms=preload_lms";
        }
        if(!isset($_REQUEST['perm'])) {
            $_REQUEST['perm'] = session()->get('sub_institute_id');
        }
        $mappedValues = '';
        if(isset($_REQUEST['mapped_values'])) {
            $mappedValues = $_REQUEST['mapped_values'];
        }
    @endphp

    <div class="row align-items-center">
        <div class="col-md-6 mb-3">
            <h1 class="h4 mb-3">Suggested Content</h1>
            <div class="suggested-level-card mb-3">
    <h4>Suggested Level</h4>
    <h5 class="text-primary">
       @php $displayLevel = $student_level ?? request()->student_level ?? null; @endphp
       {{ $displayLevel ? ucfirst(strtolower($displayLevel)) : 'N/A' }}
    </h5>
</div>
            <!-- <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('pal.index')}}">PAL</a></li>
                    @php $standard_name = DB::table('standard')->where('id',$_REQUEST['standard_id'])->get(); @endphp
                    <li class="breadcrumb-item"><a href="#">{{ $standard_name[0]->name ?? '' }}</a></li>
                    <li class="breadcrumb-item"><a href="#">{{$data['subject_name']}}</a></li>
                </ol> -->
            </nav>
        </div>
    </div>
   
    <!-- <div class="row mb-5 bg-white p-2">
        @foreach($data['lms_mapping_Values'] as $parentType=>$valueArr)
            <div class="col-md-12">
                @foreach($valueArr as $key=>$value)
                    @php 
                        $class='';
                        $explodeVal= isset($data['mapped_value']) ? explode(',',$data['mapped_value']) : [] ;
                       
                        if(!empty($explodeVal) && in_array($value->id,$explodeVal)){
                             $class='btnactive';
                        }
                    @endphp
                    <button class="btn {{$class}} btn-{{$k++}}" onclick="filterContent('{{$value->id}}','{{$parentType}}')"> {{ $value->name }} </button>
                @endforeach
            </div>
        @endforeach
    </div> -->

    <!-- <div class="row" id="chapterList">
        @if(!empty($data['data']))
            @foreach($data['data'] as $chapter)
            @php $chapter_id = $chapter['id']; @endphp
            <div class="col-md-12" style="margin-bottom:20px;">
                <div class="card chapter-card">
                    <div class="card-header" id="headingOne">
                        <h5 class="mb-0">
                            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse_{{$chapter_id}}" aria-expanded="true" aria-controls="collapse_{{$chapter_id}}">
                            {{ $chapter['chapter_name'] }}
                            @if(isset($data['content_data'][$chapter_id]))
                                <span style="font-size: 12px; color: #333;">({{ count($data['content_data'][$chapter_id]) }} content types)</span>
                            @endif
                            </button>
                        </h5>
                    </div>
                    <div id="collapse_{{$chapter_id}}" class="collapse" aria-labelledby="headingOne" data-parent="#chapterList">
                        <div class="card-body">
                            @if(isset($data['content_data'][$chapter_id]))
                                @foreach($data['content_data'][$chapter_id] as $contentCategory => $contents)
                                <div class="content-category-section" style="margin-bottom: 20px;">
                                    <h6 style="background: #f4f4f4; padding: 8px; border-radius: 4px;">{{ $contentCategory }}</h6>
                                    @if(!empty($contents))
                                        <div class="row">
                                        @foreach($contents as $content)
                                            @if(!empty($content))
                                            <div class="col-md-3 mb-3">
                                                <div class="card" style="border: 1px solid #ddd; border-radius: 5px;">
                                                    <div class="card-body">
                                                        <h6>{{ $content['title'] ?? 'Untitled' }}</h6>
                                                        <p class="text-muted" style="font-size: 12px;">{{ $content['description'] ?? '' }}</p>
                                                        @if(isset($content['content_url']))
                                                        <a href="{{ $content['content_url'] }}" target="_blank" class="btn btn-sm btn-primary">View Content</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted">No content available</p>
                                    @endif
                                </div>
                                @endforeach
                            @else
                                <p class="text-muted">No content available for this chapter</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        @else
            <div class="col-md-12">
                <div class="alert alert-info">No chapters found for the selected criteria.</div>
            </div>
        @endif
    </div> -->
  <div class="suggested-content-card p-3">
    <h4>Suggested Content</h4>

    @if(!empty($data['content_data']))
        @foreach($data['content_data'] as $chapterId => $categories)
            @foreach($categories as $category => $contents)
                @foreach($contents as $content)

                    @if(!empty($content))
                   <div class="content-box p-3 mb-3">
                      <span class="badge badge-info mb-2">
        {{ $category }}
    </span>
                        <h5>{{ $content['title'] ?? 'Untitled' }}</h5>

                        @if(!empty($content['description']))
                            <p>{{ $content['description'] }}</p>
                        @endif

                        @if(isset($content['content_url']))
                            <a href="{{ $content['content_url'] }}" target="_blank" class="btn btn-primary btn-sm">
                                View Content
                            </a>
                        @endif
                    </div>
                    @endif

                @endforeach
            @endforeach
        @endforeach
    @else
        <p>No suggested content available</p>
    @endif

</div>
</div>

<script>
function filterContent(value, parentType) {
    var currentUrl = new URL(window.location.href);
    var mappedValue = currentUrl.searchParams.get('mapped_value') || '';
    var existingValues = mappedValue ? mappedValue.split(',') : [];
    
    if (existingValues.includes(value.toString())) {
        var index = existingValues.indexOf(value.toString());
        existingValues.splice(index, 1);
    } else {
        existingValues.push(value);
    }
    
    currentUrl.searchParams.set('mapped_value', existingValues.join(','));
    currentUrl.searchParams.set('mapping_type', parentType);
    window.location.href = currentUrl.toString();
}

function clearSerach() {
    var currentUrl = new URL(window.location.href);
    currentUrl.searchParams.delete('mapped_value');
    currentUrl.searchParams.delete('mapping_type');
    window.location.href = currentUrl.toString();
}
</script>
@include('includes.lmsfooterJs')
@endsection
