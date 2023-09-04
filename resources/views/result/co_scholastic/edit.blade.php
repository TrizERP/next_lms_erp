@include('../includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">           
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('co_scholastic.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        {{csrf_field()}}

                        <div class="row">
                        
                            {{ App\Helpers\TermDD($data['term_id']) }}                        
                            <div class="col-md-4 form-group">
                             
                                <label>Standard: </label>                                
                                <select name="standard" class="form-control" required>
                                    @foreach ($data['standard'] as $id=>$arr)
                                   <option value={{$arr->id}} @if($arr->id == $data['standard_id']) selected @endif>{{$arr->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Co-Scholastic Title : </label>
                                <input type="text" name="title" value="{{ $data['title'] }}" class="form-control" />
                            </div>                            
                            <div class="col-md-4 form-group">
                                <label>Sort Order: </label>
                                <input type="text" name="sort_order" value="{{ $data['sort_order'] }}" class="form-control" />
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Parent Co-Scholastic: </label>
                                <select name="parent_id" class="form-control">
                                    <option value="">--Select Parent--</option>
                                    @php
                                    foreach ($data['ddValue'] as $id=>$arr){
                                    $selected = "";
                                    if($data['parent_id'] == $arr['id']){
                                    $selected = 'selected=selected';
                                    }
                                    echo "<option $selected value=$arr[id]>$arr[title]</option>";
                                    }
                                    @endphp
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Total Mark: </label>
                                <input type="text" name="max_mark" value="{{ $data['max_mark'] }}" class="form-control" />
                            </div>
                            <div class="col-md-4 form-group" style="margin-top: 34px;">
                                <label>Display Option: </label>
                                <input type="radio" name="mark_type" value="MARK" {{ ($data['mark_type']=="MARK")? "checked" : "" }} onchange='$("#grad_tbl").hide();' checked> Mark
                                <input type="radio" name="mark_type" value="GRADE" onchange='$("#grad_tbl").show();' {{ ($data['mark_type']=="GRADE")? "checked" : "" }}> Grade
                            </div>
                            @php
                            if($data['mark_type']=="MARK"){
                            $style = 'display:none;';
                            }else{
                            $style = '';
                            }
                            @endphp
                            <div class="col-md-12 form-group" id="grad_tbl" style="{{ $style }}">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <tr>
                                            <th>
                                                No.
                                            </th>
                                            <th>
                                                Grade Name
                                            </th>
                                            <th>
                                                Break Off
                                            </th>
                                        </tr>
                                        <tr>
                                            <td>
                                                1
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[1][title]" value="{{ isset($data['grd_data'][0]['title']) ? $data['grd_data'][0]['title'] : '' }}" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[1][break_off]" value="{{ isset($data['grd_data'][0]['break_off']) ? $data['grd_data'][0]['break_off'] : '' }}" class="form-control" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                2
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[2][title]" value="{{ isset($data['grd_data'][1]['title']) ? $data['grd_data'][1]['title'] : '' }}" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[2][break_off]" value="{{ isset($data['grd_data'][1]['break_off']) ? $data['grd_data'][1]['break_off'] : '' }}" class="form-control" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                3
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[3][title]" value="{{ isset($data['grd_data'][2]['title']) ? $data['grd_data'][2]['title'] : '' }}" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[3][break_off]" value="{{ isset($data['grd_data'][2]['break_off']) ? $data['grd_data'][2]['break_off'] : '' }}" class="form-control" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                4
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[4][title]" value="{{ isset($data['grd_data'][3]['title']) ? $data['grd_data'][3]['title'] : '' }}" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[4][break_off]" value="{{ isset($data['grd_data'][3]['break_off']) ? $data['grd_data'][3]['break_off'] : '' }}" class="form-control" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                5
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[5][title]" value="{{ isset($data['grd_data'][4]['title']) ? $data['grd_data'][4]['title'] : '' }}" class="form-control" />
                                            </td>
                                            <td>
                                                <input type="text" name="co_grade[5][break_off]" value="{{ isset($data['grd_data'][4]['break_off']) ? $data['grd_data'][4]['break_off'] : '' }}" class="form-control" />
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>

                    </form>
                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif            
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
