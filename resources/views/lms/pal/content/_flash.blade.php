{{-- Shared flash messages for the PAL Content Intelligence screens. --}}
@if(session('pal_message'))
    <div class="alert alert-success alert-block">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>{{ session('pal_message') }}</strong>
    </div>
@endif

@if(session('pal_error'))
    <div class="alert alert-danger alert-block">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>{{ session('pal_error') }}</strong>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-block">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <ul style="margin-bottom:0;padding-left:18px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
