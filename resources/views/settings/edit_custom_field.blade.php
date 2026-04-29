@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit Custom Field</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route(isset($data['update_route']) ? $data['update_route'] : 'custom_fields.update', $data['field']->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Module Name</label>
                                <input type="text" class="form-control" value="{{ $data['field']->table_name }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Field Name</label>
                                <input type="text" class="form-control" value="{{ $data['field']->field_name }}" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Field Label *</label>
                                <input type="text" name="field_label" class="form-control" value="{{ $data['field']->field_label }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Field Type</label>
                                <input type="text" class="form-control" value="{{ $data['field']->field_type }}" readonly>
                                <input type="hidden" name="field_type" value="{{ $data['field']->field_type }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Field Message (Tooltip)</label>
                                <input type="text" name="field_message" class="form-control" value="{{ $data['field']->field_message }}">
                            </div>
                        </div>
                        <div class="col-md-6" id="file_size_container" style="{{ $data['field']->field_type === 'file' ? '' : 'display: none;' }}">
                            <div class="form-group">
                                <label>File Max Size (MB)</label>
                                <input type="number" name="file_size_max" class="form-control" value="{{ $data['field']->file_size_max }}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sort Order *</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ $data['field']->sort_order }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="required" value="1" class="form-check-input" id="required" {{ $data['field']->required == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="required">Required Field</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="common_to_all" value="1" class="form-check-input" id="common_to_all" {{ $data['field']->common_to_all == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="common_to_all">Common to All Institutes</label>
                            </div>
                        </div>
                    </div>

                    <!-- Options for checkbox and dropdown -->
                    @if(in_array($data['field']->field_type, ['checkbox', 'dropdown']))
                        <div id="options_container">
                            <h5>Options</h5>
                            <div id="options_list">
                                @foreach($data['options'] as $option)
                                    <div class="option-row row mt-2">
                                        <div class="col-md-5">
                                            <input type="text" name="display_name[]" class="form-control" placeholder="Display Text" value="{{ $option->display_text }}" required>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" name="f_value[]" class="form-control" placeholder="Value" value="{{ $option->display_value }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger remove-option"><i class="fa fa-minus"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="option-row row mt-2">
                                    <div class="col-md-5">
                                        <input type="text" name="display_name[]" class="form-control" placeholder="Display Text">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" name="f_value[]" class="form-control" placeholder="Value">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-success add-option"><i class="fa fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Update Field</button>
                            <a href="{{ route(isset($data['index_route']) ? $data['index_route'] : 'custom_fields.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script>
$(document).ready(function() {
    $(document).on('click', '.add-option', function() {
        var newRow = `
            <div class="option-row row mt-2">
                <div class="col-md-5">
                    <input type="text" name="display_name[]" class="form-control" placeholder="Display Text" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="f_value[]" class="form-control" placeholder="Value" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger remove-option"><i class="fa fa-minus"></i></button>
                </div>
            </div>
        `;
        $('#options_list').append(newRow);
    });

    $(document).on('click', '.remove-option', function() {
        $(this).closest('.option-row').remove();
    });
});
</script>

@include('includes.footer')