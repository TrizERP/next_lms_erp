@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Template Master All Tags</h4> 
                </div>
            </div>        
            <div class="card">
                <h4>To make the template values dynamic,please use the following table as below.</h4>
                {{-- {!! $data !!} --}}
                <div class="table-responsive">
                    <table class="table table-stripped" id="example">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Tags</th>
                                <th class="text-left">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i=1 @endphp
                            @foreach ($data as $key=> $item)
                            <tr>
                                <td>{{$i++}}</td>
                                <td><b> << {{$key}} >> </b></td>
                                <td>{{$item}}</td>
                            </tr> 
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>       
    </div>
</div>

@include('includes.footerJs')

<script>
$(document).ready(function() {
    var table = $('#example').DataTable({
        select: true,
        lengthMenu: [
            [-1, 100, 500, 1000],
            ['Show All','100', '500', '1000']
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
</script>
@include('includes.footer')
