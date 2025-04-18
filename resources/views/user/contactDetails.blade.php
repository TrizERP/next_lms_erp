<form action="{{route('user_contact_details.store')}}" method="post">
    @csrf
<div class="row">
    <input type="hidden" name="empId" value="{{ isset($data['id']) ? $data['id'] : '' }}">
    <div class="col-md-4">
        <label for="country">Country</label>
        <select name="country" id="country" class="form-control">
            <option value="india" {{ (isset($contactDetails) && $contactDetails->country == 'india') ? 'selected' : '' }}>India</option>
        </select>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mt-2">
        <label for="street1">Street 1</label>
        <input type="text" class="form-control" name="street1" id="street1" placeholder="Street 1" value="{{ isset($contactDetails) ? $contactDetails->street1 : '' }}">
    </div>
    <div class="col-md-6 mt-2">
        <label for="street2">Street 2</label>
        <input type="text" class="form-control" name="street2" id="street2" placeholder="Street 2" value="{{ isset($contactDetails) ? $contactDetails->street2 : '' }}">
    </div>
</div>

<div class="row">
    <div class="col-md-6 mt-2">
        <label for="city">City/Town</label>
        <input type="text" class="form-control" name="city" id="city" placeholder="City" value="{{ isset($contactDetails) ? $contactDetails->city : '' }}">
    </div>
    <div class="col-md-6 mt-2">
        <label for="state">State / Province</label>
        <input type="text" class="form-control" name="state" id="state" placeholder="State" value="{{ isset($contactDetails) ? $contactDetails->state : '' }}">
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <label for="zipCode">Zip Code</label>
        <input type="text" class="form-control" name="zipCode" id="zipCode" placeholder="Zip Code" value="{{ isset($contactDetails) ? $contactDetails->zipCode : '' }}">
    </div>
</div>

<div class="row">
    <div class="col-md-6 mt-2">
        <label for="homeTelephone">Home Telephone</label>
        <input type="text" class="form-control" name="homeTelephone" id="homeTelephone" placeholder="Home Telephone" value="{{ isset($contactDetails) ? $contactDetails->homeTelephone : '' }}">
    </div>
    <div class="col-md-6 mt-2">
        <label for="mobile">Mobile</label>
        <input type="text" class="form-control" name="mobile" id="mobile" placeholder="Mobile" value="{{ isset($contactDetails) ? $contactDetails->mobile : '' }}">
    </div>
</div>
<div class="row">
    <div class="col-md-6 mt-2">
        <label for="workTelephone">Work Telephone</label>
        <input type="text" class="form-control" name="workTelephone" id="workTelephone" placeholder="Work Telephone" value="{{ isset($contactDetails) ? $contactDetails->workTelephone : '' }}">
    </div>
</div>
<div class="row">
    <div class="col-md-6 mt-2">
        <label for="workEmail">Work Email</label>
        <input type="text" class="form-control" name="workEmail" id="workEmail" placeholder="Work Email" value="{{ isset($contactDetails) ? $contactDetails->workEmail : '' }}">
    </div>
    <div class="col-md-6 mt-2">
        <label for="otherEmail">Other Email</label>
        <input type="text" class="form-control" name="otherEmail" id="otherEmail" placeholder="Other Email" value="{{ isset($contactDetails) ? $contactDetails->otherEmail : '' }}">
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <label for="otherContact">Same as Permanent 
            <input type="checkbox" name="other_status" id="sameVal" 
            {{ isset($contactDetails) && $contactDetails->other_status ? 'checked' : '' }} 
            {{ isset($contactDetails) && $contactDetails->other_status ? 'disabled' : '' }}>
        </label>
    </div>
</div>
<div class="card otherVals" style="display: none;">
    <div class="row">
        <div class="col-md-4">
            <label for="country">Country</label>
            <select name="country_other" id="country_other" class="form-control">
                <option value="india" {{ (isset($contactDetails) && $contactDetails->country_other == 'india') ? 'selected' : '' }}>India</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mt-2">
            <label for="street1">Street 1</label>
            <input type="text" class="form-control" name="street1_other" id="street1_other" placeholder="Street 1" value="{{ isset($contactDetails) ? $contactDetails->street1_other : '' }}">
        </div>
        <div class="col-md-6 mt-2">
            <label for="street2">Street 2</label>
            <input type="text" class="form-control" name="street2_other" id="street2_other" placeholder="Street 2" value="{{ isset($contactDetails) ? $contactDetails->street2_other : '' }}">
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mt-2">
            <label for="city">City/Town</label>
            <input type="text" class="form-control" name="city_other" id="city_other" placeholder="City" value="{{ isset($contactDetails) ? $contactDetails->city_other : '' }}">
        </div>
        <div class="col-md-6 mt-2">
            <label for="state">State / Province</label>
            <input type="text" class="form-control" name="state_other" id="state_other" placeholder="State" value="{{ isset($contactDetails) ? $contactDetails->state_other : '' }}">
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <label for="zipCode">Zip Code</label>
            <input type="text" class="form-control" name="zipCode_other" id="zipCode_other" placeholder="Zip Code" value="{{ isset($contactDetails) ? $contactDetails->zipCode_other : '' }}">
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('.otherVals').hide();
        @if(isset($contactDetails) && $contactDetails->other_status)
        $('.otherVals').show();
        @endif
        
        $('#sameVal').on('change', function() {
            if ($(this).is(':checked')) {
                $('.otherVals').show();
            } else {
                $('.otherVals').hide();
            }
        });
    });
</script>
<div class="row">
    <div class="center">
        <input type="submit" value="Save" class="btn btn-primary mt-3">
    </div>
</div>
</form>
