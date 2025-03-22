@extends('layout') @section('container')
<div id="page-wrapper" style="color:#000;">
	<div class="container-fluid">

		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Print barcode</h4>
			</div>
		</div>
		<!-- search card start  -->
		<div class="card">
			@if(!empty($data['message'])) @if(!empty($data['status_code']) && $data['status_code'] == 1)
			<div class="alert alert-success alert-block">
				@else
				<div class="alert alert-danger alert-block">
					@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $data['message'] }}</strong>
				</div>
			</div>
            @endif
            @php 
                $print_types=['member'=>'Member Id','tem_code'=>'Item Code'];
            @endphp
            <form action="{{route('print_barcode.create')}}" method="post">
            <div class="row">            
                <div class="col-md-3 form-group">
                    <label>Search By</label>
                   <select name="print_type" id="print_type" class="form-control" required>
                   @foreach($print_types as $key=>$value)
                    <option value="{{$key}}" @if(isset($data['print_type']) && $data['print_type']==$key) selected @endif>{{$value}}</option>
                   @endforeach
                   </select>
                </div> 

                <div class="col-md-3 form-group">
                    <label>Search</label>
                    <input type="text" id="search_by" placeholder="Search.." name="search_by" @if(isset($data['search_by'])) value="{{$data['search_by']}}" @endif>
                </div>    

                <div class="col-md-3">
                <input type="submit" value="Search" class="btn btn-success">
                </div>

            </div>                    
         
            </form>        
		</div>
		<!-- search card end  -->

        <!-- data card start  -->
        @if(isset($data['details']))
        <div class="card">
        <form action="{{route('generateBarcodePdf')}}" method="post" target="_blank">
        @csrf 
        <input type="hidden" name="print_type" value="{{$data['print_type']}}">
			<div class="table-responsive">
				<table id="example" class="table table-box table-bordered">
					<thead>
                    @php
                    if($data['print_type']=="member"){
                     $theads = ["Member Id", "Roll No", "Student Name", App\Helpers\get_string('std/div')];

                    }else{
                        $theads = ["Item Code","Title","Classification"];
                    }
                    @endphp

                    <tr>
                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                    @foreach($theads as $key=>$value)
                    <th class="text-left">{{$value}}</th>
                    @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['details'] as $key=>$value)                    
                        @if($data['print_type']=="member")
                            <tr>
                                <td><input id="{{$value->enrollment_no}}" value="{{$value->enrollment_no}}" name="check_id[]" type="checkbox"><input type="hidden" name="print_text[]" value="{{$value->student_name}}"></td>                            
                                <td>{{$value->enrollment_no}}</td>
                                <td>{{$value->roll_no}}</td>
                                <td>{{$value->student_name}}</td>
                                <td>{{$value->standard.'/'.$value->division}}</td>
                            </tr>
                        @else
                        @php
                            $itemCodeId = $value->item_code;
                        @endphp     
                        <tr>
                                <td><input id="{{$itemCodeId}}" value="{{$itemCodeId}}" name="check_id[]" type="checkbox">
                                <input type="hidden" name="print_text[]" value="{{$value->book_title}}">
                                <input type="hidden" name="print_code[]" value="{{$value->classification}}">
                                </td>                            
                                <td>{{$value->item_code}}</td>
                                <td>{{$value->book_title}}</td>
                                <td>{{$value->classification}}</td>                                
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="row">
                <div class="col-md-12">
                <center><input type="submit" value="Print Barcode PDF" class="btn btn-primary" onclick="check_validation()"></center>
            </div>
            </div>
            </form>
        </div>
        @endif
        <!-- data card ends  -->

<!-- end container-->
    </div>
</div>

@include('includes.footer') @include('includes.footerJs')
<script>
	 $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [24,100, 500, 1000, -1],
                ['24','100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
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

    	function checkAll(ele) {
			var checkboxes = document.getElementsByTagName('input');
			if (ele.checked) {
				for (var i = 0; i < checkboxes.length; i++) {
					if (checkboxes[i].type == 'checkbox') {
						checkboxes[i].checked = true;
					}
				}
			} else {
				for (var i = 0; i < checkboxes.length; i++) {
					console.log(i)
					if (checkboxes[i].type == 'checkbox') {
						checkboxes[i].checked = false;
					}
				}
			}
		}

     
	function check_validation() {
		var checked_questions = err = 0;

		$("input[name='check_id[]']:checked").each(function() {
		checked_questions = checked_questions + 1;
		});

		if (checked_questions == 0) {
		alert("Please Select Atleast one Student from search");
		err = 1;
		}

		if (err == 1) {
		event.preventDefault();
		}

		return err;
	}
</script>
@endsection
