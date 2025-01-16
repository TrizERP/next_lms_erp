<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center" style="gap: 8px;">
            <div class="me-3" style="flex: 1;">
                <label for="sub_institute_id">Institute</label>
                <select id="sub_institute_id" name="sub_institute_id" class="form-control" style="width: 100%;">
                    <option value="">Select Institute</option>
                    @foreach($institutes as $instituteId)
                        <option value="{{ $instituteId }}">{{ $instituteId }}</option>
                    @endforeach
                </select>
            </div>
            <div class="me-3" style="flex: 1;">
                <label for="from">From</label>
                <input type="date" id="from" name="from" class="form-control" style="width: 100%;" />
            </div>

            <div class="me-3" style="flex: 1;">
                <label for="to">To</label>
                <input type="date" id="to" name="to" class="form-control" style="width: 100%;" />
            </div>
            @if($fields) <!-- Only show the filter if fields are available -->
                <div class="me-3" style="flex: 1;">
                    <label for="notification_type">Fields</label>
                    <select id="field" name="notification_type" class="form-control" style="width: 100%;">
                        <option value="">Select Fields</option>
                        @foreach($fields as $fieldId)
                        <option value="{{ $fieldId }}">{{ $fieldId }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div style="flex: 0 0 auto;">
                <input type="button" class="btn btn-success" value="Filter" onclick="getData()" style="width: 80px;" />
            </div>

            <div style="flex: 0 0 auto;">
                <input type="button" class="btn btn-success" value="Download Report" onclick="downloadReport()" style="width: 180px;" />
            </div>
        </div>
    </div>
</div>
