@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">

        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Student Discipline Master</h4>            
            </div>                    
        </div>
        @if ($message = Session::get('data'))
            <div class="alert @if($message['status']!=1) alert-danger @else alert-success @endif alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message['message'] }}</strong>
            </div>
        @endif
        {{-- add button ends here  --}}
        @include('../front_desk.dicipline_master.addMaster')
        {{-- add button ends here  --}}
        <div class="card">

        {{-- table start here --}}
        <div class="table-responsive mt-2">
            <table class="table table-bordered" id="example">
                <thead>
                    <tr>
                        <th>Sr No</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th class="text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['addedData'] as $key => $diciplineMaster)
                    <tr>
                        <td>{{$key+1}}</td>
                        <td>{{$diciplineMaster['title']}}</td>
                        <td>{{$diciplineMaster['message']}}</td>
                        <td>
                            <div class="d-flex align-items-center justify-content-end">
                                <a class="btn btn-outline-success mr-1 editMasterBtn" 
                                   data-id="{{ $diciplineMaster['id'] }}" 
                                   data-message="{{ $diciplineMaster['message'] }}" 
                                   data-title="{{ $diciplineMaster['title'] }}" 
                                   data-titleid="{{ $diciplineMaster['dicipline_id'] }}"
                                   data-toggle="modal" 
                                   data-target="#titleModal">
                                    <i class="ti-pencil-alt"></i>
                                </a>
                                <form action="{{ route('dicipline-Master.destroy', $diciplineMaster['id'])}}" class="d-inline" method="post">
                                @csrf
                                @method('DELETE')
                                    <input type="hidden" name="formType" value="master">
                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
      
        </div>

    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')

<script>
  $(document).ready(function() {
    var table = $('#example').DataTable({
        select: true,
        lengthMenu: [
            [100, 500, 1000, -1],
            ['100', '500', '1000', 'Show All']
        ],
        dom: 'Bfrtip',
        buttons: [{
                extend: 'pdfHtml5',
                title: 'Discipline Master',
            },
            {
                extend: 'csv',
                text: ' CSV',
                title: 'Discipline Master'
            },
            {
                extend: 'excel',
                text: ' EXCEL',
                title: 'Discipline Master'
            },
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Discipline Master',
            },
            'pageLength'
        ],
    });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );

    $(document).ready(function () {
        $('.editBtn').on('click', function () {
            const id = $(this).data('id');
            const message = $(this).data('message');
            
            // Set form action to update route with the selected ID
            const form = $('#titleForm');
            form.attr('action', `{{ route('dicipline-Master.update', '') }}/${id}`);
            form.attr('method', 'POST');

            // Add hidden input for method spoofing
            let methodInput = form.find('input[name="_method"]');
            if (!methodInput.length) {
                methodInput = $('<input>', {
                    type: 'hidden',
                    name: '_method',
                    value: 'PUT'
                });
                form.append(methodInput);
            } else {
                methodInput.val('PUT');
            }

            // Autofill the title input
            $('#editTitle').val(message);
            $('.titleBtn').val('Update');

        });
    });
    $(document).on('click', '.editMasterBtn', function () {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const message = $(this).data('message');
        const titleId = $(this).data('titleid');

        // Set form action to update route with the selected ID
        const form = $('#editMasterForm');
        form.attr('action', `{{ route('dicipline-Master.update', '') }}/${id}`);
        form.attr('method', 'POST');

        // Add hidden input for method spoofing
        let methodInput = form.find('input[name="_method"]');
        if (!methodInput.length) {
            methodInput = $('<input>', {
                type: 'hidden',
                name: '_method',
                value: 'PUT'
            });
            form.append(methodInput);
        } else {
            methodInput.val('PUT');
        }

        // Autofill the form inputs
        console.log(titleId);
        $('#dicipline_id').val(titleId).prop('selected', true);
        $('#editMessage').val(message);
        $('.masterBtn').val('Update');

        // Open the modal
        $('#titleModal').modal('show');
    });
</script>
@endsection
