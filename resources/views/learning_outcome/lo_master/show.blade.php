@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
                <div class="panel-body">

                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('lo_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                    </div>
                    <br><br><br>
                    <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                        <!--<table id="example" class="table table-striped border dataTable" style="width:100%">-->
                        <table id="example" class="table table-striped table-bordered dataTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Medium</th>
                                    <th>Standard</th>
                                    <th>Subject</th>
                                    <th>Learning Outcome</th>
                                    <th>Action</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $datas)
                                <tr id={{$datas->ID}}>    
                                    <td>{{$datas->SrNo}}</td>
                                    <td>{{$datas->MEDIUM}}</td>
                                    <td>{{$datas->STANDARD}}</td>
                                    <td>{{$datas->SUBJECT}}</td>
                                    <td>{{$datas->INDICATOR}}</td>
                                    <td>
                                        <a href="{{ route('lo_master.edit',$datas->ID)}}" class="btn btn-info btn-outline btn-circle btn m-r-5">
                                            <i class="ti-pencil-alt"></i>
                                        </a>
                                        <form action="{{ route('lo_master.destroy', $datas->ID)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-info btn-outline btn-circle btn m-r-5" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
                                        </form>
                                    </td>                 
                                    
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>

{{-- <script src="http://www.appelsiini.net/download/jquery.jeditable.mini.js"></script> --}}

<script>
                                                $(document).ready(function () {
                                                    var table = $('#example').DataTable({
                                                    });
                                                });

                                                // $(document).ready(function() {
 
//  $('#example').dataTable( {
//         "bPaginate": false,
//         "bStateSave": true
//     } );
 
    /* Init DataTables */
    // var oTable = $('#example').dataTable();

//     var table = $('#example').dataTable();

//     table.$('td').editable( 'ajaxServerScri', {
        
//      tooltip: "Click to edit.",
//      indicator: "Saving...",
//      onblur: "submit",
//      data: {
//         "id": "asd",
//                 "column": "asdas",
//         "row_id": "qweqwew"
//           },
     
//      intercept: function (jsondata) {
//          obj = jQuery.parseJSON(jsondata);
//          // do something with obj.status and obj.other
//          return(obj.result);
//      }
// } );
      

// data: function(value, settings) {
//           return $.fn.stripHTMLforAJAX(value);
//           },

// "callback": function( value, y ) {
//         table.cell( this ).data( value ).draw();
//         console.log("asdasd");
//     },
//     "submitdata": function ( value, settings ) {
//         return {
//             "row_id": $(this).closest('tr').attr('id'),
//             "column": table.cell( this ).index().column
//         };
//     }

    /* Apply the jEditable handlers to the table */
    // oTable.$('td').editable( 'get-subject-list.php', {
        
    //     "callback": function( sValue, y ) {
    //         alert("here");
    //         var aPos = oTable.fnGetPosition( this );
    //         oTable.fnUpdate( sValue, aPos[0], aPos[1] );
    //         window.location.reload();
    //     },
    //     "submitdata": function ( value, settings ) {
    //         alert("here");
    //         return {
    //             "row_id": this.parentNode.getAttribute('SrNo'),
    //             "column": oTable.fnGetPosition( this )[2]
    //         };
    //     },
    //     "height": "28px",
    //     "width": "100%"
    // } );
// } );

</script>
@include('includes.footer')
