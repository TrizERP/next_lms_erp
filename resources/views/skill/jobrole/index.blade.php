@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .center {
        text-align: center;
        vertical-align: middle;
        font-family: system-ui;
        color: #000000 !important;
    }
</style>
<div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Job Role with SKAT Matrix</h4>
                    <ul>
                        <li><strong>➤ Skills:</strong> Total required skills</li>
                        <li><strong>➤ Knowledge:</strong> Total required knowledge</li>
                        <li><strong>➤ Abilities:</strong> Total required abilities</li>
                        <li><strong>➤ Tasks:</strong> Total perform tasks</li>
                    </ul>
                </div>
                    <div class="col-lg-9 col-md-8 col-sm-8 col-xs-12 center">
                        <!--<h1 class="center">{{ $skills->first()->sector }}</h1>-->
<label for="Category">Industry:</label>
<select class="form-select form-select-lg mb-4" id="Category">
    <option value="">--Select Industry--</option>
    <option value="Hospital Management">Hospital Management</option>
</select>

<label for="mainCategory">Sector:</label>
<select class="form-select form-select-lg mb-4" id="mainCategory" onchange="updateSubcategory()">
    <option value="">--Select Sector--</option>
    @php
        $sectorData = [];
    @endphp
    @foreach ($skills->groupBy('sector') as $sector => $items)
        <option value="{{ $sector }}">{{ $sector }}</option>
        @php
            $sectorData[$sector] = $items->groupBy('track')->keys()->toArray();
        @endphp
    @endforeach
</select>

<label for="subCategory">Track:</label>
<select class="form-select form-select-lg mb-4" id="subCategory">
    <option value="">--Select Track--</option>
</select>
                        <h1 class="center" id="selectedCategory"></h1>
                    </div>
            </div>
        </div>
        <div class="card">
            <div class="panel-body">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table id="jobrole" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                            <th>Sector / Department</th>
                            <th>Track</th>
                            <th>Job Role</th>
                            <th class="center">Perform Tasks</th>
                            <th class="center" style="text-align:center !important">Required Skills</th>
                            <!--<th class="center">Required Knowledge</th>
                            <th class="center">Required Ability</th>-->
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($skills as $skill)
                        <tr>
                            <td>{{ $skill->industries }} ({{ $skill->sector }})</td>
                            <td>{{ $skill->track }}</td>
                            <td><a href="{{ route('jobrole.jobdescription',['id' => $skill->id])}}" target="_blank" rel="noopener noreferrer">{{ $skill->jobrole }}</a></td>
                            <td class="center"><a href="#" class="open-modal" data-toggle="modal" data-target="#dynamicModal" data-title="Perform Tasks" data-content="{{ $skill->TasksData }}"><u>{{ $skill->Tasks }}</u></a></td>
                            <td class="center"><a href="#" class="open-modal" data-toggle="modal" data-target="#dynamicModal" data-title="Required Skill" data-content="{{ $skill->SkillData }}"><u>{{ $skill->Skill }}</u></a></td>
                            <!--<td class="center"><a href="#" class="open-modal" data-toggle="modal" data-target="#dynamicModal" data-title="Required Knowledge for this skill" data-content="{{ $skill->KnowledgeData }}"><u>{{ $skill->Knowledge }}</u></a></td>
                            <td class="center"><a href="#" class="open-modal" data-toggle="modal" data-target="#dynamicModal" data-title="Required Ability for this skill" data-content="{{ $skill->AbilityData }}"><u>{{ $skill->Ability }}</u></a></td>-->
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Dynamic Modal -->
<div class="modal fade" id="dynamicModal" tabindex="-1" role="dialog" aria-labelledby="dynamicModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width: 600px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Modal Title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="modalContent">Modal Content</p>
      </div>
    </div>
  </div>
</div>
<script>
    $(document).ready(function() {
        $('.open-modal').click(function() {
            var title = $(this).data('title');   // Get title from the clicked link
            var content = $(this).data('content'); // Get content from the clicked link

            $('#modalTitle').text(title);       // Update modal title
            $('#modalContent').html(content);   // Update modal content
        });
    });
</script>
@include('includes.footerJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#jobrole').DataTable({
        "scrollX": true,
        "pageLength": 50  // Show 100 rows by default
    });
});

</script>
<script>
    // Convert Laravel PHP data to JavaScript
    const data = @json($sectorData);

    function updateSubcategory() {
        let Category = document.getElementById("Category").value;
        let mainCategory = document.getElementById("mainCategory").value;
        let subCategoryDropdown = document.getElementById("subCategory");
        document.getElementById("selectedCategory").innerText = Category ? Category : "";
        // Clear previous options
        subCategoryDropdown.innerHTML = "<option value=''>--Select Track--</option>";

        // Populate subcategories if a sector is selected
        if (mainCategory && data[mainCategory]) {
            data[mainCategory].forEach(track => {
                let option = new Option(track, track);
                subCategoryDropdown.add(option);
            });
        }
    }
</script>    
@include('includes.footer')
