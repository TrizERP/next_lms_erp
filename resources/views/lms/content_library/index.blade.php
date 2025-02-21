@extends('layout')
@section('container')
<style>
    /* -- purple : 8979ff
    -- blue : 87c2fe
    -- dark blue : 1568BA
    -- orange : e98d38
    -- green : 20b24b
    -- grey : ebebf3 */
    .mdi, .fa{
        padding:0px 10px;
        font-size:1.3rem;
    }
    .activeNav > .mdi,.activeNav > .fa{
        color: #25bdea;
    }
    .boardBtn{
        background : #8979ff;
        border : none;
        margin : 0px 10px 0px 0px;
    }
    .boardBtn:hover{
        background : #5645d5;
    }
    .activeBoard{
        margin : 0px 10px 10px 10px;
        box-shadow: 3px 5px #5645d5;
    }
    .stdBtn{
        background : #87c2fe;
        border : none;
        margin : 0px 10px 0px 0px;
    }

    .ContentTypeBtn{
        background : #26dad2;
        border : none;
        margin : 0px 10px 0px 0px;
    }
    .TypeBtn{
        background : #8f9ce9;
        border : none;
        margin : 10px 10px 0px 0px;
    }

    .MatrialBtn{
        background : #ce9fff;
        border : none;
        margin : 10px 10px 0px 0px;
    }

    .stdBtn:hover{
        background : #2374c7;
     }
     .ContentTypeBtn:hover{
        background : #29b5af;
     }
     .TypeBtn:hover{
        background : #25337e;
     }
    .activeStd{
        margin : 0px 10px 10px 10px;
        box-shadow: 3px 5px #2374c7;
    }
    .activeContentType{
        margin : 0px 10px 10px 10px;
        box-shadow: 3px 5px #29b5af;
    }
    .activeType{
        margin : 0px 10px 10px 10px;
        box-shadow: 3px 5px #25337e;
    }

    hr{
        border-top: 3px solid #ebebf3;
        width: 100%;
        margin-top:4px;
    }
    .boardCard{
        padding: 20px 20px 6px 20px;
    }
    .boardCard, .standardCard{
        margin: 4px 0px !important;
    }
    .optionSelect{
        background:#ebebf3;
        padding: 4px;
    }
    .contentCard .card-body{
        padding: 16px;
        box-shadow: 3px 3px #ebebf3;
        border-radius: 10px;
    }
    .rounded-circle-icon {
        display: inline-flex;
        align-items: center; 
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        margin-top:4px
    }
    .rounded-circle-icon > span{
        color: white; 
        font-size: 0.8rem;
    }
    .shadow-sm{
        padding : 40px 60px;
    }
    .card-title{
        font-size:14px;
    }
    .card-text{
        font-size:12px;
    }
    .middleCard{
        height: 166px;
    }
