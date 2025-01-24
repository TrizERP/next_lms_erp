@extends('layout')
@section('container')
<style>
   #movingAni {
  bottom: 15%;
  position: absolute;
  transform: rotateY(180deg);
  animation: linear infinite;
  animation-name: run;
  animation-duration: 7s;
}
@keyframes run {
  0% {
    left: 0;
  }
  50% {
    left: 100%;
  }
  100% {
    left: 0;    
  }
}
.activeBtn{
    box-shadow: 5px 10px #95c0d7;
    margin : 0px 10px 16px 10px;
}
.libraryBtn{
    background: #fff;
    padding: 10px 14px;
    border: 3px solid #20a5cc;
    color: #167aaf;
}
.libraryBtn:hover{
    background: #20a5cc;
    color:#fff;
}
.headingH2{
    margin: 0px;
    padding: 10px 0px;
    font-family: cursive;
    color: #20a5cc;
    font-weight: bolder;
}
.rowData{
    margin: 60px 20px;
    /* padding: 10px; */
    border-radius: 20px;
    /* border-top-left-radius: 100px;
    border-bottom-left-radius: 100px; */
    border: 1px solid #ddd;
    box-shadow: 5px 10px #ddd;
}
.fileIcons{
    background : #fff;
}
.searchNoDiv{
    text-align: center;
}
.noBtn{
    padding: 10px 0px;
    border: 1px solid #ddd;
    border-radius: 30px;
    border-bottom-left-radius: 30px;
    color: #fff;
    background: #e7a52c;
}
.actionDiv,.dataParent{
    display: flex;
    flex-wrap: wrap;
}
.dataParent{
    padding-bottom:20px;
}
.actionDiv{
    margin-top:10px;
    align-item : center;
}
/* .actionIcons{
    font-size: 1.5rem;
    color: #20a5cc;
    margin: 6px;
    padding: 10px 10px;
    border: 1px solid #20a5cc;
    border-radius: 50%;
} */
.actionIcons, .actionIcons .iconsAchor{
    color:#20a5cc;
}
.headTitle{
    margin-top: 17px;
    margin-bottom: 0px;
}
.actionIcons .iconsAchor{
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1px solid #20a5cc;
    display: flex; 
    align-items: center;
    justify-content: center;
    cursor: pointer; 
    margin:4px;
}

/* .actionIcons:hover, */
 .actionIcons .iconsAchor:hover, .clickedIcon .iconsAchor {
    background-color: #20a5cc;
    color: white;
}

