<style>
h2{
    margin-left: 5%;
    border-bottom: 3px solid #50c7ec;
    background: transparent !important;
    color: #333333 !important;
    font-size: 16px !important;
    border-radius: 0;
    margin-bottom: -2px;
    display: block;
    padding: 0.5rem 1rem;
    font-family: 'Josefin Sans', sans-serif;
    width: 88%;
}
.btn-primary {
    background-color:#25bdea;
    border-color: #25bdea;
    border-radius: 0.25rem;
    color:white;
}
.btn:not(.btn-sm) {
    outline: none;
    font-size: 14px;
    padding: 8px 10px;
}

</style>
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
            
            <h2>Industry Listing List</h2>
            
            <form id="online_exam" method="post" action="">
            {{ method_field('POST') }}
            @csrf            
            
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="chat" role="tabpanel" aria-labelledby="chat-tab">
                    <div class="card border-0 rounded mb-5">
                        <div class="card-body">
                            <div class="d-md-flex align-items-center justify-content-between">
                                <table align="center" width="96%" border="0" cellpadding="4" cellspacing="0">
                                    <tbody>
                                        <tr><br>
                                            <td valign="top">
                                            @if(isset($data['industry']))

                                            @foreach ($data['industry'] as $industry)
                                                <table align="center" width="95%" border="1" cellpadding="6" cellspacing="0">
                                                    <tbody>
                                                        <tr bgcolor="#ffffc6">
                                                            <td colspan="2" valign="top" width="100%">
                                                                <p> </p>
                                                                <!-- <p class="directory4"><b>{{ $industry['title'] }}</b></p> -->
                                                                <a class="directory4" href="{{ route('lmsIndustryListing.careersInIndustry',['id'=>$industry['code']])}}">
                                                                {{ $industry['title'] }}
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <br><br>
                                            @endforeach
                                            @else
                                                <p>No data available.</p>
                                            @endif
                                           
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script type="text/javascript">
function Preference(id,ele)
{
   $("#"+id).val(ele.value);    
}
function checkdata()
{
    if($("#first").val() == "" || $("#second").val() == "" || $("#third").val() == "" || $("#fourth").val() == "") 
    {
        alert("Please Select Answer");
        return false;
    }  
    else
    {
        return true;
    }
}
</script>
