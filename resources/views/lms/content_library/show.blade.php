@extends('layout')
@section('container')
<style>
   .titleHead, .descriptionDiv,.contentDivs,.fileDiv{
    padding: 16px;
    border-radius: 20px;
    margin: 10px 0px;
   }
   .titleHead{
    background:  #f9d4d4;
    text-align:center;
   }
   .descriptionDiv{
    background: cornsilk;
   }
   .contentDivs{
    background: #ceedf1;
    }
    .fileDiv{
        background: aliceblue;
        /* text-align:center; */
    }
   .dataParent{
        display: flex;
        flex-wrap: wrap;
        padding-bottom:20px;
    }
    .dataDiv{
        padding:6px;
        display:flex;
        flex-wrap:wrap;
    }

    .dataHead, .dataValue{
        padding : 6px 14px;
        border: 1px solid #20a5cc;
        height: max-content;
    }
    .dataHead{
        /* border-top-right-radius:20px; */
        border-top-left-radius:20px;
        background : aliceblue; 
    }
    .dataValue{
        border-bottom-right-radius:20px;
        background:#fff;
        /* border-bottom-left-radius:20px; */
    }

    h5,p{
        margin-bottom : 0px;
    }
</style>
<div id="page-wrapper">
   <div class="container-fluid">

        <div class="card row">

            <div class="col-md-12 titleHead"><h4>{{$data['editData']->title}}</h4></div>

            <div class="col-md-12 descriptionDiv">
                <h5><strong>Description</strong></h5>
                <hr>
                <div class="description">
                    {!!$data['editData']->description!!}
                </div>
            </div>

                @php 
                    $decodeJson = json_decode($data['editData']->keywords,true);
                @endphp
                @if(!empty($decodeJson))
            <div class="col-md-12 contentDivs">
                <h5><strong>Mappings</strong></h5>
                <hr>
                <div class="dataParent">
                    @foreach($decodeJson as $k=>$v)
                        @if(isset($v))
                        <div class="dataDiv">
                            <div class="dataHead">
                                <h5><strong>{{$k}}</strong></h5>
                            </div>
                            <div class="dataValue">
                                <p>{{$v}}</p>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
                @endif
            
            @if(isset($data['editData']->attachment))
            <div class="fileDiv">
            <h5><strong>File Attached</strong></h5>
                <hr>
            @php 
                $fileUrl = "https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/".$data['editData']->attachment;
                $fileExtension = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                $fileType = 'other';

                // Determine the file type
                if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $fileType = 'image';
                } elseif (in_array($fileExtension, ['mp4', 'webm', 'ogg'])) {
                    $fileType = 'video';
                }
                elseif (in_array($fileExtension, ['pdf'])) {
                    $fileType = 'pdf';
                }
            @endphp
                <div class="fileAttached text-center">
                @if($fileType=="pdf")
                <iframe src="{{ $fileUrl }}" style="width: 50%; height: 200px; border: none;"></iframe>
                @elseif($fileType=="image")
                <img src="{{ $fileUrl }}" alt="$data['editData']->attachment" width="300px" height="300px">
                @elseif($fileType=="video")
                <video src="{{ $fileUrl }}" width="300px" height="300px"></video>
                @else 
                <a href="{{ $fileUrl }}" target="_blank">Download File</a>
                </div>
                @endif
            </div>
            @endif
        </div>

    </div>
</div>
@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection