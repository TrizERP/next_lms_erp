{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Circular</h4>
            </div>
        </div>
        <div class="card">
            <form action="{{ route('circular.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                {{csrf_field()}}

                <div class="row">
               
                    {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                    <div class="col-md-4 form-group">
                        <label>Date</label>
                        <input type="text" name="date_" class="form-control mydatepicker" autocomplete="off" required>
                    </div>
					<div class="col-md-4 form-group">
                        <label>Title</label>
                        <input type="text" name="title" id="title" class="form-control" autocomplete="off" required>
                        <!-- <input type="text" name="title" class="form-control"> -->
                    </div>
					<div class="col-md-4 form-group">
						<label>Type</label>
						<select name="type" class="form-control">
							@if(isset($data['circular_type']))
								@foreach($data['circular_type'] as $key => $val)
									<option value="{{$val->id}}">{{$val->type}}</option>
								@endforeach
							@endif
						</select>
					</div>
                    <div class="col-md-4 form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control"></textarea>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>File</label>
                        <input type="file" name="attachment[]" id="attachment[]" class="form-control" accept="image/*,application/pdf">
                        <span class="text-danger font-weight-bold">Note: Select single file from here.</span>
                    </div>

                     <div class="col-md-4 form-group">
					<label>Send To All Standard</label><br>			
					<input name="allstd" type="checkbox" @if(isset($data['allstd'])) checked @endif>
				</div>
                </div>
                
				<div class="col-md-12 form-group">
					<label></label><br>
					<center>
						<input type="submit" name="submit" value="Submit" class="btn btn-success">
					</center>
                </div>

            </form>
        </div>
        <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12">
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
                </div>
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr No</th>
                                <th>Syear</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Standard</th>
                                <th>Division</th>
                                <th>File</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['data']))
                            @foreach($data['data'] as $key => $data)
                            <tr>
                                <td>{{$j}}</td>
                                <td>{{$data->syear}}</td>
                                <td>{{$data->circular_type}}</td>
                                <td>{{$data->title}}</td>
                                <!--td>@php echo substr($data->message,0,50)."..."; @endphp</td-->
                                <td style="white-space: break-spaces;">{{$data->message}}</td>
                                <td>{{date('d-m-Y',strtotime($data->date_))}}</td>
                                <td>{{$data->std_name}}</td>
                                <td>{{$data->div_name}}</td>
                                <td>
                                @if(isset($data->file_name))
                                    <a href="{{ asset('storage/circular/' . $data->file_name)}}" target="_blank">View</a>
                                @else
                                -</td>
                                @endif
                                <td>
                                    <form action="{{ route('circular.destroy', $data->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" name="delete" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="mdi mdi-close"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @php
                            $j++;
                            @endphp
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<link href = "https://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<script src = "https://code.jquery.com/jquery-1.10.2.js"></script>
<script src = "https://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>

<script>
    $(document).ready(function () {

        $("#title").autocomplete({
          source: function( request, response )
          {
            $.ajax({
                url: "{{route('search_by_circular_title')}}",
                type: 'POST',
                data: {
                    'value': request.term
                },
                success: function(data){
                    response( $.map( data, function( item ) {
                        return {
                            label: item.title,
                            value: item.title
                        }
                    }));
                }
            });
          }
        });
    } );

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

</script>
@include('includes.footer')
@endsection
