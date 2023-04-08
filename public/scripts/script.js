//Enable or Disable Marks and Grades fields...
function enableMarksAndGrades(subjectElem, marksElem, gradeElem) {
  const subjectObj = document.getElementById(subjectElem);
  const marksObj = document.getElementById(marksElem);
  const gradeObj = document.getElementById(gradeElem);
  if (subjectObj.value.length > 0) {
    marksObj.readOnly = false;
    gradeObj.readOnly = false;
  } else {
    marksObj.value = '';
    gradeObj.value = '';
    marksObj.readOnly = true;
    gradeObj.readOnly = true;
  }
}

// load image to the preview image element
function previewImage(inputElem, previewElem) {
  var file = document.getElementById(inputElem).files;
  if (fileSizeValidation(inputElem, previewElem) === false) {
    return;
  }
  if (file.length > 0) {
    var reader = new FileReader();
    reader.onload = function (e) {
      document.getElementById(previewElem).setAttribute('src', e.target.result);
    };
    reader.readAsDataURL(file[0]);
  }
}

// File size validation utility
function fileSizeValidation(elemName, elemImg) {
  // console.log(elemName, elemImg);
  maxSize = 2;
  const fi = document.getElementById(elemName);
  // Check if any file is selected.
  if (fi.files.length > 0) {
    for (const i = 0; i <= fi.files.length - 1; i++) {
      const fsize = fi.files.item(i).size;
      const file = Math.round(fsize / 1024);
      // The size of the file.
      // console.log(file);
      if (file >= maxSize * 1024) {
        alert(`File too Big, please select a file less than ${maxSize} mb`);
        fi.value = '';
        document.getElementById(elemImg).setAttribute('src', '');
        return false;
      } else {
        return true;
      }
    }
  }
}

// this function is fired when school is changed.
function schoolChanged(schoolElemName, streamElemName, subjectElemName) {
  // for preference 1
  const schoolObj = document.getElementById(schoolElemName);
  const streamObj = document.getElementById(streamElemName);
  const subjectObj = document.getElementById(subjectElemName);
  filterStreams(schoolObj, streamObj, streamElemName);

  streamObj.removeAttribute('disabled');
  streamObj.selectedIndex = '0';
  subjectObj.selectedIndex = '0';
  subjectObj.setAttribute('disabled', 'disabled');

  // for preference 2
  const streamObj2 = document.getElementById(streamElemName + 2);
  const subjectObj2 = document.getElementById(subjectElemName + 2);
  filterStreams(schoolObj, streamObj2, streamElemName + 2);
  streamObj2.removeAttribute('disabled');
  streamObj2.selectedIndex = '0';
  subjectObj2.selectedIndex = '0';
  subjectObj2.setAttribute('disabled', 'disabled');

  // for preference 3
  const streamObj3 = document.getElementById(streamElemName + 3);
  const subjectObj3 = document.getElementById(subjectElemName + 3);
  filterStreams(schoolObj, streamObj3, streamElemName + 3);
  streamObj3.removeAttribute('disabled');
  streamObj3.selectedIndex = '0';
  subjectObj3.selectedIndex = '0';
  subjectObj3.setAttribute('disabled', 'disabled');
}

//  this function is fired when the stream is changed
function streamChanged() {
  var x = document.getElementById("selectedStream").value;

  var r = '';
  if(x == 1)
  {
    r = '<option label="English Core, Physics, Chemistry, Biology and Computer Science" value="English Core, Physics, Chemistry, Biology and Computer Science"></option><option label="English Core, Physics, Chemistry, Biology and Enterepreneurship" value="English Core, Physics, Chemistry, Biology and Enterepreneurship"></option><option label="English Core, Physics, Chemistry, Maths and Computer Science" value="English Core, Physics, Chemistry, Maths and Computer Science"></option><option label="English Core, Physics, Chemistry, Maths and Enterepreneurship" value="English Core, Physics, Chemistry, Maths and Enterepreneurship"></option>';
  }else{
    r = '</option><option label="English Core, Accountancy, Business Studies, Economics and Enterepreneurship" value="English Core, Accountancy, Business Studies, Economics and Enterepreneurship"></option>';
  }

  document.getElementById("selectedSubjects").innerHTML = r;
}

//  this function helps filter the streams based on school selected
function filterStreams(schoolObj, streamObj, streamElemName) {
  Array.from(document.querySelector('#' + streamElemName).options).forEach(
    (option_element) => {
      if (
        option_element.getAttribute('data-schoolid') == schoolObj.value ||
        option_element.getAttribute('data-schoolid') === null
      ) {
        option_element.hidden = false;
      } else {
        option_element.hidden = true;
      }
    }
  );
}