</style>
<div id="page-wrapper">
   <div class="container-fluid">
      <div class="white-box">

        <div class="card boardCard">
            <!-- navbar  -->
            <div class="row">
                <div class="col-md-6 d-flex flex-wrap">
                @if(isset($data['boards']['mapValue']['Boards']))
                    <div class="boardDiv">
                        <label for="Board">Select Board</label><br>
                        <input type="hidden" name="keywords[Boards]" id="board">
                        @foreach($data['boards']['mapValue']['Boards'] as $key=>$value)
                            <a class="btn btn-primary boardBtn" onclick="getSearchedContents('contentDiv','');">{{$value->name}}</a>
                        @endforeach
                    </div>
                    <div class="boyDiv">
                        <img src="{{asset('admin_dep/images/library_boy.png')}}" alt="library_boy" width="85px">
                    </div>
                @endif
                </div>
                <div class="col-md-4 text-right">
                    <a class="anchorNav browseNav activeNav" onclick="getActive('browseNav','contentDiv','')"><span class="mdi mdi-magnify"></span></a>
                    <a class="anchorNav ownName" onclick="getActive('ownName','ownDiv','edit')"><span class="mdi mdi-folder"></span></a>
                    <a class="anchorNav copyNav" onclick="getActive('copyNav','copyDiv','copy')"><span class="mdi mdi-content-copy"></span></a>
                    <a class="anchorNav downloadNav" onclick="getActive('downloadNav','downloadDiv','download')"><span class="fa fa-download"></span></a>
                    <a class="anchorNav starNav" onclick="getActive('starNav','starDiv','starred')"><span class="mdi mdi-star"></span></a>
                    <a class="anchorNav shareNav" onclick="getActive('shareNav','shareDiv','shared')"><span class="mdi mdi-share-variant"></span></a>
                </div>
                <div class="col-md-2">
                   <a href="{{route('content_library.create')}}">Add Content <span class="mdi mdi-plus-circle text-black"></span></a>
                </div>
            </div>
            <!-- navbar  -->
        </div>

        <!-- standard search  -->
        <div class="card standardCard">
            <div class="row">
                <div class="col-md-12 d-flex flex-wrap">
                @if(isset($data['standards']['mapValue']['Standards']))
                    <div class="boardDiv">
                        <label for="standard">Select Standard</label><br>
                        <input type="hidden" name="keywords[Standards]" id="standard">
                        @foreach($data['standards']['mapValue']['Standards'] as $key=>$value)
                            <a class="btn btn-success stdBtn" data-type="{{$value->type}}" onclick="getSearchedContents('contentDiv','');">{{$value->name}}</a>
                        @endforeach
                    </div>
                @endif
                </div>
            </div>

            <div class="row text-center" style="padding:50px 24px 6px 24px">
                @foreach($data['courses']['mapType'] as $key=>$value)
                @php 
                    $course_name = str_replace(' ','_',$value->name);
                @endphp 
                    @if(isset($data['courses']['mapValue'][$course_name]) && !empty($data['courses']['mapValue'][$course_name]))
                    <div class="col-md-4 form-group">
                    <select name="keywords[{{$course_name}}]" id="Courses" class="form-control optionSelect" onchange="getContents(this,'subject');" onchange="getSearchedContents('contentDiv','');">
                        <option value="">Select {{$value->name}}</option>
                        @foreach($data['courses']['mapValue'][$course_name] as $k=>$val)
                        <option value="{{$val->name}}" data-parentId="{{$val->id}}">{{$val->name}}</option>
                        @endforeach
                    </select>
                    </div>
                    @endif
                @endforeach

                @if(!empty($data['courses']['mapValue']))
                <div class="col-md-4">
                    <select name="keywords[subject]" id="subject" class="form-control optionSelect" onchange="getSearchedContents('contentDiv','');">
                    <option value="">Select Subject</option>
                    </select>
                </div>
               
                <div class="col-md-4">
                    <select name="keywords[chapter]" id="chapter" class="form-control optionSelect" onchange="getSearchedContents('contentDiv','');"> 
                    <option value="">Select Chapter</option>
                    </select>
                </div>
                @endif
                @foreach($data['content_type']['mapType'] as $key=>$value)
                @php 
                    $type_name = str_replace(' ','_',$value->name);
                @endphp 
                    @if(isset($data['content_type']['mapValue'][$type_name]) && !empty($data['content_type']['mapValue'][$type_name]))
                    {{-- <div class="col-md-3 form-group">
                    <select name="keywords[{{$type_name}}]" id="select_{{$key}}" class="form-control optionSelect" onchange="getSearchedContents('contentDiv','');">
                        <option value="">Select {{$value->name}}</option>
                        @foreach($data['content_type']['mapValue'][$type_name] as $k=>$val)
                        <option value="{{$val->name}}">{{$val->name}}</option>
                        @endforeach
                    </select>
                    </div> --}}

                    <div class="col-md-12" style="padding: 10px 14px;text-align:left">
                    <label for="contentType">Select Content Type</label><br>
                    @foreach($data['content_type']['mapValue'][$type_name] as $k=>$value)
                    <input type="hidden" name="keywords[{{$type_name}}]" id="contentType">
                    <a class="btn btn-success ContentTypeBtn" data-type="{{$value->type}}" onclick="getSearchedContents('contentDiv','');">{{$value->name}}</a>
                    @endforeach
                    @endif
                    </div>
                @endforeach

                @foreach($data['otherMaps']['mapType'] as $key=>$value)
                @php 
                    $otherMap = str_replace(' ','_',$value->name);
                @endphp 
                    @if(isset($data['otherMaps']['mapValue'][$otherMap]) && !empty($data['otherMaps']['mapValue'][$otherMap]))
                    {{-- <div class="col-md-3 form-group">
                    <select name="keywords[{{$otherMap}}]" id="select_{{$key}}" class="form-control optionSelect" onchange="getSearchedContents('contentDiv','');">
                        <option value="">Select any {{$value->name}}</option>
                        @foreach($data['otherMaps']['mapValue'][$otherMap] as $k=>$val)
                        <option value="{{$val->name}}">{{$val->name}}</option>
                        @endforeach
                    </select>
                    </div> --}}

                    <div class="col-md-12" style="padding: 10px 10px 14px;text-align:left">
                    <label for="Types">Select Types</label><br>
                    @foreach($data['otherMaps']['mapValue'][$otherMap] as $k=>$value)
                    <input type="hidden" name="keywords[{{$otherMap}}]" id="Type">
                    <a class="btn btn-success TypeBtn" data-type="{{$value->type}}" onclick="getSearchedContents('contentDiv','');">{{$value->name}}</a>
                    @endforeach
                    </div>
                    @endif
                @endforeach

                <!-- Start Material Type-->
                @foreach($data['material_type']['mapType'] as $key=>$value)
                @php 
                    $material = str_replace(' ','_',$value->name);
                @endphp 
                    @if(isset($data['material_type']['mapValue'][$material]) && !empty($data['material_type']['mapValue'][$material]))
                    <div class="col-md-12" style="padding: 10px 14px;text-align:left">
                    <label for="Types">Material Types</label><br>
                    @foreach($data['material_type']['mapValue'][$material] as $k=>$value)
                    <input type="hidden" name="keywords[{{$material}}]" id="Type">
                    <a class="btn btn-danger MatrialBtn" data-type="{{$value->type}}" onclick="getSearchedContents('contentDiv','');">{{$value->name}}</a>
                    @endforeach
                    </div>
                    @endif
                @endforeach
        </div>
        <!-- standard search  -->
        </div>

        <!-- contentDiv  -->
        <div id="contentDiv" class="allContent contentDiv"></div>
        <!-- contentDiv -->

        <!-- downloadDiv  -->
        <div id="downloadDiv" class="allContent downloadDiv"></div>
        <!-- downloadDiv -->

        <!-- starDiv  -->
        <div id="starDiv" class="allContent starDiv"></div>
        <!-- starDiv -->

        <!-- ownDiv  -->
        <div id="ownDiv" class="allContent ownDiv"></div>
        <!-- ownDiv -->

         <!-- copyDiv  -->
         <div id="copyDiv" class="allContent copyDiv"></div>
        <!-- copyDiv -->

        <!-- shareDiv  -->
        <div id="shareDiv" class="allContent shareDiv"></div>
        <!-- shareDiv --> 

    </div>
