@extends('lmslayout') @section('container')
<style>
    .contentName{
        display:flex;
        justify-content: space-evenly;
        padding: 20px 4px;
    }
    .treeDIv,.tableDiv{
        padding:8px 10px;
    }
    .treeView,.tableView{
        padding:10px 30px !important;
    }
    .activeTab{
        color : #25bdea;
        border-bottom : 4px solid #25bdea;
    }
    .roundCard{
        border-radius:10px;
    }
    h1,h2,h3,h4,h5,h6{
        margin-bottom:0px;
    }
   .tree {
    --spacing: 1.5rem;
    --radius: 6px;
    }

    .tree ul.open {
        display: block; /* Show child nodes when 'open' class is added */
    }
    .tree li {
    display: block;
    position: relative;
    padding-left: calc(2 * var(--spacing) - var(--radius) - 2px);
    }

    .tree ul {
    margin-left: calc(var(--radius) - var(--spacing));
    padding-left: 0;
    }

    .tree ul li {
    border-left: 2px solid #ddd;
    }

    .tree ul li:last-child {
    border-color: transparent;
    }

    .tree ul li::before {
    content: '';
    display: block;
    position: absolute;
    top: calc(var(--spacing) / -2);
    left: -2px;
    width: calc(var(--spacing) + 2px);
    height: calc(var(--spacing) + 1px);
    border: solid #ddd;
    border-width: 0 0 2px 2px;
    }

    .tree summary {
    display: block;
    cursor: pointer;
    padding:6px;
    }

    .tree summary::marker,
    .tree summary::-webkit-details-marker {
    display: none;
    }

    .tree summary:focus {
    outline: none;
    }

    .tree summary:focus-visible {
    outline: 1px dotted #000;
    }

    .tree li::after,
    .tree summary::before {
    content: '';
    display: block;
    position: absolute;
    top: calc(var(--spacing) / 2 - var(--radius));
    left: calc(var(--spacing) - var(--radius) - 1px);
    width: calc(2 * var(--radius));
    height: calc(2 * var(--radius));
    border-radius: 50%;
    background: #ddd;
    }

    .tree summary::before {
    z-index: 1;
    background: #aaa url('expand-collapse.svg') 0 0;
    }

    .tree summary span{
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 4px;
    }
    /* .treeSpan{
        background : #06457c;
        color:#fff;
    } */
    /* .parentSpan{
        background : #0262b7;
        color:#fff;
    } */
    /* .catSpan{
        background : #2484d9;
        color:#fff;
    } */
    .tree li.open > ul {
        display: block;
    }
    .toggleDiv{
        padding: 10px;
        text-align: center;
    }
    .toggleBtn{
        padding : 6px;
    }
    .toggleBtn .btn{
        width: 133px;
    }
    .hidden { display: none; }
    .highlight { background-color: yellow; }
    table tr th, table tr td{
        color : black;
    }
    .activeSummary, .tree summary:hover{
        color:#25bdea;
    }
</style>
<div id="page-wrapper">
	<div class="container-fluid">
	
		@if ($sessionData = Session::get('data'))
        @if (isset($sessionData['status']))
            <div class="col-md-12 alert alert-{{ $sessionData['status'] == '1' ? 'success' : 'danger' }} alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{!! $sessionData['message'] !!}</strong>
            </div>
        @endif 
        @endif

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card roundCard mb-4">
          <div class="row justify-content-center" style="background-color: #ADDFFF">
            <div class="col-md-2 mx-auto p-3">
                <h4 class="page-title">Skill Library</h4>
            </div>
            <div class="col-md-4 mx-auto text-right p-3">  <!-- Or any other column size you need -->
              <label for="mainCategory">Sector:</label>
            <select class="form-select form-select-lg mb-3" id="mainCategory" onchange="updateSubcategory()">
                <option value="">--Select Sector--</option>
                <option value="Hospital Management">Hospital Management</option>
            </select>
            </div>
            <div class="col-md-6 mx-auto p-3">
            <label for="subCategory">Sub-Sector:</label>
            <select class="form-select form-select-lg mb-3" id="subCategory" onchange="updateSkills()">
                <option value="">--Select Sub-Sector--</option>
            </select>
            </div>
            <h1 class="center" id="selectedCategory"></h1>
          </div>
        </div>
    </div>
