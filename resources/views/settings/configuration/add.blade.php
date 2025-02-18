@extends('layout')
@section('container')
<style>
  .select-module{
    display:flex;
    flex-wrap:wrap;
  }
  .select-module>label{
    padding:10px;
  }
  .displayTabs{
    padding:12px 0px 0px 0px;
    text-align:center;
  }
   
  .activeTab{
    /* border-bottom:4px solid black; */
    background:#fff;
    border-top-right-radius: 10px;
    border-top-left-radius: 10px;
    /* padding: 12px 0px; */
  }
  .ContainerCard{
    margin:0px !important;width:100%;box-shadow: none !important;
  }
  .basicContainer{
    width:100%;
    padding : 36px 0px 0px 0px;
  }
  .basicCard, .basicSubContents{
    width:100%;
    border:1px solid #ddd;
    display:flex;
  }
  .basicCard{
    padding : 20px 6px;
    flex-wrap:wrap;
  }
  .basicSubContents, .basicContents{
    height:120px;
  }
  .basicContents{
    width: 50%;
    /* margin:0px 6px; */
    padding: 0px 10px 0px 10px;
  }
  .content40{
    width: 40%;
    padding:16px;
    border-right:1px solid #ddd;
    display: flex;
    justify-content: space-between;
  }
    .content60{
      width: 60%;
      padding:16px;
      display: flex;
      justify-content: space-between;
    }
    .dragIcon{
      cursor:grab;
    }
    .dragIcon>span
    {
        font-size: 24px;
    }
    h1,h2,h3,h4,h5,h6{
      margin-bottom:0px !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <!-- Start Select Module -->
        <div class="card" style="width:100%">
            <div class="select-module">
                <label for="module_select"><b>Select Module</b></label>
                <select name="module_name" id="module_name" class="form-control col-md-2">
                    @foreach($data['modulesList'] as $key=>$value)
                    <option value="{{$value['module']}}">{{$value['module']}}</option>
                    @endforeach
                </select>
            </div>
      </div>
        <!-- End  Select Module -->
        
        <!-- start module details -->
        <!-- <div class="card" style="width:100%"> -->
            <!-- tabs start  -->
            <div class="tabRow">
              <div class="row" style="padding:15px 15px 0px 15px;">
                  <div class="displayTabs detailTab col-md-2 activeTab" onclick="activeTabs('detail')">
                      <h6>Detail View Layout</h6>
                  </div>
                  <div class="displayTabs duplicateTab col-md-2" onclick="activeTabs('duplicate')">
                      <h6>Duplicate Prevention</h6>
                  </div>
                  <div class="col-md-8"></div>
              </div>
              <!-- tabs containers start -->
              <div class="row" style="margin:0px !important">
                <div class="card ContainerCard">
                  <div class="dataContainer detailContainer show">
                      <div class="addBlock">
                          <button class="btn btn-outline-secondary"><span class="mdi mdi-plus"></span> Add Block</button>
                      </div>
                      <div class="basicContainer">
                          
                      </div>
                  </div>
                  <div class="dataContainer duplicateContainer hide">
                  duplicateContainer
                  </div>
                </div>
              </div>
              <!-- tabs containers start -->
            </div>
            <!-- tabs  ends-->
           
        <!-- </div> -->
        <!-- end module details -->

    </div>
</div>

@include('includes.footerJs')
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
   $(document).ready(function(){
    getFieldData();

    $('#module_name').on('change',function(){
      getFieldData();
    })
   })
   function activeTabs(activeTab){
        $('.displayTabs').removeClass('activeTab');
        $('.'+activeTab+'Tab').addClass('activeTab');
        $('.dataContainer').hide();
        $('.'+activeTab+'Container').show();
   }

   function getFieldData(){
    var selModule = $('#module_name').val();
    $('.basicContainer').empty();
        $.ajax({
            url : "{{route('getFeildLists')}}",
            data : {module:selModule},
            type : 'get',
            success : function(response){
              console.log(response); 

              let row = ``;
              let count = 0;

              row += `<h5><b>${selModule}</b></h5> <div class="basicCard">`;

              $.each(response, function(index, field) {
                if(count=== 0) {
                    row += `<div class="basicContents"  data-id="${field.field_name}" data-section="Basic Details"><div class="basicSubContents">`;
                  }

                  row += `
                    
                          <div class="content40">
                              <div class="dragIcon">
                                  <span class="mdi mdi-drag"></span>
                              </div>
                              <div class="fieldData text-right">
                                  <p class="fieldTitle" style="margin:0px !important">
                                      <b>${field.field_label}</b> 
                                      ${field.is_mandatory ? '<span class="mdi mdi-asterisk" style="color:red"></span>' : ''}
                                  </p>
                                  <p class="fieldType" style="margin:0px !important">${field.field_type}</p>
                              </div>
                          </div>

                          <div class="content60">
                              <div class="column1">
                                  <ul>
                                      <li style="padding-bottom:30px"><span class="mdi mdi-information"></span> ${field.is_mandatory ? "Mandatory" : "Optional"}</li>
                                      <li><span class="mdi mdi-asterisk"></span> ${field.validation_rules ? "Validation" : "No Validation"}</li>
                                  </ul>
                              </div>
                              <div class="column2">
                                  <ul>
                                      <li style="padding-bottom:30px"><span class="mdi mdi-eye-outline"></span> ${field.is_visible ? "Visible" : "Hidden"}</li>
                                      <li><span class="mdi mdi-alpha-d-circle"></span> Default</li>
                                  </ul>
                              </div>
                              <div class="column3">
                                  <span class="mdi mdi-pencil" onclick="editModel('${field.field_name}','${field.field_type}','${field.section}')"></span>
                              </div>
                          </div>
                      
                  `;

                  count++;

                  if (count === 1) {
                      // Close the row after every 2 items or at the last item
                      row += `</div></div>`;
                      count = 0;
                  }
              });
              row +=`</div>`;
              $(".basicContainer").append(row);

                  },
                  error : function(response){
                      alert('Opps ! Something went wrong');
                  }
              })
        }

   function editModel(fieldName, fieldType, sectionName) {
    $.ajax({
        url: "{{ route('configurations.edit', ':fieldName') }}".replace(':fieldName', fieldName),
        type: 'GET',
        data: {
            fieldName: fieldName,
            fieldType: fieldType,
            sectionName: sectionName
        },
        success: function(response) {
            console.log(response);
        }
    });
}
$(document).ready(function () {
    $(".basicContainer").sortable({
        placeholder: "sortable-placeholder",
        handle: ".dragIcon",  
        items: ".basicContents",
        update: function(event, ui) {
          var selModule = $('#module_name').val();
            let sortedFields = $(this).sortable("toArray", { attribute: "data-id" });
            let section =  $(this).attr('data-section');
            console.log("Sorted Order:", sortedFields);

            // Uncomment when backend is ready
            $.ajax({
                url: "{{route('updateMenuSortOrder')}}",
                data: { orderArr: sortedFields, masterType: 'institute', module : selModule, section:section },
                type: "POST",
                success: function(response) {
                    console.log("Updated order:", response);
                    if(response.status === 0){
                        alert(response.message);
                    }
                },
                error: function(response){
                    alert('Oops! Something went wrong');
                }
            });
        }
    }).disableSelection();
});

</script>

@include('includes.footer')

@endsection