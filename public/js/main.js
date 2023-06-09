// Mattermost User Merge Tool

// Copyright (c) 2023 Maxwell Power
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without
// restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom
// the Software is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
// AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
// ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

function confirmSubmit(event) {
    const result = confirm("Are you sure you want to merge these users?");
    if (!result) {
        event.preventDefault(); // Prevents the form from being submitted
    }
}

// Attach event listener to the form
const usersForm = document.getElementById("users");
usersForm.addEventListener("submit", confirmSubmit);

function disableDuplicateUser() {
    const oldUserSelect = document.getElementById("old_user_id");
    const newUserSelect = document.getElementById("new_user_id");

    const selectedUserId = oldUserSelect.value;

    // Disable new_user_id if no old_user_id is selected
    newUserSelect.disabled = selectedUserId === "";

    for (let i = 0; i < newUserSelect.options.length; i++) {
        const option = newUserSelect.options[i];
        option.disabled = option.value === selectedUserId;
    }

    newUserSelect.options[0].disabled = true;
}

// Attach event listener to the old_user select box
const oldUserSelect = document.getElementById("old_user_id");
oldUserSelect.addEventListener("change", disableDuplicateUser);

// Initialize the disabled state of new_user_id select box on page load
disableDuplicateUser();

function toggleFields() {
    const newUserIdSelect = document.getElementById("new_user_id");
    const forceUsernameCheckbox = document.getElementById("force_username_checkbox");
    const forceUsernameInput = document.getElementById("force_username");
    const forceEmailCheckbox = document.getElementById("force_email_checkbox");
    const forceEmailInput = document.getElementById("force_email");
    const forceauthdataCheckbox = document.getElementById("force_authdata_checkbox");
    const submitButton = document.getElementById("submit");

    const isUserSelected = newUserIdSelect.value !== ""; // Check if a user is selected

    // Enable/disable the checkboxes based on user selection
    forceUsernameCheckbox.disabled = !isUserSelected;
    forceEmailCheckbox.disabled = !isUserSelected;
    forceauthdataCheckbox.disabled = !isUserSelected;
    submitButton.disabled = !isUserSelected;

    // Enable/disable the checkboxes based on user selection
    forceUsernameCheckbox.disabled = !isUserSelected;
    forceEmailCheckbox.disabled = !isUserSelected;

    // Toggle display and disable/enable state of the input fields based on checkbox and user selection
    forceUsernameInput.style.display = forceUsernameCheckbox.checked && isUserSelected ? "block" : "none";
    forceUsernameInput.disabled = !(forceUsernameCheckbox.checked && isUserSelected);

    forceEmailInput.style.display = forceEmailCheckbox.checked && isUserSelected ? "block" : "none";
    forceEmailInput.disabled = !(forceEmailCheckbox.checked && isUserSelected);
}

// Attach event listeners to the checkboxes and new_user_id select box
const forceUsernameCheckbox = document.getElementById("force_username_checkbox");
forceUsernameCheckbox.addEventListener("change", toggleFields);

const forceEmailCheckbox = document.getElementById("force_email_checkbox");
forceEmailCheckbox.addEventListener("change", toggleFields);

const newUserIdSelect = document.getElementById("new_user_id");
newUserIdSelect.addEventListener("change", toggleFields);

// Initialize the fields' state on page load
toggleFields();

function populateFields() {
    const newUserSelect = document.getElementById("new_user_id");
    const forceEmailInput = document.getElementById("force_email");
    const forceUsernameInput = document.getElementById("force_username");

    const selectedOption = newUserSelect.options[newUserSelect.selectedIndex];
    const selectedEmail = selectedOption.dataset.email;
    const selectedUsername = selectedOption.dataset.username;

    forceEmailInput.value = selectedEmail;
    forceUsernameInput.value = selectedUsername;
}

// Attach event listener to the new_user_id select box
const newUserSelect = document.getElementById("new_user_id");
newUserSelect.addEventListener("change", populateFields);
