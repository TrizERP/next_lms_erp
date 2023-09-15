<style>
    h2 {
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
        background-color: #25bdea;
        border-color: #25bdea;
        border-radius: 0.25rem;
        color: white;
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
            
            <h2>Career Report: {{ $data['title'] }}</h2>
            
            <div class="card border-0 rounded mb-5">
                <div class="card-body">
                    <div class="d-md-flex align-items-center justify-content-between">
                        <p><strong>Code:</strong> {{ $data['code'] }}</p>
                        <p><strong>Title:</strong> {{ $data['title'] }}</p>
                        <p><strong>Also Called:</strong> {{ implode(', ', $data['also_called']['title']) }}</p>
                        <p><strong>What They Do:</strong> {{ $data['what_they_do'] }}</p>
                        <h3>On the Job</h3>
                        <ul>
                            @foreach ($data['on_the_job']['task'] as $task)
                                <li>{{ $task }}</li>
                            @endforeach
                        </ul>
                        <h3>Resources</h3>
                        <ul>
                            @foreach ($data['resources']['resource'] as $resource)
                                <li>
                                    <!-- <a href="{{ $resource['href'] }}" target="_blank">{{ $resource['title'] }}</a> -->
                                    <a class="directory4" href="{{ route('lmsIndustryListing.careersInIndustry.careerReport.resources', ['id' => $id, 'title' => $resource['title']]) }}">
                                        {{ $resource['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script type="text/javascript">
    function Preference(id, ele) {
        $("#" + id).val(ele.value);
    }

    function checkdata() {
        if ($("#first").val() == "" || $("#second").val() == "" || $("#third").val() == "" || $("#fourth").val() == "") {
            alert("Please Select Answer");
            return false;
        } else {
            return true;
        }
    }
</script>