.dataDiv,.decriptionDiv{
    padding:6px;
    display:flex;
    flex-wrap:wrap;
}
hr{
    border : 1px solid #20a5cc !important;
    margin : 16px 0px !important;
    border-radius: 20px;
}
h2,h3,h4,h5,h6,p{
    margin-bottom:0px;
}
.dataHead, .dataValue{
    padding : 6px 14px;
    border: 1px solid #20a5cc;
}
.dataHead{
    /* border-top-right-radius:20px; */
    border-top-left-radius:20px;
    background : aliceblue; 
}
.dataValue{
    border-bottom-right-radius:20px;
    /* border-bottom-left-radius:20px; */
}
.statusLabel{
    padding: 4px 10px;
    background: #ef8da0;
    color: #fff;
    border-radius: 20px;
}
</style>
<div id="page-wrapper">
   <div class="container-fluid">

      <div class="white-box">
         <div class="panel-body">
            <!-- content add button -->
            <div class="row">
                <div class="col-md-11 text-right">
                    <div class="addBtn">
                        <a class="libraryBtn" href="{{route('content_library.create')}}"><i class="fa fa-plus-circle" aria-hidden="true"></i>&nbsp;Add Your Content</a>
                    </div>
                </div>
                <div class="col-md-1">
                    <div id="movingAni">
                        <img src="/Images/pointing.png" width=80px height=80px alt="pointing">
                    </div>  
                </div>
            </div>
            <!-- content add button end -->

            <!-- header card start  -->
            <div class="card">
                <!-- header start  -->
                <div class="row">
                    <!-- for title  -->
                    <div class="col-md-4">
                        <h2 class="headingH2">Content Library</h2>
                    </div>
                    <!-- for buttons  -->
                    <div class="col-md-8">
                        <div class="headerButtons text-right">
                            <a class="btn libraryBtn btn1 activeBtn" onclick="makeActive(1);">
                                <i class="fa fa-search" aria-hidden="true"></i>&nbsp;Browse
                            </a>
                            <a class="btn libraryBtn btn2" onclick="makeActive(2);">
                                <i class="fa fa-share-alt" aria-hidden="true"></i>&nbsp;Shared
                            </a>
                            <a class="btn libraryBtn btn3" onclick="makeActive(3);">
                                <i class="fa fa-star" aria-hidden="true"></i>&nbsp;Starred
                            </a>
                            <a class="btn libraryBtn btn4" onclick="makeActive(4);">
                            <i class="fa fa-download" aria-hidden="true"></i>&nbsp;Downloads
                            </a>
                            <a class="btn libraryBtn btn5" onclick="makeActive(5);">
                                <i class="fa fa-clone" aria-hidden="true"></i>&nbsp;Copied
                            </a>
                            <a class="btn libraryBtn btn6" onclick="makeActive(6);">
                                <i class="fa fa-folder" aria-hidden="true"></i>&nbsp;My Own
                            </a>
                        </div>
                    </div>
                </div>
                <!-- header end  -->
            </div>
            <!-- header card end  -->

            <!-- body card start  -->
            <div class="card">
                <!-- browse part  -->
                <div class="collType collapse_1">
                    <form action="{{route('content_library.index')}}">
                        @csrf
                        <input type="hidden" name="search_type" value="content_search">
                        <div class="card" style="padding:20px !important">
                            <div class="row" style="padding:20px">
                                <div class="col-md-4 form-group">
                                    <label for="Title">Title</label>
                                    <input type="text" class="form-control" name="title" @if(isset($data['searchedTitle'])) value="{{$data['searchedTitle']}}" @endif>
                                </div>

                                @foreach($data['mapType'] as $key=>$value)
                                @if(isset($data['mapValue'][$value->name]) && !empty($data['mapValue'][$value->name]))
                                <div class="col-md-4 form-group">
                                    <label for="{{$value->name}}">{{$value->name}}</label>
                                    <select name="keywords[{{$value->name}}]" id="select_{{$key}}" class="form-control">
                                        <option value="">Select any one</option>
                                        @foreach($data['mapValue'][$value->name] as $k=>$val)
                                        <option value="{{$val->name}}" @if(isset($data['keywordSearch'][$value->name]) && $data['keywordSearch'][$value->name]==$val->name) selected @endif>{{$val->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                @endforeach

                                <div class="col-md-12 mb-2">
                                <center>
                                <button type="submit" name="submit" class="libraryBtn">
                                    <i class="fa fa-search" aria-hidden="true"></i>&nbsp; Search
                                </button>
                                </center>
                                </div>
                            </div>
                        </div>
                    </form>
                    @php 
                        $extensionDocArr = ["docx","doc","dotx","dot","rtf"];
                        $extensionXlArr = ["xlsx","xls","xlsm","xlsb","csv"];
                        $extensionImgArr = ["png","jpeg","jpg","webp"];
                        $extensionVidArr = ['mp4', 'webm', 'ogg'];
                    @endphp
                    @include('lms.content_library.index_models.browse')
                </div>
               <!-- browse part  -->
               <!-- shared part -->
                <div class="collType collapse_2">
                @include('lms.content_library.index_models.share')
                </div>
               <!-- shared part -->
               <!-- starred part  -->
                <div class="collType collapse_3">
                @include('lms.content_library.index_models.starred')
                </div>
               <!-- starred part  -->
               <!-- added Downloads part  -->
                <div class="collType collapse_4">
                @include('lms.content_library.index_models.download')
                </div>
               <!-- added Downloads part  -->
                <!-- added Copied part  -->
                <div class="collType collapse_5">
                @include('lms.content_library.index_models.copy')
                </div>
               <!-- added Copied part  -->
                <!-- added content part  -->
                <div class="collType collapse_6">
                @include('lms.content_library.index_models.ownContent')
                </div>
               <!-- added content part  -->

            </div>
            <!-- body card end  -->

         </div>
      </div>

   </div>
</div>


@include('includes.lmsfooterJs')
<script>
    $('.collType').hide();
    $('.collapse_1').show();

    function makeActive(colNo){
        $('.collType').hide();
        $('.libraryBtn').removeClass('activeBtn');
        $('.collapse_'+colNo).show();
        $('.btn'+colNo).toggleClass('activeBtn');
    }

    function addActivity(content_id,type){
        $('.'+type+'_'+content_id).toggleClass('clickedIcon');
        // alert(type+'='+content_id);'view','download','shared','copy','edit','starred'
        $.ajax({
            url  : "{{route('content_library.store')}}",
            data : {insert_type:'activity',content_id:content_id,action:type,_token: '{{ csrf_token() }}'},
            type : 'POST',
            success : function(result){
                if(type=="starred")
                {
                    alert(result.message);
                }

                if(type=="copy"){
                    alert(result.message);
                }

                if(result.status_code==2){
                    $('.'+type+'_'+content_id).removeClass('clickedIcon');
                }

                if(result.status_code==1){
                    location.reload();
                }
            }
        })
    }

    function copyToClipboard(url) {
        navigator.clipboard.writeText(url).then(function() {
            alert("URL copied to clipboard!");
        }).catch(function(error) {
            alert("Failed to copy URL: " + error);
        });
    }
</script>
@include('includes.footer')
@endsection