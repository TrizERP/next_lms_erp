@include('includes.headcss')
<!-- Editable CSS -->
<link type="text/css" rel="stylesheet" href="../../../plugins/bower_components/jsgrid/jsgrid.min.css" />
<link type="text/css" rel="stylesheet" href="../../../plugins/bower_components/jsgrid/jsgrid-theme.min.css" />
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit User</h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
                <div class="panel-body">
                    @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                    @endif
                    <div class="row">
                        <div class="col-lg-2 col-sm-4 col-xs-12">
                            <a href="{{ route('add_user.create') }}"><button class="btn btn-block btn-default btn-rounded">User Information</button></a>
                        </div>
                        <div class="col-lg-2 col-sm-4 col-xs-12">
                            <a href="{{ route('add_user_past_education.index') }}"><button class="btn btn-block btn-info btn-rounded">Past Education</button></a>
                        </div>
                        <!-- <div class="col-lg-2 col-sm-4 col-xs-12">
                        <button class="btn btn-block btn-default btn-rounded">Primary</button>
                    </div>
                    <div class="col-lg-2 col-sm-4 col-xs-12">
                        <button class="btn btn-block btn-default btn-rounded">Success</button>
                    </div>
                    <div class="col-lg-2 col-sm-4 col-xs-12">
                        <button class="btn btn-block btn-default btn-rounded">Danger</button>
                    </div> -->
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="white-box">
                                <div id="basicgrid"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
    function getUsername() {
        var first_name = document.getElementById("first_name").value;
        var last_name = document.getElementById("last_name").value;
        var username = first_name.toLowerCase() + "_" + last_name.toLowerCase();
        document.getElementById("user_name").value = username;
    }

    function savePastEducation(data){
        var myJSON = JSON.stringify(data);
        var path = "{{ route('ajax_pasteducation') }}";
        $.ajax({url: path,data:'data='+myJSON, success: function(result){
            alert("success");
        }});
    }
</script>
<!-- Editable -->
<!-- <script src="../../../plugins/bower_components/jsgrid/db.js"></script> -->
<script>
    (function() {

        var db = {

            loadData: function(filter) {
                return $.grep(this.clients, function(client) {
                    return (!filter.degree || client.degree.indexOf(filter.degree) > -1) &&
                        (!filter.medium || client.medium.indexOf(filter.medium) > -1) &&
                        (!filter.university_name || client.university_name.indexOf(filter.university_name) > -1) &&
                        (!filter.passing_year || client.passing_year.indexOf(filter.passing_year) > -1) &&
                        (!filter.main_subject || client.main_subject.indexOf(filter.main_subject) > -1);
                });
            },

            insertItem: function(insertingClient) {
                console.log(Object.values(insertingClient));
                savePastEducation(insertingClient);
                this.clients.push(insertingClient);
            },

            updateItem: function(updatingClient) {
                savePastEducation(updatingClient);
                console.log(Object.values(updatingClient));
            },

            deleteItem: function(deletingClient) {
                var clientIndex = $.inArray(deletingClient, this.clients);
                this.clients.splice(clientIndex, 1);
            }

        };

        window.db = db;


        // db.countries = [
        //     { Name: "", Id: 0 },
        //     { Name: "United States", Id: 1 },
        //     { Name: "Canada", Id: 2 },
        //     { Name: "United Kingdom", Id: 3 },
        //     { Name: "France", Id: 4 },
        //     { Name: "Brazil", Id: 5 },
        //     { Name: "China", Id: 6 },
        //     { Name: "Russia", Id: 7 }
        // ];

        db.clients = @php echo json_encode($data, true);
        @endphp;

    }());
</script>
<script type="text/javascript" src="../../../plugins/bower_components/jsgrid/jsgrid.min.js"></script>
<script src="../../../admin_dep/js/jsgrid-init.js"></script>
<script>
    $("#basicgrid").jsGrid({
        height: "auto",
        width: "100%",
        filtering: true,
        inserting: true,
        editing: true,
        sorting: true,
        paging: true,
        autoload: true,
        pageSize: 9,
        pageButtonCount: 5,
        deleteConfirm: "Do you really want to delete?",
        controller: db,
        fields: @php echo json_encode($fieldsData, true);@endphp
    });
</script>
@include('includes.footer')