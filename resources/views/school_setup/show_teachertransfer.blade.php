@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Teacher Transfer Utility</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-block {{ $sessionData['class'] }}">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('teachertransfer.store') }}" method="post">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Left Teacher</label>
                        <select class="form-control" name="left_teacher_id" id="left_teacher_id" required>
                            <option value="">Select Left Teacher</option>
                            @if(isset($data['teacher_data']))
                                @foreach($data['teacher_data'] as $key =>$val)
                                    @php
                                        $selected = '';
                                        if( isset($data['left_teacher_id']) && $data['left_teacher_id'] == $val->id )
                                        {
                                            $selected = 'selected';
                                        }
                                    @endphp
                                    <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>New Teacher</label>
                        <select class="form-control" name="new_teacher_id" id="new_teacher_id" required>
                            <option value="">Select New Teacher</option>
                            @if(isset($data['teacher_data']))
                                @foreach($data['teacher_data'] as $key =>$val)
                                    @php
                                        $selected = '';
                                        if( isset($data['new_teacher_id']) && $data['new_teacher_id'] == $val->id )
                                        {
                                            $selected = 'selected';
                                        }
                                    @endphp
                                    <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 form-group mt-4">
                        <br>
                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.footerJs')
<!-- <script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#classteacher_list').DataTable({});
});

</script> -->
@include('includes.footer')
