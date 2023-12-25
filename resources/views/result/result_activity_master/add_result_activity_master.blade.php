@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Result Activity Master</h4>
            </div>
        </div>
        <div class="card">
		    <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($sessionData = Session::get('data'))
            @if($sessionData['status_code'] == 1)
            <div class="alert alert-success alert-block">
            @else
            <div class="alert alert-danger alert-block">
            @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('result_activity_master.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}                        
                    @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='title' name="title" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Skill Name</label>
                                <select id="skill_id" name="skill_id" class="form-control" required>
                                @php
                                    $uniqueTitles = [];
                                @endphp

                                @foreach ($data['result_skillsets'] as $item)
                                    @php
                                        $titleCombo = $item->main_title . '(' . $item->title . ')';
                                    @endphp

                                    @if (!in_array($titleCombo, $uniqueTitles))
                                        <option value="{{ $item->id }}" @if(isset($data['result_skillset']->main_title) && $data['result_skillset']->main_title == $item->main_title) selected @endif>
                                            {{ $titleCombo }}
                                        </option>
                                        @php
                                            $uniqueTitles[] = $titleCombo;
                                        @endphp
                                    @endif
                                @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' name="sort_order" class="form-control">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>    
        </div>
    </div>    
</div>

@include('includes.footerJs')
@include('includes.footer')
