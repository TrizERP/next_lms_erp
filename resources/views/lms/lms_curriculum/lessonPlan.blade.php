@extends('layout')
@section('container')
<div id="page-wrapper">
   <div class="container-fluid">

        <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status'] == 1)
                    <div class="alert alert-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif
            <form action="{{route('curriculum_lessonplan.index')}}">
                @csrf
                <div class="row">
                    <input type="hidden" value="1" name="search_data">
                    <div class="col-md-3 form-group">
                        <label for="teacher">Select Teacher</label>
                        <select name="teacher_id" id="teacher_id" class="form-control">
                            <option value="">Select Teacher</option>
                            @foreach($data['teachersList'] as $key => $value)
                            <option value="{{$value->id}}" @if(isset($data['teacher_id']) && $data['teacher_id']==$value->id) selected @endif>{{$value->full_name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="from_date">From Date</label>
                        <input type="text" class="form-control mydatepicker" name="from_date" id="from_date" placeholder="Select From Date" @if(isset($data['from_date'])) value="{{$data['from_date']}}" @endif>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="to_date">To Date</label>
                        <input type="text" class="form-control mydatepicker" name="to_date" id="to_date" placeholder="Select To Date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="teacher">Completion Status</label>
                        <select name="completion_status" id="completion_status" class="form-control">
                            <option value="">Select Completion Status</option>
                            @foreach($data['compeletion_status'] as $key => $value)
                            <option value="{{$value}}" @if(isset($data['completion_status']) && $data['completion_status']==$value) selected @endif>{{$value}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" value="Search" name="search" class="btn btn-primary">
                        </center>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(isset($data['searched_data']))
    <div class="card">
        <div class="row">
            <form action="{{route('curriculum_lessonplan.store')}}" onsubmit="return validateForm()" method="POST">
            @csrf
            <div class="table-responsive">
                <table id="example" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>SR No.</th>
                            <th>Teacher</th>
                            <th>Standard</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th class="text-left">Completion Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['searched_data'] as $key=>$value)
                        <tr>
                            <td><input type="checkbox" name="checkedValue[{{$value->id}}]" id="checkedValue" class="checkedValue" value="{{$value->id}}">&nbsp;{{$key+1}}</td>
                            <td>{{$value->teacher_name}}</td>
                            <td>{{$value->standard_name}}</td>
                            <td>{{$value->subject_name}}</td>
                            <td>{{ date('d-m-Y',strtotime($value->school_date)) }}</td>
                            <td>{{$value->title}}</td>
                            <td>{{$value->description}}</td>
                            <td> 
                                <label class="switch">
                                    <input type="checkbox" name="completion_status[{{$value->id}}]"  disabled="true" class="roundCheckbox check_status" @if($value->completion_status=="Yes") checked @endif value="Yes">
                                    <span class="slider round"></span>
                                </label>
                            </td>
                            <td>
                                <textarea name="reasons[{{$value->id}}]" id="reasons" class="resizableVertical check_text" rows="5" cols="50" placeholder="Add Reasons" disabled="true">{{$value->reasons}}</textarea>
                            </td>
                            <td>
                                <input type="text" class="form-control mydatepicker noFutureDate check_date" name="completeion_date[{{$value->id}}]" @if(isset($value->completion_date)) value="{{date('d-m-Y',strtotime($value->completion_date))}}" @endif placeholder="Add Completion Date" disabled="true">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="col-md-12 mt-4">
                <center>
                    <input type="submit" value="Save" name="submit" class="btn btn-success">
                </center>
            </div>
            </form>
        </div>
    </div>
    @endif
</div>
@include('includes.lmsfooterJs')
<script>
    $(function () {
        $(".checkedValue").on("click", function () {
            var row = $(this).closest('tr');
            var check_status = row.find('.check_status'); 
            var check_text = row.find('.check_text'); 
            var check_date = row.find('.check_date'); 
           
            check_status.prop('disabled', function (i, v) {
                return !v;
            });
            
            check_text.prop('disabled', function (i, v) {
                return !v;
            });
            
            check_date.prop('disabled', function (i, v) {
                return !v;
            });

        });

        $(".noFutureDate").on("change", function() {
            var selectedDate = $(this).datepicker("getDate");
            if (selectedDate > new Date()) {
                alert("Future dates are not allowed!");
                $(this).val('');
            }
        });

    });
    function validateForm() {
        var checkboxes = document.querySelectorAll('input[name^="checkedValue["]');
        var anyChecked = false;
        
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            }
        });
        
        if (!anyChecked) {
            alert("Please select at least one checkbox");
            return false; 
        }
        
        return true;
    }

</script>
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
                        title: 'Lesson Plan Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible',
                            orthogonal: 'export'
                        },
                    },
                    {
                        extend: 'csv', 
                        text: ' CSV',
                        title: 'Lesson Plan Report',  
                        exportOptions: {
                            columns: ':visible',
                            orthogonal: 'export'
                        }
                    },
                    {
                        extend: 'excel', 
                        text: ' EXCEL', 
                        title: 'Lesson Plan Report',
                        exportOptions: {
                            columns: ':visible',
                            orthogonal: 'export'
                        }
                    },
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Lesson Plan Report',
                        exportOptions: {
                            columns: ':visible',
                            orthogonal: 'export'
                        }
                    },
                    'pageLength'
                ],
                // for input value print
                columnDefs: [
                    {
                        targets: (function() {
                            // Find the index of the column containing checkboxes
                            var checkboxIndex = -1;
                            $('#example tbody td').each(function(index) {
                                if ($(this).find('.check_status').length > 0) {
                                    checkboxIndex = index;
                                    return false; // Exit loop once found
                                }
                            });
                            return checkboxIndex;
                        })(), // Self-invoking function to calculate targets
                        render: function(data, type, row, meta) {
                            if (type === 'export') {
                                var $input = $('<div>' + data + '</div>').find('.check_status');
                                return $input.is(':checked') ? 'Yes' : 'No';
                            }
                            return data;
                        }
                    },
                    {
                        targets: (function() {
                            // Find the index of the column containing checkboxes
                            var checkboxIndex = -1;
                            $('#example tbody td').each(function(index) {
                                if ($(this).find('.check_date').length > 0) {
                                    checkboxIndex = index;
                                    return false; // Exit loop once found
                                }
                            });
                            return checkboxIndex;
                        })(),
                        render: function(data, type, row) {
                            // Extract value from the input element for export
                            if (type === 'export' || type === 'print') {
                                var $input = $('<div>' + data + '</div>').find('.check_date');
                                return $input.val() || ''; // Return the value or empty if not set
                            }
                            return data; // For normal rendering
                        }
                    }
                ]
                
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
@endsection