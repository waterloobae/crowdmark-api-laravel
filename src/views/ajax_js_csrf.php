<script>
    // Fetch CSRF token on page load
fetch('{_PATH}/AjaxHandler.php?csrf=true')
  .then(response => response.json())
  .then(data => {
      document.getElementById('csrf_token').value = data.csrf_token;
  });
document.getElementById('crowdmarkDashboardForm').addEventListener('submit', function (event) {
  createHiddenControl();
  console.log('Form submitted');
  sendAjaxRequest(event, 'crowdmarkDashboardForm');
  deleteHiddenControl()
});

function createHiddenControl() {
  const form = document.getElementById("crowdmarkDashboardForm");
  const chips = document.querySelectorAll("md-filter-chip");
  const selectedChips = [];
  chips.forEach(chip => {
      const chipValue = chip.getAttribute("label");
      //alert(chip.selected);              
      //alert(chip.getAttribute("selected"));
      const isSelected = chip.selected;
  
      if (isSelected) {
          // Add selected chip to the array
          selectedChips.push(chipValue);
      }
  }); // Added missing parenthesis

  const hiddenControl = document.createElement('input');
  hiddenControl.type = 'hidden';
  hiddenControl.name = 'selectedChips';
  hiddenControl.value = JSON.stringify(selectedChips);  // Post as JSON array
  //alert(hiddenControl.value);
  form.appendChild(hiddenControl);
}
    
function deleteHiddenControl() {
  const form = document.getElementById("crowdmarkDashboardForm");
  const hiddenControl = form.querySelector('input[name="selectedChips"]');
  if (hiddenControl) {
      form.removeChild(hiddenControl);
  }
}

function sendAjaxRequest(event, formId) {
  // Prevent the default form submission
  event.preventDefault();
  const form2 = document.getElementById("crowdmarkDashboardForm");
  const selectedChips = form2.querySelector('input[name="selectedChips"]').value;
  if (selectedChips === '[]') {
    alert('No courses selected. Please select at least one course.');
    return;
  }
  document.getElementById('crowdmarkDashboard_response').innerHTML = '<h2>Loading...</h2>';
  
  const xhr = new XMLHttpRequest();
  
  // Construct the URL with the query string
  const url = `{_PATH}/AjaxHandler.php`;
  xhr.open('POST', url, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onreadystatechange = function() {
      if (xhr.readyState === 4 && xhr.status === 200) {
          console.log(xhr.responseText);
          let response;
            try {
              response = JSON.parse(xhr.responseText);
            } catch (e) {
              response = xhr.responseText;
            }

            if (response === null) {
            response = '';
            } else if (typeof response === 'string' && response.endsWith('null')) {
            response = response.slice(0, -4);
            }

          if (response.status === 'success') {
              //document.getElementById('response').innerText = response.data;
              document.getElementById('crowdmarkDashboard_response').innerHTML = response;                
          } else {
              //document.getElementById('response').innerText = response.message;
              document.getElementById('crowdmarkDashboard_response').innerHTML = response;
          }
      }
  };
   // Get the form by its ID, which is passed as a parameter
   const form = document.getElementById(formId);
   if (!form) {
       console.error(`Form with id "${formId}" not found.`);
       return;
   } 
   const formData = new FormData(form);

   // Append the action parameter to the form data
   // formData.append('action', action);

   // Convert FormData to URL-encoded string for the POST request
   const params = new URLSearchParams(formData).toString();
   //console.log(params);
  xhr.send(params);
}

// Example call to the function with action and form ID as parameters
// sendAjaxRequest('getData', 'myForm');

</script>