<div class="row">
    <!-- side na bar start  -->
    <div class="col-md-2">
        <div class="card roundCard">
             <div class="toggleDiv" style="padding:10px">
                <h6>Search :</h6>
                <hr style="margin: 10px 0px;">

                <input type="text" class="form-control" id="searchInput" name="searchInput" style="padding:6px;height:30px !important" placeholder="search">

            </div>
            <div class="toggleDiv" style="padding:10px">
                <h6>TOGGLE</h6>
                <hr style="margin: 10px 0px;">

                <div class="toggleBtn"><button class="btn btn-outline-secondary" onclick="openAll()"><span class="mdi mdi-folder-open"></span> Open All</button></div>

                <div class="toggleBtn"><button class="btn btn-outline-secondary" onclick="closeAll()"><span class="mdi mdi-folder"></span> Close All</button></div>
            </div>

            <div class="toggleDiv" style="padding:10px">
                <h6>CONTROL</h6>
                <hr style="margin: 10px 0px;">
                <div class="toggleBtn">
                    <a class="btn btn-outline-secondary viewTree" onclick="openAll()"><span class="mdi mdi-eye"></span> View</a>
                </div>

                <div class="toggleBtn">
                    <a class="btn btn-outline-secondary editTree" onclick="openAll()"><span class="mdi mdi-pencil-box-outline"></span> Edit</a>
                </div>

                <div class="toggleBtn">
                    <a class="btn btn-outline-secondary deleteTree" onclick="openAll()"><span class="mdi mdi-delete-circle"></span> Delete</a>
                </div>

            </div>

            <div class="toggleDiv" style="padding:10px">
                <h6>ADD NEW</h6>
                <hr style="margin: 10px 0px;">
                <!-- <div class="toggleBtn"><a class="btn btn-outline-secondary" ><span class="mdi mdi-folder"></span> New category</a></div> -->

                <div class="toggleBtn"><a class="btn btn-outline-secondary" href="{{route('skill_library.create')}}"><span class="mdi mdi-folder"></span> New Skill</a></div>
            </div>
        </div>
    </div>
    <!-- side na bar end  -->
    <!-- tree view start  -->
    <div class="col-md-10">
        <div class="card roundCard">
            <ul class="tree">
                <li>
                    <summary onclick="actionLi('parent')">
                        <span class="treeSpan"><i class="mdi mdi-folder"></i> All Skills</span>
                    </summary>
                    <ul class="parent">
                        @foreach($data['treeData'] as $key => $value)
                            <li>
                                <summary onclick="actionLi('subCat')">
                                    <span class="parentSpan"><i class="mdi mdi-folder"></i> {{$key}}</span>
                                </summary>
                                @if(is_array($value))
                                    <ul class="subCat">
                                        @foreach($value as $ky => $val)
                                            @if($ky != "no_sub_category")
                                                <li>
                                                    <summary onclick="actionLi('subtitle')">
                                                        <span class="catSpan"><i class="mdi mdi-folder"></i> {{$ky}}</span>
                                                    </summary>
                                                    <ul class="subtitle">
                                                        @foreach($val as $k => $v)
                                                            <li class="lastNode" data-id="{{$v['id']}}">
                                                                <summary>
                                                                    <span ondblclick="dbclickLi({{$v['id']}})"><i class="mdi mdi-star-outline"></i> {{$v['title']}}</span>
                                                                </summary>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </li>
                                            @else
                                                @foreach($val as $k => $v)
                                                    <li class="lastNode" data-id="{{$v['id']}}">
                                                        <summary>
                                                            <span ondblclick="dbclickLi({{$v['id']}})"><i class="mdi mdi-star-outline"></i> {{$v['title']}}</span>
                                                        </summary>
                                                    </li>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            </ul>
        </div>
    </div>
    <!-- tree view   -->
</div>