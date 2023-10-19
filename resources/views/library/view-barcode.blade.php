@foreach ($books as $item)
    <div class="form-group">
        <label for=""><b>Book Name:</b> {{ $item->title }}</label>
        <div class="m-3">{!! DNS1D::getBarcodeHTML($item->id, 'PHARMA') !!}</div>
    </div>
@endforeach
