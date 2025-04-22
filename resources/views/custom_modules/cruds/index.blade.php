@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">{{strtoupper($data['data']['module_name'])}}</h4>
            </div>
        </div>
        <div class="card">
             @if ($sessionData = Session::get('data'))
                <div class="alert @if($sessionData['status']==1) alert-success @else alert-danger @endif alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-3 col-xs-3 text-right">
                    <a href="{{ route('custom-module.tables') }}" class="btn btn-primary add-new"> Back </a>
                    <a href="{{ route('custom_module_crud.create', $data['data']['id']) }}"
                       class="btn btn-primary add-new"><i class="fa fa-plus"></i> Add Records </a>
                </div>

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                @foreach($data['data']['columns'] as $column)
                                    @if($column['column_name']!='syear')
                                    <th>{{ucfirst(str_replace('_',' ',$column['column_name']))}}</th>
                                    @endif
                                @endforeach
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                            @endphp
                            @foreach($data['data']['view'] as $key => $value)
                                <tr>
                                    @foreach($data['data']['columns'] as $column)
                                        @if(in_array($column['column_name'],['academic_section','grade']))
                                            @foreach($data['data']['academic_section'] as $academic_section)
                                                @if ($academic_section['id'] == $value[$column['column_name']])
                                                    <td>{{$academic_section['title']}}</td>
                                                @endif
                                            @endforeach
                                        @elseif(in_array($column['column_name'],['Division','division']))
                                            @foreach($data['data']['division'] as $division)
                                                @if ($division['id'] == $value[$column['column_name']])
                                                    <td>{{$division['name']}}</td>
                                                @endif
                                            @endforeach
                                        @elseif(in_array($column['column_name'],['Standard','standard']))
                                            @foreach($data['data']['standard'] as $standard)
                                                @if ($standard['id'] == $value[$column['column_name']])
                                                    <td>{{$standard['name']}}</td>
                                                @endif
                                            @endforeach
                                         @elseif(in_array($column['column_name'],['Term','term']))
                                            @foreach($data['data']['term'] as $term)
                                                @if ($term->term_id == $value->{$column['column_name']})
                                                    <td>{{$term->title}}</td>
                                                @endif
                                            @endforeach
                                        @elseif ($column['column_name'] == 'image')

                                            <td><a href="{{asset('images/'.$value[$column['column_name']])}}"
                                                   target="_blank">link</a>
                                            </td>
                                        @elseif($column['column_name'] != 'syear')
                                            <td>{{$value[$column['column_name']]}}</td>
                                        @endif
                                    @endforeach
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ url('custom-module/create-view/' . $data['data']['id'] . '/update/' . $value->id) }}"
                                               class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form class="d-inline"
                                              action="{{ route('custom_module_crud.delete', $value->id)}}"
                                              method="post">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" value="{{$data['data']['table_name']}}"
                                                   name="table_name">
                                            <input type="hidden" value="{{$data['data']['id']}}" name="view_id">
                                            <button type="submit" class="btn btn-info btn-outline-danger"><i
                                                    class="ti-trash"></i></button>
                                        </form>
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

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
 $(document).ready(function () {
            var table = $('#example').DataTable({
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: '{{strtoupper($data['data']['module_name'])}} report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: '{{strtoupper($data['data']['module_name'])}} report'},
                    {extend: 'excel', text: ' EXCEL', title: '{{strtoupper($data['data']['module_name'])}} report'},
                    {extend: 'print', text: ' PRINT', title: '{{strtoupper($data['data']['module_name'])}} report'},
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

            $('#example thead tr').clone(true).appendTo('#example thead');
            $('#example thead tr:eq(1) th').each(function (i) {
                var title = $(this).text();
                $(this).html('<input type="text" placeholder="Search ' + title + '" />');

                $('input', this).on('keyup change', function () {
                    if (table.column(i).search() !== this.value) {
                        table
                            .column(i)
                            .search(this.value)
                            .draw();
                    }
                });
            });
        });
</script>
@include('includes.footer')
