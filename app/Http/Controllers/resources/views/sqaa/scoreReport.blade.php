@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
<style>
thead>tr>th{
    font-weight:bold;
    text-align:center !important;
}
#example-1, #example-1 th{
    border :1px solid #ffb64d !important;
}
#example-1 td{
    border :none !important;
    border-bottom :1px solid #ffb64d !important;
    text-align:center;
}
#example-1 th{
    background : #ffb64d;
}
</style>
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">SQAA Score Report</h4>
			</div>
		</div>
		@if(isset($data['level_1']) && !empty($data['level_1']))
        @php 
        $tot_mark=[1=>104,2=>80,3=>40,4=>28,5=>40,6=>20,7=>24];
        $Weightage=[1=>40,2=>10,3=>10,4=>10,5=>10,6=>10,7=>10];                            
        @endphp
		<div class="card">
			<div class="row">
				<div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="table-responsive">
						<table id="example" class="table">
							<thead style="text-align:left">
								<tr>
									<th><b>S. No.</b></th>
									<th><b>Domains/Sub-domains</b></th>
									<th><b>Score</b></th>
                                    <th><b>Remarks</b></th>
								</tr>
							</thead>
							<tbody>
                            @php 
                            $i = 1;
                            $level_1_tot = [];
                            $no_std = [];
                             @endphp 
								@foreach ($data['level_1'] as $key=>$item)
								<tr>
									<td><b>{{ $i++ }}</b></td>                                    
									<td><b>{{ $item['title'] }}</b></td> 
                                    <td></td>
                                    <td></td>                                   
								</tr>
                                @php 
                                $j = 1;
                                $level_1_tot[$item['id']] = 0;
                                $no_std[$item['id']] = 0;
                                @endphp 
                                @if(isset($data['level_2'][$item['id']]))
                                @foreach ($data['level_2'][$item['id']] as $key=>$item_2)
                                <tr>
									<td>{{ $item_2['parent_id'].'.'.$j++ }}</td>                                    
									<td><b>{{ $item_2['title'] }}<b></td> 
                                    <td></td>
                                    <td></td>                                   
								</tr>
                                @if(isset($data['level_3'][$item_2['id']]))
                                @php 
                                $no_std[$item['id']] += count($data['level_3'][$item_2['id']]) ?? 0;
                                @endphp
                                @foreach ($data['level_3'][$item_2['id']] as $key=>$item_3)
                                <tr>
									<td></td>                                    
									<td>{{ $item_3['title'].'-'.$item_3['id'] }}</td> 
                                    <td>{{$data['level_4'][$item_3['id']] ?? 0}}</td>
                                    <td></td>                                   
								</tr>
                                @php
                                $level_1_tot[$item['id']] += $data['level_4'][$item_3['id']] ?? 0;
                                @endphp 
								@endforeach
                                @endif
								@endforeach
                                @endif
                                <tr>
                                <td><b>Total</b></td>
                                <td><div style="display:flex"><div style="border-right:1px solid black;padding-right:20px"><b>Score Obtained - x<br>{{$level_1_tot[$item['id']]}}</b></div><div style="padding-left:20px"><b>Maximum Marks - 104<br>{{$tot_mark[$item['id']]}}</b></div></div></td>
                                @php 
                                if($level_1_tot[$item['id']] != 0){
                                   $tot_obt_mark =  round(($level_1_tot[$item['id']] * $Weightage[$item['id']] ) /$tot_mark[$item['id']],2);
                                }else{
                                    $tot_obt_mark =0;
                                }
                                @endphp 
                                <td colspan=2><b>(x × {{$Weightage[$item['id']]}}/{{$tot_mark[$item['id']]}}) = {{$tot_obt_mark}}</b></td>
                                </tr>
								@endforeach
							</tbody>
						</table>
					</div>
                    <div class="table-responsive" style="padding:30px">
						<table id="example-1" class="table">
							<thead>
								<tr>
									<th><b>S. No.</b></th>
									<th><b>Domains</b></th>
									<th><b>No. of Standards</b></th>
                                    <th><b>Total Score</b></th>
                                    <th><b>Weightage Assigned</b></th>
                                    <th><b>Weightage Score <br>Obtained**(%)</b></th>
								</tr>
							</thead>
							<tbody>
                            @php 
                            $i = 1;
                             @endphp 
								@foreach ($data['level_1'] as $key=>$item)
                                <tr>
									<td>{{ $i++ }}</td>                                    
									<td>{{ $item['title'] }}</td> 
                                    <td><b>{{ $no_std[$item['id']] ?? 0 }}</b></td>
                                    <td><b>{{$tot_mark[$item['id']]}}</b></td> 
                                    <td><b>{{$Weightage[$item['id']] }}</b></td>   
                                    <td><b>{{$level_1_tot[$item['id']]}}</b></td>                               
								</tr>
                                @endforeach
                                @php 
                                $tot_no_std = array_values($no_std);
                                $tot_no_std = array_sum($tot_no_std);
                                
                                $tot_tot_mark = array_values($tot_mark);
                                $tot_tot_mark = array_sum($tot_tot_mark);
                                
                                $tot_Weightage = array_values($Weightage);
                                $tot_Weightage = array_sum($tot_Weightage);

                                $tot_level_1 = array_values($level_1_tot);
                                $tot_level_1 = array_sum($tot_level_1);
                                @endphp
                                <tr>
                                    <td colspan="2" align="center"><b>Total Score Obtained in 84<br>Standards<br>(out of 336 Marks)</b></td>
									<td><b>{{ $tot_no_std }}</b></td>                                    
									<td><b>{{ $tot_tot_mark }}</b></td>                                    
									<td><b>{{ $tot_Weightage }}</b></td>                                    
									<td><b>{{ $tot_level_1 }}</b></td>                                                                        
                                </tr>
                            </tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		@endif
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
</script>
@include('includes.footer')