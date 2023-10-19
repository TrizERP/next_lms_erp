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
        <input type="date" class="form-control" name="issue_date" id="issue_date" value="{{ date('Y-m-d') }}">
    </div>
</div>
<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Return Date</label>
        <input type="date" class="form-control" name="return_date" id="return_date" value="">
    </div>
</div>
<div class="col-md-12">
    <table class="table table-responsive">
        <thead>
            <th>Book</th>
            <th>Issue Date</th>
            <th>Return Date</th>
            <th>Action</th>
        </thead>
        <tbody>
            @foreach ($details->issuedBook as $item)
                <tr>
                    <td>{{ $item->book->title ?? '' }}</td>
                    <td>{{ $item->issued_date ?? '' }}</td>
                    <td>{{ $item->return_date ?? '' }}</td>
                    <td><button type="button" class="btn btn-danger return-book" data-id="{{ $item->id }}">Return</button></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
