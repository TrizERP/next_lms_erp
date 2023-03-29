<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <link
         rel="stylesheet"
         href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
         integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm"
         crossorigin="anonymous"
         />
      <meta http-equiv="X-UA-Compatible" content="ie=edge" />
      <title>Admission Forms</title>
      <style type="text/css">
         .logo-div {
         height: 125px;
         width: 160px;
         display: block;
         margin-left: auto;
         margin-right: auto;
         }
         #logo {
         padding: 5px;
         height: 100%;
         width: 100%;
         }
         .img-div {
         height: 250px;
         width: 200px;
         background-color: rgb(179, 179, 179);
         color: white;
         margin-top: 5px;
         border-radius: 5px;
         }
         .img-div img {
         height: 100%;
         width: 100%;
         border-radius: 5px;
         }
      </style>
   </head>
   <body>
      <div class="container">
         <!-- Include your partials here -->
         <header>
            <!-- Image and text -->
            <div class="container header text-center">
               <div class="row">
                  <div class="col-lg-3">
                     <div class="logo-div">
                        @if(isset($data['Logo']))
                        <img id="logo" src="{{$data['Logo']}}" alt="Logo" style="height: 200px; width: 200px;" />
                        <link rel="icon" href="{{$data['Logo']}}" sizes="192x192" />
                        @else
                        <img id="logo" src="http://202.47.117.124/admin_dep/images/logo_triz.png" alt="Logo" />
                        <link rel="icon" href="http://202.47.117.124/admin_dep/images/logo_triz.png" sizes="192x192" />
                        @endif
                     </div>
                  </div>
                  <div class="col-lg-9 text-primary">
                     <h2>
                     WELCOME TO
                     <h2>
                     <h2>
                        {{$data['SchoolName']}}
                     </h2>
                     <h2></h2>
                  </div>
               </div>
            </div>
         </header>
         <form
            action="{{route('processOnlineEnquiry')}}"
            method="POST"
            enctype="multipart/form-data"
            >
            @csrf
            <div class="container">
               <hr />
               <div class="text-center">
                  <h3>Admission Form for Class XI</h3>
                  <h5 class="text-danger">
                     CANDIDATE MUST FILL IN THE FORM VERY CAREFULLY AS ONLY ONE FORM CAN BE
                     SUBMITTED.
                  </h5>
               </div>
               <h4>Select your preference</h4>
               <select
                  class="form-control mb-2"
                  id="selectedStream"
                  name="stream"
                  required="required"
                  onchange="streamChanged(this)"
                  >
                  <option selected>* Select Stream...</option>
                  <option label="Science" value="1"></option>
                  <option label="Commerce" value="2"></option>
               </select>
               <select
                  class="form-control mb-2"
                  id="selectedSubjects"
                  name="subjects"
                  required="required"
                  >
                  <option selected>* Select Subject Group...</option>
               </select>
               <h4 class="mt-3">Student Details</h4>
               <h6 class="text-primary">
                  Must be according to class 10 board exam admit card
               </h6>
               <div class="form-row">
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="first_name"
                        placeholder="* First name"
                        required
                        maxlength="75"
                        title="Please enter your first name"
                        />
                  </div>
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="middle_name"
                        placeholder="Middle name"
                        maxlength="75"
                        title="Please enter your middle name"
                        />
                  </div>
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="last_name"
                        placeholder="* Last name"
                        required
                        maxlength="75"
                        title="Please enter your last name"
                        />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="aadhar_number"
                        placeholder="* Aadhar number"
                        required
                        maxlength="12"
                        pattern="[0-9]{12}"
                        title="Please enter exactly 12 digits of aadhar"
                        />
                  </div>
                  <div class="col-md-4">
                     <div class="input-group flex-nowrap">
                        <div class="input-group-prepend">
                           <span class="input-group-text" id="addon-wrapping"
                              >* Date of Birth:</span
                              >
                        </div>
                        <input
                           type="date"
                           class="form-control"
                           placeholder="Date of Birth"
                           id="dob"
                           name="date_of_birth"
                           required
                           title="Enter/Select your date of birth"
                           />
                     </div>
                  </div>
                  <div class="col-md-4">
                     <select
                        id="gender"
                        name="gender"
                        class="form-control"
                        required
                        title="Select gender"
                        >
                        <option selected>* Select gender</option>
                        <option label="Male" value="M"></option>
                        <option label="Female" value="F"></option>
                     </select>
                  </div>
               </div>
               <input
                  type="email"
                  class="form-control"
                  name="email"
                  placeholder="* Email"
                  required
                  maxlength="100"
                  title="Please enter your email correctly"
                  />
               <small class="form-text text-muted">
               Please write correct email, admission confirmation will be sent on
               this.</small
                  >
               <textarea
                  class="form-control mt-2"
                  name="address"
                  id="address"
                  cols="50"
                  rows="6"
                  style="resize: none;"
                  placeholder="* Residential Address"
                  required
                  title="Please enter your complete address"
                  ></textarea>
               <h4 class="mt-3">Parent Details</h4>
               <div class="form-row">
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="father_name"
                        placeholder="* Father's name"
                        required
                        maxlength="75"
                        title="Please enter your father's name"
                        />
                  </div>
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="father_occupation"
                        placeholder="* Father's Occupation"
                        required
                        maxlength="75"
                        title="Please enter your fathers occupation"
                        />
                  </div>
                  <div class="col-md-4">
                     <input
                        type="number"
                        class="form-control"
                        name="mobile"
                        placeholder="* Father's Mobile number"
                        required
                        min="0"
                        maxlength="10"
                        title="Please enter your father's Mobile number"
                        />
                  </div>
               </div>
               <!-- mother's details -->
               <div class="form-row">
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="mother_name"
                        placeholder="* Mother's name"
                        required
                        maxlength="75"
                        title="Please enter mother's name"
                        />
                  </div>
                  <div class="col-md-4">
                     <input
                        type="text"
                        class="form-control"
                        name="mother_occupation"
                        placeholder="* Mother's Occupation"
                        required
                        maxlength="75"
                        title="Please enter your mother's occupation"
                        />
                  </div>
                  <div class="col-md-4">
                     <input
                        type="number"
                        class="form-control"
                        name="mother_mobile_number"
                        placeholder="* Mother's Mobile number"
                        required
                        title="Please enter your mother's Mobile number"
                        min="0"
                        maxlength="10"
                        />
                  </div>
               </div>
               <h4 class="mt-3">Other Details</h4>
               <div class="form-row">
                  <div class="col-md-6">
                     <input
                        type="text"
                        class="form-control"
                        name="previous_school_name"
                        placeholder="* Last School's Name"
                        required
                        maxlength="75"
                        />
                  </div>
                  <div class="col-md-6">
                     <input
                        type="text"
                        class="form-control"
                        name="board"
                        placeholder="* Board for Class X"
                        required
                        maxlength="75"
                        />
                  </div>
               </div>
               <!-- include the new marks section here -->
               <h4 class="mt-3">Enter your class X marks - as per Board's Marksheet</h4>
               <!-- List of subjects, just add subjects here and 
                  it will be added to form and db both -->
               <!-- labels/header row -->
               <div class="form-row">
                  <div class="col-6">
                     <label class="text-primary"><b>Subjects</b></label>
                  </div>
                  <div class="col-4">
                     <label class="text-primary"><b>Marks Obtained</b></label>
                  </div>
                  <div class="col-2">
                     <label class="text-primary"><b>Grade</b></label>
                  </div>
               </div>
               <!-- loop to create rows -->
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject0"
                        name="subject[]" value="English" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks0"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade0" name="grade[]"
                        placeholder="Grade" maxlength="2"  pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject1"
                        name="subject[]" value="Hindi/Sanskrit/Marathi/French." 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks1"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade1" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject2"
                        name="subject[]" value="Mathematics (Std)" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks2"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade2" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject3"
                        name="subject[]" value="Mathematics (Basic)" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks3"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade3" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject11"
                        name="subject[]" value="Mathematics" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks11"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade11" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject4"
                        name="subject[]" value="Science" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks4"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade4" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject5"
                        name="subject[]" value="Social Science" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks5"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade5" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject7"
                        name="subject[]" value="Physical Education" 
                        readOnly />
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks7"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade7" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject8"
                        name="subject[]" value=""  onkeyup="enableMarksAndGrades( 'subject8'
                        ,'marks8' ,'grade8' ) "/>
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks8"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  readOnly  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade8" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" readOnly
                        />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject9"
                        name="subject[]" value=""  onkeyup="enableMarksAndGrades( 'subject9'
                        ,'marks9' ,'grade9' ) "/>
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks9"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  readOnly  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade9" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" readOnly
                        />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject10"
                        name="subject[]" value=""  onkeyup="enableMarksAndGrades( 'subject10'
                        ,'marks10' ,'grade10' ) "/>
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks10"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  readOnly  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade10" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" readOnly
                        />
                  </div>
               </div>
               <div class="form-row">
                  <div class="col-6">
                     <input type="text" class="form-control" id="subject12"
                        name="subject[]" value=""  onkeyup="enableMarksAndGrades( 'subject12'
                        ,'marks12' ,'grade12' ) "/>
                  </div>
                  <div class="col-4">
                     <input type="number" class="form-control" id="marks12"
                        name="marks[]" placeholder="Marks Obtained" min="0" max="100"  readOnly  />
                  </div>
                  <div class="col-2">
                     <input type="text" class="form-control" id="grade12" name="grade[]"
                        placeholder="Grade" maxlength="2" pattern="[A-Za-z0-9+]+" readOnly
                        />
                  </div>
               </div>
               <!-- documents section -->
               <h4 class="mt-3">Upload your documents</h4>
               <h6 class="text-primary">
                  Ensure Name, Class, School, Subjects, Marks Scored, Remarks are distinctly
                  visible in the upload.
               </h6>
               <div class="form-row">
                  <div class="col-md-4">
                     <small class="text-warning font-weight-bold"
                        >* Upload your recent photo (Max. 2mb)</small
                        >
                     <input
                        type="file"
                        class="form-control"
                        name="photo_upload"
                        id="photo_upload"
                        accept="image/*"
                        onChange="previewImage('photo_upload','photo_preview')"
                        required
                        title="Please select scan latest photo to upload"
                        />
                     <div class="img-div">
                        <img id="photo_preview" src="https://media.wired.com/photos/59265616cefba457b07999d7/master/w_2400,c_limit/Google-Phish-TA.jpg" alt="Your photo preview will appear here" />
                     </div>
                  </div>
                  <div class="col-md-4">
                     <small class="text-warning font-weight-bold"
                        >* Upload your mark-sheet (Max. 2mb)</small
                        >
                     <input
                        type="file"
                        class="form-control"
                        name="report_upload"
                        id="report_upload"
                        accept="image/*"
                        onChange="previewImage('report_upload','report_preview')"
                        required
                        title="Please select scanned mark-sheet to upload"
                        />
                     <div class="img-div">
                        <img id="report_preview" src="https://media.wired.com/photos/59265616cefba457b07999d7/master/w_2400,c_limit/Google-Phish-TA.jpg" alt="Your mark-sheet preview will appear here" />
                     </div>
                  </div>
                  <div class="col-md-4">
                     <small class="text-warning font-weight-bold"
                        >Upload any other doc. (Max. 2mb)</small
                        >
                     <input
                        type="file"
                        class="form-control"
                        name="decl_upload"
                        id="decl_upload"
                        accept="image/*"
                        title="Please select scanned document to upload"
                        onChange="previewImage('decl_upload','decl_preview')"
                        />
                     <div class="img-div">
                        <img id="decl_preview" src="https://media.wired.com/photos/59265616cefba457b07999d7/master/w_2400,c_limit/Google-Phish-TA.jpg" alt="Your document preview will appear here" />
                     </div>
                  </div>
               </div>
               <br />
               <hr />
               <br />
               <hr />
               <input
                  type="checkbox"
                  name="chkForm"
                  id="chkForm" required="required"
                  />
               I hereby declare that all the information that has been furnished in the
               application form is true.I agree to the terms of admission. In case
               admission is granted, I promise to abide by the fee structure and the rules
               and regulations of the school, which includes decisions regarding my child /
               ward's general welfare / academics and behaviour..
               <div class="my-3">
               	<input type="hidden" name="sub_institute_id" value="{{$data['Id']}}">
                  <input
                     id="btnSubmit"
                     class="btn btn-primary btn-block"
                     type="submit"
                     value="Submit"
                     />
               </div>
            </div>
         </form>
      </div>
      <!-- Javascript files -->
      <script
         defer
         src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
         integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
         crossorigin="anonymous"
         ></script>
      <script
         defer
         src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
         integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
         crossorigin="anonymous"
         ></script>
      <script
         defer
         src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"
         integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"
         crossorigin="anonymous"
         ></script>
      <script defer src="http://202.47.117.124//scripts/script.js"></script>
   </body>
</html>