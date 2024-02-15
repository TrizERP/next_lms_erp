<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Student Name</label>
        <input type="hidden" name="student_id" id="student_id" value="{{ $details->id }}">
        <label for="" class="form-control">{{ $details->full_name }}</label>
    </div>
</div>
<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Issue Date</label>
        <input type="text" class="form-control mydatepicker" name="issue_date" id="issue_date" value="{{ date('d-m-Y') }}">
    </div>
</div>
<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Return Date</label>
        <input type="text" class="form-control mydatepicker" name="return_date" id="return_date" value="{{ date('d-m-Y') }}">
    </div>
</div>
<div class="col-md-12">
    <table class="table table-responsive">
        <thead>
            <th>Book</th>
            <th>Item Code</th>
            <th>Issue Date</th>            
            <th>Due Date</th>            
            <th>Return Date</th>
            <th>Action</th>
        </thead>
        <tbody>
            @foreach ($details->issuedBookItem as $item)
                @php 
                    $return_date = null;
                    if(isset($item->return_date) && $item->return_date != '0000-00-00 00:00:00'){
                        $return_date = \Carbon\Carbon::parse($item->return_date)->format('d-m-Y');
                    }
                @endphp
                <tr>
                    <td>{{ $item->book->title ?? '' }}</td>
                    <td>{{ $item->item_code ?? '' }}</td>                    
                    <td>{{ \Carbon\Carbon::parse($item->issued_date)->format('d-m-Y') ?? '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($item->due_date)->format('d-m-Y') ?? '' }}</td>
                    <td>{{ $return_date }}</td>
                    <td>@if( $return_date == null )<button type="button" class="btn btn-danger return-book" data-id="{{ $item->main_id }}">Return</button>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
