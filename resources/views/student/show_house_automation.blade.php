@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student House Automation</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-block {{ $sessionData['class'] }}">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('house_automation.store') }}" method="post">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Standard</label>
                        <select class="form-control" name="standard_id" id="standard_id" required>
                            <option value="">Select Standard</option>
                            @if(isset($data['standard_data']))
                                @foreach($data['standard_data'] as $key =>$val)
                                    @php
                                        $selected = '';
                                        if( isset($data['standard_id']) && $data['standard_id'] == $val->id )
                                        {
                                            $selected = 'selected';
                                        }
                                    @endphp
                                    <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 form-group mt-4 ml-0 mr-0">
                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.footerJs')
@include('includes.footer')