// function enable/disable submit button when declaration is checked
function DeclareClicked() {
  var chkbox = document.getElementById('chkForm');
  var btnSubmit = document.getElementById('btnSubmit');
  if (chkbox.checked) {
    btnSubmit.disabled = false;
  } else {
    btnSubmit.disabled = true;
  }
}

// main function to validate form before submission
function validateForm(e) {
  // console.log("Form Validate Event");
  if (isSchoolDataOK() === false) {
    e.preventDefault();
  }

  if (isGenderOK() === false) {
    e.preventDefault();
  }

  if (isUploadOk() === false) {
    e.preventDefault();
  }

  if (chkDuplicatePref() === false) {
    e.preventDefault();
  }
}

// school data validation
function isSchoolDataOK() {
  var school = document.getElementById('selectedSchool');
  var stream = document.getElementById('selectedStream');
  var subject = document.getElementById('selectedSubjects');
  if (
    school.selectedIndex <= 0 ||
    stream.selectedIndex <= 0 ||
    subject.selectedIndex <= 0
  ) {
    alert('Please select all -> School, Stream & Subject List');
    return false;
  }
}

// gender required checking
function isGenderOK() {
  var gender = document.getElementById('gender');
  if (gender.selectedIndex <= 0) {
    alert('Please select gender');
    return false;
  }
}

// documents upload validation
function isUploadOk() {
  var picFile = document.getElementById('photo_upload');
  var repFile = document.getElementById('report_upload');
  var decFile = document.getElementById('decl_upload');
  if (
    picFile.value === null ||
    picFile.value === '' ||
    repFile.value === null ||
    repFile.value === ''
  ) {
    alert('Please select your photo and documents for uploading');
    return false;
  } else {
    // check for valid extensions
    if (ValidateFileExt(document) === false) {
      return false;
    }
  }
}

// function to check duplicate streams
function chkDuplicatePref() {
  var stream = document.getElementById('selectedStream');
  var stream2 = document.getElementById('selectedStream2');
  var stream3 = document.getElementById('selectedStream3');
  var subject2 = document.getElementById('selectedSubjects2');
  var subject3 = document.getElementById('selectedSubjects3');
  //Stream 2 is selected, check with stream1 and stream3 for duplicacy
  if (stream2.selectedIndex > 0) {
    if (
      stream2.selectedIndex === stream.selectedIndex ||
      stream2.selectedIndex === stream3.selectedIndex
    ) {
      alert(
        'Please avoid selecting duplicate stream in second/third preference'
      );
      return false;
    }
    //also check if subject is selected or not
    if (subject2.selectedIndex <= 0) {
      alert('Please select subjects from the list in 2nd Preference');
      return false;
    }
  }
  //Stream 3 is selected, check with stream1 and stream3 for duplicacy
  if (stream3.selectedIndex > 0) {
    if (
      stream3.selectedIndex === stream.selectedIndex ||
      stream3.selectedIndex === stream2.selectedIndex
    ) {
      alert(
        'Please avoid selecting duplicate stream in second/third preference'
      );
      return false;
    }
    //also check if subject is selected or not
    if (subject3.selectedIndex <= 0) {
      alert('Please select subjects from the list in 3rd Preference');
      return false;
    }
  }
}

// function to check only valid files are selected for upload
var _validFileExtensions = ['.jpg', '.jpeg', '.bmp', '.gif', '.png'];
function ValidateFileExt(oForm) {
  var arrInputs = oForm.getElementsByTagName('input');
  for (var i = 0; i < arrInputs.length; i++) {
    var oInput = arrInputs[i];
    if (oInput.type == 'file') {
      var sFileName = oInput.value;
      if (sFileName.length > 0) {
        var blnValid = false;
        for (var j = 0; j < _validFileExtensions.length; j++) {
          var sCurExtension = _validFileExtensions[j];
          if (
            sFileName
              .substr(
                sFileName.length - sCurExtension.length,
                sCurExtension.length
              )
              .toLowerCase() == sCurExtension.toLowerCase()
          ) {
            blnValid = true;
            break;
          }
        }

        if (!blnValid) {
          alert(
            'Sorry, ' +
              sFileName +
              ' is invalid, allowed extensions are: ' +
              _validFileExtensions.join(', ')
          );
          return false;
        }
      }
    }
  }

  return true;
}
