@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Custom Fields</h4> </div>
            </div>       
            <div class="card">               
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code']==0)
                    <div class="alert alert-danger alert-block">
                    @else
                    <div class="alert alert-success alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <div class="row">
                        <div class="col-lg-3 col-sm-3 col-xs-3">
                            <a href="{{ route('add_fields.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Custom Field </a>
                        </div>
                        <br><br><br>
                        <div class="col-lg-12 col-sm-12 col-xs-12">
    <div class="table-responsive">
        <table id="example" class="table table-striped">
            <thead>
                <tr>
                    <th>Id</th>
                    <th>Module Name</th>
                    <th>Field Name</th>
                    <th>Field Label</th>
                    <th>Field Type</th>
                    <th>Sort Order</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="sortable-list">
            @php
            $j=1;
            $current_table = '';
            @endphp
                @foreach($data['data'] as $key => $field)
                    @if($current_table != $field->table_name)
                        @php $current_table = $field->table_name @endphp
                        <tr class="table-group">
                            <td colspan="8"><strong>{{ $field->table_name }}</strong></td>
                        </tr>
                    @endif
                <tr data-id="{{ $field->id }}" class="sortable-item">
                    <td>{{$j}}</td>
                    <td>{{$field->table_name}}</td>
                    <td>{{$field->field_name}}</td>
                    <td>{{$field->field_label}}</td>
                    <td>{{$field->field_type}}</td>
                    <td>{{$field->sort_order}}</td>
                    <td>
                        <a href="{{ route('add_fields.edit', $field->id) }}" class="btn btn-sm btn-primary">
                            <i class="fa fa-edit"></i> Edit
                        </a>
                        <form action="{{ route('add_fields.destroy', $field->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </form>
                        <span class="handle" style="cursor: move;"><i class="fa fa-bars"></i></span>
                    </td>
                </tr>
                @php
            $j++;
            @endphp
                @endforeach

            </tbody>

        </table>
    </div>
                        </div>
              
                    </div>
            </div>
      
    </div>
</div>

@include('includes.footerJs')

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    // Initialize sortable
    $("#sortable-list").sortable({
        handle: ".handle",
        update: function(event, ui) {
            var order = [];
            $("#sortable-list .sortable-item").each(function() {
                order.push($(this).data('id'));
            });

            $.ajax({
                url: '{{ route("add_fields.update_sort") }}',
                method: 'POST',
                data: {
                    order: order,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    // Optional: Show success message
                    console.log('Sort order updated');
                },
                error: function() {
                    alert('Error updating sort order');
                }
            });
        }
    });

    // DataTable removed to avoid conflicts with sortable drag & drop
});

</script>
@include('includes.footer')
