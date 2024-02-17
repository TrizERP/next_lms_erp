<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Student Name</label>
        <input type="hidden" name="student_id" id="student_id" value="{{ $details->id }}">
        <label for="" class="form-control">{{ $details->full_name }}</label>
    </div>
</div>
<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Issue Date</label>
        <input type="text" class="form-control mydatepicker" name="issue_date" id="issue_date" value="{{ date('d-m-Y') }}">
    </div>
</div>
<div class="col-md-6 mt-3">
    <div class="form-group">
        <label for="">Due Date</label>
        <input type="text" class="form-control mydatepicker" name="return_date" id="return_date">
    </div>
</div>
<div class="col-md-12">
    <table class="table table-responsive">
        <thead>
            <th>Book</th>
            <th>Item Code</th>
            <th>Issue Date</th>            
            <th>Due Date</th>            
            <th>Return Date</th>
            <th>Action</th>
        </thead>
        <tbody>
            @foreach ($details->issuedBookItem as $item)
                @php 
                    $return_date = null;
                    if(isset($item->return_date) && $item->return_date != '0000-00-00 00:00:00'){
                        $return_date = \Carbon\Carbon::parse($item->return_date)->format('d-m-Y H:s:i');
                    }
                @endphp
                <tr>
                    <td>{{ $item->book->title ?? '' }}</td>
                    <td>{{ $item->item_code ?? '' }}</td>                    
                    <td>{{ \Carbon\Carbon::parse($item->issued_date)->format('d-m-Y') ?? '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($item->due_date)->format('d-m-Y') ?? '' }}</td>
                    <td>{{ $return_date }}</td>
                    <td>@if( $return_date == null )<button type="button" class="btn btn-danger return-book" data-id="{{ $item->main_id }}">Return</button>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<script>
    $(document).ready(function(){
        // Listen for change event on issue_date
        var selectedDate = $('#issue_date').val();
        
        get_date(selectedDate);

        $('#issue_date').change(function(){
            var selectedDate = $('#issue_date').val();
            // alert(selectedDate);
            // Get the selected date from issue_date input
            get_date(selectedDate);
        });

        // Date Picker
        jQuery('.mydatepicker, #datepicker').datepicker({
            changeMonth: true,
            changeYear: true,
            yearRange: "-40:+10",
            inline: true,
            autoclose: true,
            format: 'dd-mm-yyyy',
            orientation: 'bottom',
            forceParse: false
        });
        jQuery('#datepicker-autoclose').datepicker({
            autoclose: true,
            todayHighlight: true
        });
        jQuery('#date-range').datepicker({
            toggleActive: true
        });
        jQuery('#datepicker-inline').datepicker({
            todayHighlight: true
        });


    });

    // function get_date(selectedDate){
    //     if(selectedDate){
    //         var parsedDate = selectedDate.split("-").reverse().join("-");
    //         var returnDate = new Date(parsedDate);
            
    //         // Add 10 days to the returnDate
    //         returnDate.setDate(returnDate.getDate() + 10);

    //         // Format the return date as 'dd-mm-yyyy'
    //         var formattedReturnDate = returnDate.toLocaleDateString('en-GB');

    //         // Update the return_date input value
    //         $('#return_date').val(formattedReturnDate);
    //     }
    // }

     function get_date(selectedDate){
        if(selectedDate){
            // Split the selected date into day, month, and year
            var dateParts = selectedDate.split("-");
            var returnDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            
            // Check if the created Date object is valid
            if(!isNaN(returnDate.getTime())) {
                // Add 10 days to the returnDate
                returnDate.setDate(returnDate.getDate() + 10);

                // Format the return date as 'dd-mm-yyyy'
                var formattedReturnDate = returnDate.toLocaleDateString('en-GB');
                var formattedDate = formattedReturnDate.replace(/\//g, '-');

                // Update the return_date input value
                $('#return_date').val(formattedDate);
            } else {
                console.error("Invalid Date");
            }
        }
    }
</script>