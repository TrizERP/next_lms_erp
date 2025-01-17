@if(isset($data['ownContent']))
    <div class="card">
        @foreach($data['ownContent'] as $key => $value)
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
                                elseif($extension=="pdf"){
                                    $extensionIcons = "mdi mdi-file-pdf-box";
                                }
                            @endphp

                            <a style="color:{{$fontColor}};font-size:4rem" @if(isset($value['attachment'])) href="https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/{{$value['attachment']}}" target="_blank" @endif title="{{$title}}"><span class="{{$extensionIcons}} fileIcons"></span></a>

                        </div>  
                        <div class="col-md-8">
                            <h4 class="headTitle"><strong>{{$value['title']}}</strong>&nbsp;&nbsp;<span class="statusLabel">{{$value['status']}}</span></h4>
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
        
                    <div class="actionIcons">
                        <a class="iconsAchor"  href="{{ route('content_library.edit',$value['id'])}}"><span class="mdi mdi-pencil"></span></a>
                    </div>
                    <form action="{{ route('content_library.destroy', $value['id'])}}" method="post" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <div class="actionIcons">
                        <button type="submit" onclick="return confirmDelete();" class="iconsAchor" style="background:transparent"><span class="mdi mdi-delete-empty"></span></button>
                    </div>
                    </form>
                    <!-- action end -->
                </div>

            </div>
        @endforeach
    </div>
    @endif