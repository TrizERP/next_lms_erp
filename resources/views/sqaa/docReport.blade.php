@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">SQAA Document Report</h4>
			</div>
		</div>
        <div class="card">
            <form action="{{route('sqaa_document_report.index')}}" method="get" class="row">
                @csrf 
                <div class="col-md-4 form-group">
                    <label for="">Availability</label>
                    <select name="selAvail" id="" class="form-control">
                        <option value="Yes" @if(isset($data['selAvail']) && $data['selAvail']=="Yes") Selected @endif>Yes</option>
                        <option value="No" @if(isset($data['selAvail']) && $data['selAvail']=="No") Selected @endif>No</option>
                        <option value="all" @if(isset($data['selAvail']) && $data['selAvail']=="all") Selected @endif>All Documents</option>
                    </select>
                </div>
					<div class="col-md-4 form-group" id="level_1_div">
						<label>Select Level 1</label>
                        <select name="level_1" id="level_1" class="form-control">
                        <option value=''>--Select Level 1--</option>                        
                        @foreach($data['level_1'] as $key => $value)
                        <option value="{{$value['id']}}" @if(isset($data['selected_1']) && $data['selected_1']==$value['id']) selected @endif>{{$value['title']}}</option>
                        @endforeach
                        </select>
					</div>
                    @if(isset($data['selected_1']))
                    <div class="col-md-4 form-group" id="level_2_div">
						<label>Select Level 2</label>
                        <select name="level_2_sel" id="level_2_sel" class="form-control">
                        <option value=''>--Select Level 2--</option>                        
                        @foreach($data['level_2_val'] as $key => $value)
                        <option value="{{$value['id']}}" @if(isset($data['selected_2']) && $data['selected_2']==$value['id']) selected @endif>{{$value['title']}}</option>
                        @endforeach
                        </select>
					</div>
                    @endif

                    @if(isset($data['selected_2']))
                    <div class="col-md-4 form-group" id="level_3_div">
						<label>Select Level 3</label>
                        <select name="level_3_sel" id="level_3_sel" class="form-control">
                        <option value=''>--Select Level 3--</option>                        
                        @foreach($data['level_3_val'] as $key => $value)
                        <option value="{{$value['id']}}" @if(isset($data['selected_3']) && $data['selected_3']==$value['id']) selected @endif>{{$value['title']}}</option>
                        @endforeach
                        </select>
					</div>
                    @endif

                    @if(isset($data['selected_3']))
                    <div class="col-md-4 form-group" id="level_4_div">
						<label>Select Level 4</label>
                        <select name="level_4_sel" id="level_4_sel" class="form-control">
                        <option value=''>--Select Level 4--</option>                        
                        @foreach($data['level_4_val'] as $key => $value)
                        <option value="{{$value['id']}}" @if(isset($data['selected_4']) && $data['selected_4']==$value['id']) selected @endif>{{$value['title']}}</option>
                        @endforeach
                        </select>
					</div>
                    @endif
					<div class="col-md-12 form-group">
						<center>
							<input type="submit" name="submit" value="Search" class="btn btn-success">
						</center>
					</div>

            </form>
        </div>
		
		<div class="card">
			<div class="row">
				<div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="table-responsive">
						<table id="example" class="table">
							<thead>
								<tr>
									<th><b>S. No.</b></th>
									<th><b>SQAA Title</b></th>
									<th><b>Document title</b></th>
                                    <th><b>Availability</b></th>
                                    <th class="text-left"><b>File</b></th>
								</tr>
							</thead>
							<tbody>
                                @foreach($data['docData'] as $key=> $value)
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value->menuTitle}}</td>
                                    <td>{{$value->title}}</td>
                                    <td>{{$value->availability}}</td>
                                    <td><a href="https://s3-triz.fra1.digitaloceanspaces.com/public/sqaa/{{$value->file}}" target="_blank">{{$value->file}}</a></td>
                                </tr>
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
<script>

 $(document).ready(function () {
            var table = $('#example').DataTable({
                ordering: false,
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Student Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Student Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
                    {extend: 'print', text: ' PRINT', title: 'Student Report'},
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
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );

    $(document).on('change','#level_1',function(){
    var level_1 = $(this).val();
    // Clear existing level_2 options
    $('#level_2_div').remove();    
    $('#level_3_div').remove();        
    $('#level_4_div').remove();    
    $.ajax({
        type: 'GET',
        url: '/get-level', 
        data: { level_2: level_1 }, 
        success: function (data) {
        var level_2_select_container = $('#level_2_div');
        var level_2_select = $('#level_2_sel');

            if (Array.isArray(data) && data.length > 0) {
                if (level_2_select_container.length === 0) {
                    level_2_select_container = $('<div class="col-md-4 form-group" id="level_2_div"></div>');
                    $('#level_1_div').after(level_2_select_container);
                    var level_2_select_label = $('<label for="level_2_sel">Select Level 2</label>');
                    level_2_select = $('<select id="level_2_sel" class="form-control" name="level_2_sel"></select>');
                    var defaultOption = '<option value="">--Select Level 2--</option>';
                    level_2_select.append(defaultOption);

                    level_2_select_container.append(level_2_select_label);
                    level_2_select_container.append(level_2_select);
                }

                // Populate the level_2 options
                data.forEach(function (value) {
                    var option = '<option value="' + value.id + '">' + value.title + '</option>';
                    level_2_select.append(option);
                });
            }
        }
    });
});


$(document).on('change','#level_2_sel',function(){
    var level_2 = $(this).val();
    $('#level_3_div').remove();        
    $('#level_4_div').remove();    
   
    $.ajax({
        type: 'GET',
        url: '/get-level', 
        data: { level_3: level_2 }, 
        success: function (data) {
        var level_3_select_container = $('#level_3_div');
        var level_3_select = $('#level_3_sel');

            if (Array.isArray(data) && data.length > 0) {
                if (level_3_select_container.length === 0) {
                    level_3_select_container = $('<div class="col-md-4 form-group" id="level_3_div"></div>');
                    $('#level_2_div').after(level_3_select_container);
                    var level_3_select_label = $('<label for="level_3_sel">Select Level 3</label>');
                    level_3_select = $('<select id="level_3_sel" class="form-control" name="level_3_sel"></select>');
                    var defaultOption = '<option value="">--Select Level 3--</option>';
                    level_3_select.append(defaultOption);

                    level_3_select_container.append(level_3_select_label);
                    level_3_select_container.append(level_3_select);
                }

                // Populate the level_3 options
                data.forEach(function (value) {
                    var option = '<option value="' + value.id + '">' + value.title + '</option>';
                    level_3_select.append(option);
                });
            }
        }
    });

});


$(document).on('change','#level_3_sel',function(){
    var level_3 = $(this).val();
    $('#level_4_div').remove();    
    
    $.ajax({
        type: 'GET',
        url: '/get-level', 
        data: { level_4: level_3 }, 
        success: function (data) {
        var level_4_select_container = $('#level_4_div');
        var level_4_select = $('#level_4_sel');

            if (Array.isArray(data) && data.length > 0) {
                if (level_4_select_container.length === 0) {
                    level_4_select_container = $('<div class="col-md-4 form-group" id="level_4_div"></div>');
                    $('#level_3_div').after(level_4_select_container);
                    var level_4_select_label = $('<label for="level_4_sel">Select Level 4</label>');
                    level_4_select = $('<select id="level_4_sel" class="form-control" name="level_4_sel"></select>');
                    var defaultOption = '<option value="">--Select Level 4--</option>';
                    level_4_select.append(defaultOption);

                    level_4_select_container.append(level_4_select_label);
                    level_4_select_container.append(level_4_select);
                }

                // Populate the level_2 options
                data.forEach(function (value) {
                    var option = '<option value="' + value.id + '">' + value.title + '</option>';
                    level_4_select.append(option);
                });
            }
        }
    });

});

</script>
@include('includes.footer')