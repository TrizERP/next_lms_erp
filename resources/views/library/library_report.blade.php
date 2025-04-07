@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Library Report</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('show_library_report') }}" method="post">
                    {{ method_field("POST") }}
                    {{csrf_field()}}
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="report_of">Select Report</label>
                            <select name="report_of" id="report_of" class="form-control" required
                                    onchange="check_report(this.value);">
                                <option value="">Select Report</option>
                             
                               @foreach($data['report_list'] as $key=>$value)
                               <option value="{{$key}}" @if(isset($data['report']) && $data['report']==$key) selected @endif >{{$value}}</option>                               
                               @endforeach
                            </select>
                        </div>
                          
                        <div class="col-md-4 form-group" id="for_material_resource">
                            <label for="">Material Resource</label>
                            <select id="material_resource" class="form-control" name="material_resource"  onchange="makeShow('book_type',this.value)">
                                <option value="">All</option>
                                @foreach ($data['get_material_resource_type'] as $key => $value)
                                    @if (!empty($value->material_resource_type))
                                        <option value="{{ htmlspecialchars(strval($value->material_resource_type)) }}" @if(isset($data['material_resource']) && $data['material_resource']==htmlspecialchars(strval($value->material_resource_type)) ) selected @endif>{{ htmlspecialchars(strval($value->material_resource_type)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        @if(session()->get('sub_institute_id')==47)
                        <div class="col-md-4 form-group hide" id="book_type">
                            <label for="">Book Type</label>
                            <select id="book_type_input" class="form-control" name="book_type">
                                <option value="all">All</option>
                                <option value="purchase" @if(isset($data['book_type']) && $data['book_type']=="purchase") selected @endif>Purchase</option>
                                <option value="donate" @if(isset($data['book_type']) && $data['book_type']=="donate") selected @endif>Donate</option>
                            </select>
                        </div>
                        @endif
                        <div class="col-md-4 form-group" id="for_author">
                            <label for="">Author/Editor</label>
                            <select id="author" class="form-control" name="author">
                                <option value="">All</option>
                                @foreach ($data['get_author_name'] as $key => $value)
                                    @if (!empty($value->author_name))
                                        <option value="{{ htmlspecialchars(strval($value->author_name)) }}" @if(isset($data['author']) && $data['author']==htmlspecialchars(strval($value->author_name))) selected @endif>{{ htmlspecialchars(strval($value->author_name)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group" id="for_publisher_name">
                            <label for="">Publisher Name</label>
                            <select id="publisher_name" class="form-control" name="publisher_name">
                                <option value="">All</option>
                                @foreach ($data['get_publisher_name'] as $key => $value)
                                    @if (!empty($value->publisher_name))
                                        <option value="{{ htmlspecialchars(strval($value->publisher_name)) }}"  @if(isset($data['publisher_name']) && $data['publisher_name']==htmlspecialchars(strval($value->publisher_name))) selected @endif>{{ htmlspecialchars(strval($value->publisher_name)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group" id="for_publishing_place">
                            <label for="">Publishing Place</label>
                            <select id="publishing_place" class="form-control" name="publishing_place">
                                <option value="">All</option>
                                @foreach ($data['get_publish_place'] as $key => $value)
                                    @if (!empty($value->publish_place))
                                        <option value="{{ htmlspecialchars(strval($value->publish_place)) }}" @if(isset($data['publish_place']) && $data['publish_place']==htmlspecialchars(strval($value->publish_place))) selected @endif>{{ htmlspecialchars(strval($value->publish_place)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group" id="for_language">
                            <label for="">Language</label>
                            <select id="language" class="form-control" name="language">
                                <option value="">All</option>
                                @foreach ($data['get_language'] as $key => $value)
                                    @if (!empty($value->language))
                                        <option value="{{ htmlspecialchars(strval($value->language)) }}" @if(isset($data['language']) && $data['language']==htmlspecialchars(strval($value->language))) selected @endif>{{ htmlspecialchars(strval($value->language)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group" id="for_subject">
                            <label for="">Subject</label>
                            <select id="subject" class="form-control" name="subject">
                                <option value="">All</option>
                                @foreach ($data['get_subject'] as $key => $value)
                                    @if (!empty($value->subject))
                                        <option value="{{ htmlspecialchars(strval($value->subject)) }}"  @if(isset($data['subject']) && $data['subject']==htmlspecialchars(strval($value->subject))) selected @endif>{{ htmlspecialchars(strval($value->subject
                                            )) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>

                    </div>
                </form>
            </div>
            @if(!empty($data['all_data']))
            <div class="card">  
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">                       
                             <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Item Code</th>
                                    <th>Title</th>
                                    <th>Sub Title</th>
                                    <th>Material Resource Type</th>
                                    <th>Edition</th>
                                    <th>Tags</th>
                                    <th>No. of Items</th>
                                    <th>Author/Editor Name</th>
                                    <th>ISBN/ISSN</th>
                                    <th>Classification</th>
                                    <th>Publisher Name</th>
                                    <th>Publish Year</th>
                                    <th>Publishing Place</th>
                                    <th>Book Size/ No. page</th>
                                    <th>Series Title</th>
                                    <th>Call Number</th>
                                    <th>Language</th>
                                    <th>Source</th>
                                    <th>Subject</th>
                                    <th>Price</th>
                                    <th>Notes</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $number = 1; @endphp
                                @foreach($data['all_data'] as $key => $value)                                 
                                    <tr>
                                        <td>{{ $number++ }}</td>
                                        <td>{{$value->item_code}}</td>                                        
                                        <td>{{$value->title}}</td>
                                        <td>{{$value->sub_title}}</td>    
                                        <td>{{$value->material_resource_type}}</td>
                                        <td>{{$value->edition}}</td>
                                        <td>{{$value->tags}}</td>
                                        <td>{{$value->vol_no}}</td>
                                        <td>{{$value->author_name}}</td>
                                        <td>{{$value->isbn_issn}}</td>
                                        <td>{{$value->classification}}</td>
                                        <td>{{$value->publisher_name}}</td>
                                        <td>{{$value->publish_year}}</td>
                                        <td>{{$value->publish_place}}</td>
                                        <td>{{$value->pages}}</td>
                                        <td>{{$value->series_title}}</td>
                                        <td>{{$value->call_number}}</td>
                                        <td>{{$value->language}}</td>
                                        <td>{{$value->source}}</td>
                                        <td>{{$value->subject}}</td>
                                        <td>{{$value->price}}</td>
                                        <td>{{$value->notes}}</td>
                                        <td>{{$value->review}}</td>
                                    </tr>
                                @endforeach
                            </tbody>    
                        </table>
                    </div>     
                </div>
            </div> 
            @endif
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
<script>
    var ids = ['#for_material_resource','#for_author','#for_publisher_name','#for_publishing_place','#for_language','#for_subject'];
    @if(isset($data['report']) && $data['report']!='')
            var reportValue = '#for_'+'{!! $data["report"] !!}'; // Convert to string
          
            ids.forEach(element => {
                if(reportValue !== element){
                    $(element).hide();
                    var selectName = element.replace('#for_', '');
                    $('#'+selectName+' option').removeAttr('selected');
                }else{
                    $(element).show();
                }
       });
@else
    $(document).ready(function(){
       ids.forEach(element => {
           $(element).hide();
       });
   
    })
@endif

  function check_report(report_val) {
    var ids = ['#for_material_resource', '#for_author', '#for_publisher_name', '#for_publishing_place', '#for_language', '#for_subject'];
    var reportValue = '#for_'+report_val;
        ids.forEach(element => {
            if (reportValue !== element) {
                $(element).hide();
                var selectName = element.replace('#for_', '');
                // alert('#'+selectName+' option');
                $('#'+selectName+' option').removeAttr('selected');
            }else{
                $(element).show();
            }
        });
    }
    
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

    // 05-04-2025 
    @if(isset($data['book_type']) && in_array($data['book_type'],["purchase","donate"]))
        $('#book_type').removeClass('hide');
        $('#book_type').addClass('show');
     @endif
    function makeShow(divId,values){
        if(values=="book"){
            $('#'+divId).removeClass('hide');
            $('#'+divId).addClass('show');
        }else{
            $('#'+divId).addClass('hide');
            $('#'+divId).removeClass('show');
        }
    }
</script>
@include('includes.footer')
