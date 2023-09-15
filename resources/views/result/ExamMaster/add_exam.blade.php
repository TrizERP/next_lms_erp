@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')


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
                        <form action="
                              @if (isset($data['Id']))
                              {{ route('exam_master.update', $data->Id) }}
                              @else
                              {{ route('exam_master.store') }}
                              @endif" enctype="multipart/form-data" method="post">

                            @if(!isset($data['Id']))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif

                            {{csrf_field()}}
                            
                            @php
                            $std_id = $term_id= $weightage = '';
                            if(isset($data['standard_id'])){
                                $std_id = $data['standard_id'];
                            }
                            if(isset($data['term_id'])){
                                $term_id = $data['term_id'];
                            }
                            if(isset($data['weightage'])){
                                $weightage = $data['weightage'];
                            }
                            @endphp
                            <div class="row">
                           
                              <div class="col-md-6 form-group">
                                  <label>{{ App\Helpers\get_string('standard','request')}}</label>
                                  <select class="form-control" name="all_standard[]" multiple required>
                                      <option value="">--Select {{ App\Helpers\get_string('standard','request')}}--</option>
                                        @if($data['all_standard'])
                                                @foreach($data['all_standard'] as $id=>$std)
                                                    <option value="{{$std->id}}" @if($std->id == $std_id) Selected @endif>{{$std->name}}</option>
                                                @endforeach
                                        @endif
                                  </select>
                              </div>
                              <div class="col-md-6 form-group">
                                  <label>Exam Type</label>
                                  <input type="text" id="ExamTitle" name="ExamTitle" value="{{ $data->ExamTitle ?? '' }}" class="form-control" required>
                              </div>
                              <div class="col-md-6 form-group">
                                  <label>Weightage</label>
                                  <input type="number" id="weightage" name="weightage" value="{{ $weightage ?? '' }}" class="form-control">
                              </div>
                              <div class="col-md-6 form-group">
                                  <label>Sort Order</label>
                                  <input type="number" id="SortOrder" required name="SortOrder" value="{{ isset($data->SortOrder) ? $data->SortOrder : $data['SortOrder'] }}" class="form-control">
                                  <input type="hidden" id="code" required name="Code" value="{{ isset($data->code) ? $data->code : $data['code'] }}" class="form-control">
                              </div>

                                <div class="col-md-6 form-group">
                                  <label>Term</label>
                                  <select class="form-control" name="all_term[]" multiple required>
                                      <option value="">--Select Term--</option>
                                        @if($data['all_term'])
                                                @foreach($data['all_term'] as $id=>$term)
                                                    <option value="{{$term->term_id}}"  @if($term->term_id == $term_id) Selected @endif>{{$term->title}}</option>
                                                @endforeach
                                        @endif
                                  </select>
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
