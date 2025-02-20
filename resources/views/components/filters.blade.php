<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
 
            <!-- <div class="me-3" style="flex: 1;">
                <label for="sub_institute_id">Institute</label>
                <select id="sub_institute_id" name="sub_institute_id" class="form-control" style="width: 100%;">
                    <option value="">Select Institute</option>
                    @foreach($institutes as $instituteId)
                        <option value="{{ $instituteId }}">{{ $instituteId }}</option>
                    @endforeach
                </select>
            </div> -->

            <div class="me-3" style="flex: 1;">
                <label for="from">From</label>
                <input type="date" id="from" name="from" class="form-control" style="width: 100%;" />
            </div>

            <div class="me-3" style="flex: 1;">
                <label for="to">To</label>
                <input type="date" id="to" name="to" class="form-control" style="width: 100%;" />
            </div>

            <div class="me-3" style="flex: 1;">
                <label for="reportType">Report Type</label>
                <select id="reportType" name="reportType" class="form-control" style="width: 100%;">
                    <option value="">Select Report Type</option>
                    @foreach($reportTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="me-3" style="flex: 1; display: none;" id="xFieldsContainer">
            <label for="xField">X Fields</label>
            <select id="xField" name="xField" class="form-control" style="width: 100%;">
                <option value="">Select X Field</option>
            </select>
        </div>

        <div class="me-3" style="flex: 1; display: none;" id="yFieldsContainer">
            <label for="yField">Y Fields</label>
            <select id="yField" name="yField" class="form-control" style="width: 100%;">
                <option value="">Select Y Field</option>
            </select>
        </div>
         </div>
         <div class="d-flex justify-content-start" style="margin-top: 10px;">
            <div style="flex: 0 0 auto;">
                <input type="button" class="btn btn-success" value="Filter" onclick="getData()" style="width: 80px;" />
            </div>

            <div style="flex: 0 0 auto; margin-left: 10px;">
                <input type="button" class="btn btn-success" value="Download Report" onclick="downloadReport()" style="width: 180px;" />
            </div>
        </div>
    </div>
</div>


<script>
document.getElementById("reportType").addEventListener("change", function () {
    let reportType = this.value;
    let xFieldsDropdown = document.getElementById("xField");
    let yFieldsDropdown = document.getElementById("yField");
    let xFieldsContainer = document.getElementById("xFieldsContainer");
    let yFieldsContainer = document.getElementById("yFieldsContainer");


    if (reportType) {
        fetch(`/get-fields?report_type=${reportType}`)
            .then(response => response.json())
            .then(data => {
                xFieldsDropdown.innerHTML = '<option value="">Select X Field</option>';
                yFieldsDropdown.innerHTML = '<option value="">Select Y Field</option>';
                data.x_fields.forEach(field => {
                    let option = document.createElement("option");
                    option.value = field;
                    option.textContent = field;
                    xFieldsDropdown.appendChild(option);
                });

                // Populate Y Fields
                data.y_fields.forEach(field => {
                    let option = document.createElement("option");
                    option.value = field;
                    option.textContent = field;
                    yFieldsDropdown.appendChild(option);
                });

                xFieldsContainer.style.display = data.x_fields.length > 0 ? "block" : "none";
                yFieldsContainer.style.display = data.y_fields.length > 0 ? "block" : "none";
                window.selectedReportType = data.report_type;
                window.selectedDataType = data.data_type;
                window.selectedsYear = data.syear;
                window.selectedCountType = data.count_type;
            })
            .catch(error => console.error("Error fetching fields:", error));
    } else {
        xFieldsContainer.style.display = "none";
        yFieldsContainer.style.display = "none";
        xFieldsDropdown.innerHTML = '<option value="">Select X Field</option>';
        yFieldsDropdown.innerHTML = '<option value="">Select Y Field</option>';
    }
});
</script>
