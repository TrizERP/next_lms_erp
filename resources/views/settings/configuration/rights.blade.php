<form action="{{route('configurations.store')}}" method="POST">
    @csrf
    <input type="hidden" name="formType" value="usersRights">
    <div class="row">
        <div class="col-md-4 form-group">
            <label for="">Select Profile</label>
            <select name="user_profile" id="user_profile" class="form-control" required>
                <option value="">Select Profile</option>
                @foreach($data['UserProfile'] as $key=>$value)
                <option value="{{$value->id}}">{{$value->name}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-8"></div>
    </div>
    <table class="table">
        <thead class="thead-dark">
        <tr>
            <th scope="col">Modules</th>
            <th scope="col"><input type="checkbox" name="can_view">View</th>
            <th scope="col"><input type="checkbox" name="can_add">Create</th>
            <th scope="col"><input type="checkbox" name="can_edit">Edit</th>
            <th scope="col"><input type="checkbox" name="can_delete">Delete</th>
            <th scope="col">Field and Tool Privilages</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td align="left">Add Student</td>
            <td align="center"><input type="checkbox" name="can_view"></td>
            <td align="center"><input type="checkbox" name="can_view"></td>
            <td align="center"><input type="checkbox" name="can_view"></td>
            <td align="center"><input type="checkbox" name="can_view"></td>
            <td align="center"><span class="mdi mdi-chevron-down mdi-up d-none"></span><span class="mdi mdi-chevron-up mdi-down"></span></td>
        </tr>
        <tr class="displayUpDown">
            <td align="center" colspan="6">
                <div class="row col-md-12" style="padding:20px 0px;">
                    <div class="col-md-6" align="left">
                    <h6>Fields</h6>
                    </div>
                    <div class="col-md-2 dotsDiv" align="right">
                        <h6><span class="dots dotBlack"></span>invisible</h6>
                    </div>
                    <div class="col-md-2 dotsDiv" align="right">
                    <h6><span class="dots dotOrange"></span>read only</h6>
                    </div>
                    <div class="col-md-2 dotsDiv" align="right">
                    <h6><span class="dots dotGreen"></span>write</h6>
                    </div>
                </div>
            </td>
        </tr>
        <tr class="displayUpDown">
            <td align="center" colspan="6" class="displayRights">
                <div class="row col-md-12" style="padding:20px 0px;">
                    <div class="col-md-4"> 
                    <div class="fieldContainer">
                        <!-- Toggle Switch -->
                        <div class="toggle-container">
                        <div id="toggleSwitch" class="toggle-switch">
                            <div id="toggleKnob" class="toggle-knob knob-start"></div>
                        </div>
                        <input type="hidden" id="toggleValue" name="fieldName[]" value="start">
                        </div>

                        <!-- Field Name -->
                        <div class="fieldNames">
                        <p>First Name</p>
                        </div>
                    </div>
                    </div>
                    <div class="col-md-4">
                    <div class="fieldContainer">
                        <!-- Toggle Switch -->
                        <div class="toggle-container">
                        <div id="toggleSwitch" class="toggle-switch">
                            <div id="toggleKnob" class="toggle-knob knob-start"></div>
                        </div>
                        <input type="hidden" id="toggleValue" name="fieldName[]" value="start">
                        </div>

                        <!-- Field Name -->
                        <div class="fieldNames">
                        <p>Last Name</p>
                        </div>
                    </div>
                    </div>
                    <div class="col-md-4">
                    <div class="fieldContainer">
                        <!-- Toggle Switch -->
                        <div class="toggle-container">
                        <div id="toggleSwitch" class="toggle-switch">
                            <div id="toggleKnob" class="toggle-knob knob-start"></div>
                        </div>
                        <input type="hidden" id="toggleValue" name="fieldName[]" value="start">
                        </div>

                        <!-- Field Name -->
                        <div class="fieldNames">
                        <p>Middle Name</p>
                        </div>
                    </div>
                    </div>
                </div>
            </td>
        </tr>
            <!-- // datatable rights  -->
            <tr class="displayUpDown">
                <td style="padding:20px" colspan="6">
                    <h6>Tools</h6>
                    <div class="datatableRights">
                        <table class="table table-bordered">
                            <tr>
                                <td><input type="checkbox" name="rights[]" id="rights"> Import</td>
                                <td><input type="checkbox" name="rights[]" id="rights"> Export</td>
                                <td><input type="checkbox" name="rights[]" id="rights"> Merge</td>
                            </tr>
                            <tr>
                                <td colspan="3"><input type="checkbox" name="rights[]" id="rights"> Duplicate Handling</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="row">
        <div class="col-md-12 mt-3">
            <input type="submit" class="btn btn-success" name="submit" value="Save">
        </div>
    </div>
</form>
 