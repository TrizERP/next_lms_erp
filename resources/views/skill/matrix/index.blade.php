@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
    <div class="row">
        <!-- Left Panel -->
        <div class="col-md-3">
            <h5>Navigate my assessment</h5>
            <ul class="list-group">
                <li class="list-group-item">Project Management ✅</li>
                <li class="list-group-item">Quality Assurance ✅</li>
                <li class="list-group-item active">Senior Management ({{ $totalSkills }} skills)</li>
            </ul>

            <!-- Pie Chart -->
            <canvas id="progressChart"></canvas>

            <p>{{ $completedCount }} out of {{ $totalSkills }} completed ({{ $progress }}%)</p>
        </div>

        <!-- Skill Assessment Section -->
        <div class="col-md-9">
            <div id="skill-container">
                @foreach($skills as $skill)
                    <div class="skill-box" data-skill-id="{{ $skill->id }}">
                        <h3>{{ $skill->title }}</h3>
                        <p>{{ $skill->description }}</p>

                        <label>Skill Level:</label>
                        <select class="skill-level">
                            <option value="5">Highly Skilled</option>
                            <option value="4">Developed Skills</option>
                            <option value="3">Competent</option>
                            <option value="2">Basic Capability</option>
                            <option value="1">Very Low</option>
                        </select>

                        <label>Interest Level:</label>
                        <select class="interest-level">
                            <option value="5">Highly Interested</option>
                            <option value="4">Interested</option>
                            <option value="3">Neutral</option>
                            <option value="2">Low Interest</option>
                            <option value="1">Very Low</option>
                        </select>

                        <button class="submit-skill btn btn-primary mt-3">Save & Next</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
</div>

<!-- JavaScript for transitions & progress -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function () {
    let completedCount = {{ $completedCount }};
    let totalSkills = {{ $totalSkills }};

    // Skill fade-in/out logic
    $(".submit-skill").click(function () {
        let skillBox = $(this).closest(".skill-box");
        let skillId = skillBox.data("skill-id");
        let skillLevel = skillBox.find(".skill-level").val();
        let interestLevel = skillBox.find(".interest-level").val();

        $.ajax({
            url: "{{ route('matrix.save') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                skill_id: skillId,
                skill_level: skillLevel,
                interest_level: interestLevel
            },
            success: function () {
                completedCount++;
                updateChart(completedCount, totalSkills);
                
                skillBox.fadeOut(500, function () {
                    $(this).next(".skill-box").fadeIn(500);
                });
            }
        });
    });

    $(".skill-box:not(:first)").hide(); // Hide all except first

    // Pie Chart for progress
    let ctx = document.getElementById('progressChart').getContext('2d');
    let progressChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Remaining'],
            datasets: [{
                data: [completedCount, totalSkills - completedCount],
                backgroundColor: ['#28a745', '#ddd']
            }]
        }
    });

    function updateChart(completed, total) {
        progressChart.data.datasets[0].data = [completed, total - completed];
        progressChart.update();
    }
});
</script>
@include('includes.footerJs')
@include('includes.footer')
