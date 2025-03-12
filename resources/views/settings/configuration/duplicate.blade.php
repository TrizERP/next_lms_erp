<form class="card py-4" action="{{route('configurations.store')}}" method="POST">
    @csrf 
    <input type="hidden" name="formType" value="duplicatePrevent">
    <input type="hidden" name="main_menu" id="mainMenu">
    <input type="hidden" name="module" id="moduleName">

    <div class="row infoDiv">
        <h4 style="width:100%"><span class="fa fa-info-circle"></span> info</h4>
        <p style="width:100%;margin-top:16px">Duplicate prevention feature only prevents new duplicate records from getting created by users and external applications. Records created from Import, and from Workflows will not be checked for duplicates.</p>
        <p  style="width:100%">
            Existing duplicate records can be removed using “Find Duplicates” feature from the module page.
        </p>
    </div>
    <!-- toggle switch starts  -->
    <div class="row" style="padding:20px;">
        <div class="col-md-6 duplicateHead">
            <div class="buttonHead" style="white-space: nowrap;">
                <h5><b>Enable duplicate check</b></h5>
            </div>
            <div class="radio-group">
                <label class="radio-btn yes">
                    <input type="radio" name="duplicate_status" value="1">
                    YES
                </label>
                <label class="radio-btn no selected"> <!-- Default selected -->
                    <input type="radio" name="duplicate_status" value="0" checked> <!-- Checked by default -->
                    NO
                </label>
            </div>
        </div>
    </div>
    <!-- toggle switch end  -->
    <!-- data list starts  -->
    <div class="row duplicateFields" style="padding:20px;">
        <div class="col-md-6">
            <h5><b>Select Multiple Options</b></h5>
                <input type="text" id="searchInput" list="optionsList" placeholder="Type to search...">
                <datalist id="optionsList">
                
                </datalist>

                <div class="selected-items" id="selectedItems"></div>

                <!-- Hidden input field to store selected values -->
                <input type="hidden" name="selectedValues" id="selectedValues" class="datalistInput">
        </div>
    </div>
    <div class="col-md-12">
            <div class="col-md-6">
                <input type="submit" name="submit" value="submit" class="btn btn-success">
            </div>
        </div>
    <!-- data list ends  -->

</form>