</div>
@include('includes.lmsfooterJs')
<script>
    $(document).ready(function(){
        $('.allContent').hide();
        $('.contentDiv').show();
        getSearchedContents('contentDiv','');

        // $('.anchorNav').on('click',function(){
        //     $('.anchorNav').removeClass('activeNav');
        //     $(this).addClass('activeNav');
        // })

        $('.boardBtn').on('click',function(){
            $('.boardBtn').removeClass('activeBoard');
            $(this).addClass('activeBoard');
            let buttonText = $(this).text().trim();
            $('#board').val(buttonText);
            getSearchedContents('contentDiv','');
        })

        $('.stdBtn').on('click',function(){
            $('.stdBtn').removeClass('activeStd');
            $(this).addClass('activeStd');
            let buttonText = $(this).text().trim();
            $('#standard').val(buttonText);
            getSearchedContents('contentDiv','');
            $('#Courses').val('');
            $('#subject').find('option').remove().end().append('<option value="">Select subject</option>').val('');
            $('#chapter').find('option').remove().end().append('<option value="">Select chapter</option>').val('');
        })

        $('.ContentTypeBtn').on('click',function(){
            $('.ContentTypeBtn').removeClass('activeContentType');
            $(this).addClass('activeContentType');
            let buttonText = $(this).text().trim();
            $('#contentType').val(buttonText);
            getSearchedContents('contentDiv','');
        })

        $('.TypeBtn').on('click',function(){
            $('.TypeBtn').removeClass('activeType');
            $(this).addClass('activeType');
            let buttonText = $(this).text().trim();
            $('#Type').val(buttonText);
            getSearchedContents('contentDiv','');
        })
        
        $('#subject').on('change',function(){
            getMappedChapter();
        })
    })

  
    function getActive(navName,divName,searchType){
        $('.anchorNav').removeClass('activeNav');
        $('.'+navName).addClass('activeNav');

        $('.allContent').hide();
        $('.'+divName).show();

        $('.boardBtn').removeClass('activeBoard');
        $('.stdBtn').removeClass('activeStd');
        $('#board').val('');
        $('#standard').val('');

        getSearchedContents(divName,searchType);
    }
    
    
    function getInputVal(id,value){
        $('#input_'+id).empty();
        $('#input_'+id).val(value);
    }

    function getContents(event, content_type) {
        var selectedOption = $(event).find(':selected');
        var value = selectedOption.val();
        var parentId = selectedOption.data('parentid');

        $('#'+content_type).empty();

        $.ajax({
          url: "{{route('getMapVals')}}",
          data : {parent_id:parentId},
          type : 'GET',
          success : function(result){
            console.log(result);
            $('#'+content_type).find('option').remove().end().append('<option value="">Select '+content_type+'</option>').val('');
            if(result.length>0){
              result.forEach(function(item) {
                //   $("#"+content_type).append(
                //       $("<option></option>").val(item['name']).html(item['name'])
                //   );
                $("#" + content_type).append(`<option value="${item['name']}" data-parentid="${item['id']}" data-type="${item['type']}">${item['name']}</option>`); 

              });
            }
          }
        })

        getSearchedContents("contentDiv",'') 
    }

    function getSearchedContents(appendDiv,contentType='') {
        let keywordArr = collectKeywords();

         $.ajax({
            url: "{{route('searchContent')}}",
            data : {contentType:contentType,keywords:keywordArr},
            type: 'GET',
            success: function (result) {

                console.log(result);
     
                // Append the main card to the contentDiv
                $("#"+appendDiv).append(getCard(result,appendDiv));
            },
            error: function (xhr, status, error) {
                console.error("An error occurred:", error);
            }
        });
    }
    function collectKeywords() {
        let keywordsData = {};

        $("input[name^='keywords'], select[name^='keywords']").each(function () {
            let name = $(this).attr("name").replace("keywords[", "").replace("]", ""); // Extracting key name
            let value = $(this).val();
            if (value) {
                keywordsData[name] = value;
            }
        });

        return keywordsData;
    }

    // Example AJAX request:
    function sendKeywords() {
        let data = collectKeywords();

        $.ajax({
            url: "{{route('searchContent')}}",
            data : {keywords:data},
            type: 'GET',
            success: function (result) {

                console.log(result);
     
                // Append the main card to the contentDiv
                $("#contentDiv").append(getCard(result,"contentDiv"));
            },
            error: function (xhr, status, error) {
                console.error("An error occurred:", error);
            }
        });
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

                if(result.status_code==1 && type!="shared"){
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

    function getCard(result, appendDiv) {
        const extensionDocArr = ["docx", "doc", "dotx", "dot", "rtf"];
        const extensionXlArr = ["xlsx", "xls", "xlsm", "xlsb", "csv"];
        const extensionPptArr = ["pptx", "ppt", "potx", "pot","ppsx","pps"];
        const extensionImgArr = ["png", "jpeg", "jpg", "webp"];
        const extensionVidArr = ["mp4", "webm", "ogg"];
        

        // Check if result has values
        if (!result || result.length === 0) {
            $("#" + appendDiv).html("<p class='text-center text-muted'>No content found.</p>");
            return;
        }

        // Clear the contentDiv first
        $("#" + appendDiv).empty();

        // Create a main card to hold all content
        const mainCard = $(`
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row"></div>
                </div>
            </div>
        `);

        const cardRow = mainCard.find(".row");
        const colors = ['#1568BA', '#e98d38', '#20b24b', '#8979ff'];

        // Iterate through results and create individual cards
        result.forEach(function(item, index) {
            const truncatedDescription = item.description.length > 150
                ? item.description.substring(0, 150) + "..."
                : item.description;

            const truncatedTitle = item.title.length > 100
                ? item.title.substring(0, 100) + "..."
                : item.title;

            let extension = "no_file";
            let extensionIcons = '';
            // Sharing URLs
            var fileurl = '';
            var facebookShare = '';
            var twitterShare = '';
            var linkedinShare = '';
            var whatsappShare = '';
            let downloadBtn = '';

            if (item.attachment) {
                extension = item.attachment.split('.').pop().toLowerCase(); // Extract extension
                // Check for extension and set corresponding icon and color
                if (extensionDocArr.includes(extension)) {
                    extensionIcons = "mdi mdi-file-word";
                } else if (extensionXlArr.includes(extension)) {
                    extensionIcons = "mdi mdi-file-excel";
                } else if (extensionImgArr.includes(extension)) {
                    extensionIcons = "mdi mdi-image";
                } else if (extensionVidArr.includes(extension)) {
                    extensionIcons = "mdi mdi-video-box";
                }
                else if (extensionPptArr.includes(extension)) {
                    extensionIcons = "mdi mdi-file-powerpoint-outline";
                } else if (extension === "pdf") {
                    extensionIcons = "mdi mdi-file-pdf-box";
                }
                
                fileurl = 'https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/' + item.attachment;
                var encodedUrl = encodeURIComponent(fileurl);
                // Sharing URLs
                facebookShare = 'https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl;
                twitterShare = 'https://twitter.com/intent/tweet?url=' + encodedUrl + '&text=' + encodeURIComponent('Check this out!');
                linkedinShare = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodedUrl;
                whatsappShare = 'https://web.whatsapp.com/send?text=' + encodedUrl;

                downloadBtn=  `<a class="rounded-circle-icon" style="background-color: black" href="/download-File?ContentId=${item.id}" target="_blank" onclick="addActivity(${item.id},'download');"><span class="fa fa-download"></span></a>`;
            }

            const cardColor = colors[index % colors.length];

            let editDelete = '';
            let cardTitle = '';
            if(appendDiv==="ownDiv"){
                editDelete=  `
                    <a class="rounded-circle-icon" style="background-color: black" href="/lms/content_library/${item.id}/edit">
                        <span class="mdi mdi-pencil"></span>
                    </a>
                    <a class="rounded-circle-icon" style="background-color: black" href="#" onclick="event.preventDefault(); deleteContent(${item.id});">
                        <span class="mdi mdi-delete-empty"></span>
                    </a>
            `;
            cardTitle = `${item.status}`;
            }
            // Create individual card
            const card = $(`
                <div class="col-md-3 mb-3">
                    <div class="card contentCard">
                        <div class="card-body" title="${cardTitle}" style="height:280px">
                            <div class="d-flex flex-wrap">
                                <a class="rounded-circle-icon" style="background-color: ${cardColor};">
                                    <span class="${extensionIcons}"></span>
                                </a>
                            </div>
                            <div class="middleCard">
                            <h5 class="card-title" style="margin-top:10px">${truncatedTitle}</h5>
                            <p class="card-text">${truncatedDescription}</p>
                            </div>
                            <div class="action-btn text-center">
                                <a class="rounded-circle-icon" style="background-color: black" href="/lms/content_library/${item.id}"><span class="mdi mdi-eye-outline"></span></a>
                                <a class="rounded-circle-icon" style="background-color: black" onclick="addActivity(${item.id},'copy');"><span class="mdi mdi-content-copy"></span></a>
                                ${downloadBtn}
                                <a class="rounded-circle-icon" style="background-color: black" onclick="addActivity(${item.id},'starred');"><span class="mdi mdi-star"></span></a>
                                <a class="rounded-circle-icon" style="background-color: black" data-toggle="modal" data-target="#shareContent_${item.id}" onclick="addActivity(${item.id},'shared');"><span class="mdi mdi-share-variant"></span></a>
                                ${editDelete}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="shareContent_${item.id}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title headingH2" id="exampleModalLabel">Share Content</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="actionDiv d-flex flex-wrap">
                                    <div class="actionIcons">
                                        <a class="iconsAchor" target="_blank" href="${facebookShare}"><span class="mdi mdi-facebook"></span></a>
                                    </div>
                                    <div class="actionIcons">
                                        <a class="iconsAchor" target="_blank" href="${twitterShare}"><span class="mdi mdi-twitter"></span></a>
                                    </div>
                                    <div class="actionIcons">
                                        <a class="iconsAchor" target="_blank" href="${whatsappShare}"><span class="mdi mdi-whatsapp"></span></a>
                                    </div>
                                    <div class="actionIcons">
                                        <a class="iconsAchor" target="_blank" href="${linkedinShare}"><span class="mdi mdi-linkedin"></span></a>
                                    </div>
                                    <div class="actionIcons">
                                        <a class="iconsAchor" onclick="copyToClipboard('${fileurl}');"><i class="fa fa-clone"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            // Append the card to the row inside the main card
            cardRow.append(card);
        });
        return mainCard;
    }

const deleteContent = (id) => {
    if (confirm("Are you sure you want to delete this content?")) {
        $.ajax({
            url: `/lms/content_library/${id}`,
            type: 'POST',
            data: {
                _method: 'DELETE',
                "_token": "{{ csrf_token() }}",
            },
            success: function(response) {
                alert("Content deleted successfully.");
                location.reload();
            },
            error: function(xhr, status, error) {
                alert("Error occurred while deleting the content.");
            }
        });
    }
};
function getMappedChapter(){
      var subjectID = $('#subject option:selected').attr('data-type');
      var standardID = $('.activeStd').attr('data-type');
      if(!standardID){
        alert('please select standard to get chapter');
      }
      if(!subjectID){
        alert('please select subject to get chapter');
      }
      console.log(standardID+'-'+subjectID);
      if (subjectID && standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#chapter").empty();
                        $("#chapter").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {      
                            $("#chapter").append('<option value="' + value + '" >' + value + '</option>');
                        });

                    } else {
                        $("#chapter").empty();
                        $("#chapter").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#chapter").empty();
            $("#chapter").append('<option value="">Select Chapter</option>');
        }
    }

</script>
@include('includes.footer')
@endsection