</div>  
<script>
        // Data mapping
        const data = {
            "Healthcare": {
                "Ambulance Operations Readiness": [],
                "Department Management": [],
                "Drug Compounding and Management": [],
                "Education for Healthcare Professions": [],
                "Enterprise Risk Management": [],
                "Ethics and Professionalism": [],
                "Evidence Based Practice": [],
                "General Management": [],
                "Patient and/or Client Education and Health Promotion": [],
                "Patient Care": [],
                "People Development": [],
                "Prehospital Patient Management": [],
                "Quality and Patient Safety": [],
                "Stakeholder Engagement and Partnerships": []
            },
            "Education": {
                "General Management": [],
                "Learning Assessment and Evaluation": [],
                "Learning Delivery": [],
                "Learning Design": [],
                "Marketing": [],
                "Technology Development and Management": [],
                "Workplace Learning": []
            }
        };

        function updateSubcategory() {
            let mainCategory = document.getElementById("mainCategory").value;
            let subCategoryDropdown = document.getElementById("subCategory");
            let skillDropdown = document.getElementById("skill");

            document.getElementById("selectedCategory").innerText = mainCategory ? mainCategory : "";

            subCategoryDropdown.innerHTML = "<option value=''>--Select Sub-Sector--</option>";

            if (mainCategory) {
                for (let sub in data[mainCategory]) {
                    let option = new Option(sub, sub);
                    subCategoryDropdown.add(option);
                }
            }
        }

        function updateSkills() {
            let mainCategory = document.getElementById("mainCategory").value;
            let subCategory = document.getElementById("subCategory").value;
            let skillDropdown = document.getElementById("skill");

            skillDropdown.innerHTML = "<option value=''>--Select Skill--</option>";

            if (mainCategory && subCategory) {
                data[mainCategory][subCategory].forEach(skill => {
                    let option = new Option(skill, skill);
                    skillDropdown.add(option);
                });
            }
        }
    </script>

        		
        <!-- header card starts  -->
        <div class="card roundCard mb-4">
            <div class="row">

                <div class="col-md-2">
                    <div class="contentName">
                        <div class="menuIcon">
                            <h4><span class="mdi mdi-menu"></span></h4>
                        </div>
                        <div class="menuName">
                            <h4>Skills directory</h4>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="row tabsDiv">
                        <div class="treeDiv">
                            <a class="btn treeView activeTab" onclick="containerView('tree')">Tree</a>
                        </div>
                        <div class="tableDiv">
                            <a class="btn tableView" onclick="containerView('table')">Table</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <!-- header card end  -->
        
        <!-- content div start -->
		<div class="ContentDiv treeContainer">
           @include('lms.library.skill_library.treeView')
        </div>

        <div class="ContentDiv tableContainer">
           @include('lms.library.skill_library.tableView')
        </div>
        <!-- content div end -->
	</div>
</div>

@include('includes.lmsfooterJs')
<script>
    $(document).ready(function(){
        $('.tableContainer').hide();
        $('.subCat').hide();
        openAll();

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
                    title: 'Skill Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'LMS Curriculum Report'},
                {extend: 'excel', text: ' EXCEL', title: 'LMS Curriculum Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Skill Report',
                },
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

    })

    function containerView(tabname){
        $('.btn').removeClass('activeTab');
        $('.'+tabname+'View').toggleClass('activeTab');

        $('.ContentDiv').hide();
        $('.'+tabname+'Container').show();
    }

    // Function to open all tree nodes
    function openAll() {
        $('.tree ul').addClass('open').show();
    }

    // Function to close all tree nodes
    function closeAll() {
        $('.tree ul').removeClass('open').hide();
    }

    // Add click event to toggle individual nodes
      function actionLi(className) {
        const $summary = $(event.target).closest('summary');
        const $node = $summary.next(`.${className}`);

        if ($node.length) {
            if ($node.is(':visible')) {
                $node.hide();
            } else {
                $node.show();
            }
        }
    }

    $(document).ready(function () {
        $('a.viewTree').css({"pointer-events": "none"});
        $('a.editTree').css({"pointer-events": "none"});
        $('a.deleteTree').css({"pointer-events": "none"});
        // Attach click event to all <li> elements
        $('ul.tree').on('click', 'li', function (event) {

            $(".tree li span").removeClass("activeSummary");
            
            $('a.viewTree').css({"pointer-events": "none"});
            $('a.editTree').css({"pointer-events": "none"});
            $('a.deleteTree').css({"pointer-events": "none"});
            // Prevent the event from bubbling to parent elements pointer-events: none;
            event.stopPropagation();

            // Check if the clicked <li> has the class 'lastNode'
            if ($(this).hasClass('lastNode')) {
                $(this).find("summary span").first().addClass("activeSummary");
                // Get the data-id attribute of the clicked <li>
                let dataId = $(this).data('id');
                
                if (dataId) {
                    // Update the href attribute of the anchor tag
                    let editHref = `skill_library/${dataId}/edit`;
                    let showHref = `skill_library/${dataId}/show`;
                    let deleteHref = `skill_library/${dataId}/delete`;

                    $('a.viewTree').attr('href', showHref);
                    $('a.editTree').attr('href', editHref);
                    $('a.deleteTree').attr('href', deleteHref);
                    
                    // console.log(`Updated href to: ${newHref}`); // For debugging
                    $('a.viewTree').css({"pointer-events": "all"});
                    $('a.editTree').css({"pointer-events": "all"});
                    $('a.deleteTree').css({"pointer-events": "all"});
                }
            }else{
                $('a.viewTree').attr('href', '');
                $('a.editTree').attr('href', '');
                $('a.deleteTree').attr('href', '');
            }
        });
    });

  </script>
  <script>

        $(document).ready(function () {
            $('#searchInput').on('keyup', function () {
                let searchTerm = $(this).val().toLowerCase();

                if (searchTerm) {

                    // Traverse all `li` elements inside the tree
                    $('.tree li').each(function () {
                        let text = $(this).text().toLowerCase();
                        // console.log(text);
                        $(this).hide();
                        if (text.includes(searchTerm)) {
                            $(this).show();
                        } 
                    });
                } else {
                    // If search box is empty, reset the tree
                    $('.tree li').show();
                    $('.tree ul').show();
                }
            });
        });
        
        function dbclickLi(skillId) {
            var url = "/lms/skill_library/" + skillId + "/show";
            window.open(url, "_blank"); // Open the URL in a new tab
        }

    </script>
@include('includes.footer')
@endsection