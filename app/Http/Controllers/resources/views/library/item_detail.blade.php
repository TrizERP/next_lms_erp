<div class="px-4">
    <div class="row">
        <div class="col-md-6 mt-3">
            <div class="form-group">
                <label for="">Book Title</label>
                <input type="hidden" name="item_id" id="item_id" value="{{ $book->id }}">
                <label for="" class="form-control" style="height:fit-content">{{ $book->title }}</label>                
            </div>
        </div>
        <div class="col-md-6 mt-3">
            <div class="form-group">
                <label for="">Auther Name</label>
                <label for="" class="form-control">{{ $book->author_name }}</label>
            </div>
        </div>
    </div>
    <div class="mb-6">
        <table class="table table-responsive">
            <thead>
                <th>Item Code</th>
                <th>Order No</th>
                <th>Call Number</th>
                <!-- <th>Action</th> -->
            </thead>
            <tbody>
                @forelse($book->items as $item)
                <tr>
                    <td>{{ $item->item_code ?? '' }}</td>
                    <td>{{ $item->order_no ?? '' }}</td>
                    <td>{{ $item->call_number ?? '' }}</td>
                   {{--<td><button type="button" class="btn btn-danger delete-item" data-id="{{ $item->id }}">Delete</button></td> --}} 
                </tr>
                @empty
                <tr>
                    <td>No book items found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>