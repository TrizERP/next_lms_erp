@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Student Discipline</h4> 
			</div>
		</div>        
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    @php
                    if(isset($data['stu_data'])){
                    @endphp
                    <form action="{{ route('dicipline.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="grade" value="<?php echo $data['grade']; ?>">
                        <input type="hidden" name="standard" value="<?php echo $data['standard']; ?>">
                        <input type="hidden" name="division" value="<?php echo $data['division']; ?>">
                        
                       
                         <div class="table-responsive ">
							<table id="example" class="table table-striped display">
							<thead>
								<tr>
									<th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
									<th>Sr No</th>
									<th>Student Name</th>
									<th>Standard</th>
									<th>Division</th>
									<th>Mobile</th>
									<th>Select</th>
									<th>Message</th>
								</tr>
							</thead>
                            @php

                            $arr = $data['stu_data'];
                            foreach ($arr as $id=>$col_arr){
                            @endphp
                            <tr>

                                <td><input type="checkbox" name="@php echo 'values[stud_id]['.$col_arr['student_id'].']'; @endphp" class="ckbox1">  </td>
                                <td>@php echo $id+1; @endphp</td>
                                <td>@php echo $col_arr['name']; @endphp</td>
                                <td>@php echo $col_arr['standard_name']; @endphp</td>
                                <td>@php echo $col_arr['division_name']; @endphp</td>
                                <td>@php echo $col_arr['mobile']; @endphp</td>
                                <td>
                                    <select name="@php echo 'values[dd]['.$col_arr['student_id'].']'; @endphp"
                                            class="form-control">
                                        <option value="">Select</option>
                                        <?php foreach ($data['dd'] as $id => $name) { ?>
                                            <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td>
                                    <textarea name="@php echo 'values[text]['.$col_arr['student_id'].']'; @endphp"
                                              class="form-control"></textarea>
                                </td>
                            </tr>
                            @php
                            }
                            @endphp
                        </table>
						</div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                    @php
                    }else{
                    echo "No Student Found.";
                    }
                    @endphp
                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>        
    </div>
</div>


@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>

<script>
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
<script>
$(document).ready(function() {
    var table = $('#example').DataTable( {
         select: true,          
         lengthMenu: [ 
            [100, 500, 1000, -1], 
            ['100', '500', '1000', 'Show All'] 
        ],
        }); 

        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
} );
</script>

@include('includes.footer')
