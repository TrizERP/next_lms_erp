@if(isset($data['starredContent']))
    <div class="card">
        @foreach($data['starredContent'] as $key => $value)
            <div class="row rowData">
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-1">
                            @php
                                if(isset($value['attachment'])){
                                    $fileName = $value['attachment'];
                                    $extension = \Illuminate\Support\Facades\File::extension($fileName);
                                    $title = $extension." File Attached";
                                }else{
                                    $extension = 'no_file';
                                    $title = "No Attachment Found";
                                }

                                $extensionIcons = "mdi mdi-file-alert-outline";
                                $fontColor = 'red';
                                
                                if(in_array($extension,$extensionDocArr)){
                                    $extensionIcons = "mdi mdi-file-word";
                                    $fontColor = '#7676f1';
                                }
                                elseif(in_array($extension,$extensionXlArr)){
                                    $extensionIcons = "mdi mdi-file-excel"; 
                                    $fontColor = 'green';
                                }
                                elseif(in_array($extension,$extensionImgArr)){
                                    $extensionIcons = "mdi mdi-image";
                                    $fontColor = '#7676f1'; 
                                }
                                elseif(in_array($extension,$extensionVidArr)){
                                    $extensionIcons = "mdi mdi-video-box";
                                    $fontColor = '#7676f1'; 
                                }
                                elseif($extension=="pdf"){
                                    $extensionIcons = "mdi mdi-file-pdf-box";
                                }
                            @endphp

                            <a style="color:{{$fontColor}};font-size:4rem" @if(isset($value['attachment'])) href="https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/{{$value['attachment']}}" target="_blank" @endif title="{{$title}}"><span class="{{$extensionIcons}} fileIcons"></span></a>

                        </div>  
                        <div class="col-md-8">
                            <h4 class="headTitle"><strong>{{$value['title']}}</strong></h4>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-9">
                            <div class="decriptionDiv">
                                {{$value['description']}}
                            </div>
                            @php 
                                $decodeJson = json_decode($value['keywords'],true);
                            @endphp
                            @if(!empty($decodeJson))
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
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-2 actionDiv">
                    <!-- actions start 'view','download','shared','copy','edit','starred'  -->
                    @php 
                        $all_action = isset($value['all_actions']) ? explode(',',$value['all_actions']) : [];
                        $class_shared1 = $class_copy1 = $class_starred1 = "";
                        if(in_array('shared',$all_action)){
                        $class_shared1='clickedIcon';
                        }
                        if(in_array('copy',$all_action)){
                        $class_copy1='clickedIcon';
                    }
                    if(in_array('starred',$all_action)){
                        $class_starred1='clickedIcon';
                    }
                    @endphp
                    <div class="actionIcons {{$class_shared1}} shared_{{$value['id']}}">
                        <a class="iconsAchor" onclick="addActivity({{$value['id']}},'shared');"  data-toggle="modal" data-target="#shareContent3_{{$value['id']}}"><i class="fa fa-share-alt"></i></a>
                    </div>
                    <div class="actionIcons {{$class_starred1}} starred_{{$value['id']}}">
                        <a class="iconsAchor"  href="/download-File?ContentId={{$value['id']}}" onclick="addActivity({{$value['id']}},'starred');"><i class="mdi mdi-star star_{{$value['id']}}"></i></a>
                    </div>
                    <div class="actionIcons {{$class_copy1}} copy_{{$value['id']}}">
                        <a class="iconsAchor" onclick="addActivity({{$value['id']}},'copy');"><i class="fa fa-clone"></i></a>
                    </div>
                    <div class="actionIcons view_{{$value['id']}}">
                        <a class="iconsAchor " href="{{route('content_library.show',[$value['id']])}}" target="_blank" onclick="addActivity({{$value['id']}},'view');"> <i class="mdi mdi-eye"></i> </a>
                    </div>
                    @if(isset($value['attachment']))
                    <div class="actionIcons download_{{$value['id']}}">
                        <a class="iconsAchor"  href="/download-File?ContentId={{$value['id']}}"  onclick="addActivity({{$value['id']}},'download');"><i class="fa fa-download"></i></a>
                    </div>
                  
                    @endif
                    <!-- action end -->
                </div>

            </div>

            <!-- content share model  -->
            <div class="modal fade" id="shareContent3_{{$value['id']}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title headingH2" id="exampleModalLabel">Share Content</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @php 
                            $url = 'https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/'.$value['attachment']; 
                            $encodedUrl = urlencode($url);
                            // Sharing URLs
                            $facebookShare = 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl;
                            $twitterShare = 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . urlencode('Check this out!');
                            $linkedinShare = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl;
                            $whatsappShare = 'https://web.whatsapp.com/send?text=' . $encodedUrl;
                        @endphp
                        <div class="actionDiv">
                            <div class="actionIcons">
                                <a class="iconsAchor" target="_blank" href="{{$facebookShare}}"><span class="mdi mdi-facebook"></span></a>
                            </div>
                            <div class="actionIcons">
                                <a class="iconsAchor" target="_blank" href="{{$twitterShare}}"><span class="mdi mdi-twitter"></span></a>
                            </div>
                            <div class="actionIcons">
                                <a class="iconsAchor" target="_blank" href="{{$whatsappShare}}"><span class="mdi mdi-whatsapp"></span></a>
                            </div>
                            <div class="actionIcons">
                                <a class="iconsAchor" target="_blank" href="{{$linkedinShare}}"><span class="mdi mdi-linkedin"></span></a>
                            </div>
                            <div class="actionIcons">
                                <a class="iconsAchor" onclick="copyToClipboard('{{$url}}');"><i class="fa fa-clone"></i></a>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
            <!-- content share model  -->
        @endforeach
    </div>
@endif