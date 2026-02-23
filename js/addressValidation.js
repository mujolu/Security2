
document.addEventListener('DOMContentLoaded', function () {
    // credentials email/paass
    const usernameField = document.getElementById('username');
    const emailField = document.getElementById('email');
    const usernameError = document.getElementById('usernameError');
    const emailError = document.getElementById('emailError');

    // peersonal details and address
    const purokField = document.getElementById('purok');
    const barangayField = document.getElementById('barangay');
    const cityField = document.getElementById('city');
    const provinceField = document.getElementById('province');
    const countryField = document.getElementById('country');
    const zipCodeField = document.getElementById('zip_code');
    const form = document.getElementById('registrationForm');
    const id = document.getElementById('id');
    const first_name = document.getElementById('first_name');
    const middle_initial = document.getElementById('middle_initial');
    const last_name = document.getElementById('last_name');
    const extension_name = document.getElementById('extension_name');
    const birthdate = document.getElementById('birthdate');
    const age = document.getElementById('age');

    // Password Validation
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('repassword');
    const strengthMessage = document.getElementById('passwordStrengthMessage');
    const matchMessage = document.getElementById('passwordMatchMessage');

    // Only proceed if required form fields exist (i.e., we're on a registration page)
    if (!confirmPasswordField || !passwordField) {
        return;
    }

    confirmPasswordField.disabled = true;


    // `checkCredentials.js` is loaded via a script tag in the page; its functions
    // are expected to be available before this script runs.

    // Function to show inline error message
    function showError(field, message) {
        let errorElement = field.nextElementSibling;
        
        // If the error element doesn't exist, create it
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            field.parentNode.appendChild(errorElement);
        }
    
        // Apply error message
        errorElement.textContent = message;
        
        // Apply styling (make sure it's red)
        errorElement.style.color = 'red';  // Make error message text red
    }
    
    // Function to clear inline error message
    function clearError(field) {
        const errorElement = field.nextElementSibling;
        if (errorElement && errorElement.classList.contains('error-message')) {
            errorElement.textContent = '';
        }
    }

    // Generic function to check if the field is empty
    function checkIfEmpty(field) {
        const value = field.value;
        clearError(field);
        if (value === '') {
            showError(field, 'This field is required.');
            return false;
        }
        return true;
    }

    // ID  -----------------------------------------------------------------------------------------
    async function validateID() {
        const idField = document.getElementById('id');
        const idValue = idField.value.trim(); // Trim to remove unnecessary whitespace
    
        // Clear any existing errors
        clearError(idField);
    
        // Validate that the ID is not empty
        if (idValue === '') {
            showError(idField, 'ID is required.');
            return false;
        }
    
        // Validate format
        if (/[a-zA-Z]/.test(idValue)) {
            showError(idField, 'ID should not contain letters.');
            return false;
        }
        if (idValue.length !== 9) {
            showError(idField, 'ID must be exactly 9 characters long, including the dash.');
            return false;
        }
    
        if (!/^\d{4}-\d{4}$/.test(idValue)) {
            showError(idField, 'ID must be in the format XXXX-XXXX.');
            return false;
        }
    
        if (/\s{2,}/.test(idValue)) {
            showError(idField, 'No double spaces are allowed.');
            return false;
        }
    
        
    
        // Server-side validation
        try {
            const response = await fetch('check_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ id: idValue }).toString(),
            });
    
            if (!response.ok) {
                showError(idField, 'Error validating ID. Please try again.');
                return false;
            }
    
            const result = await response.json();
            if (result.status === 'error') {
                showError(idField, result.message || 'ID is not valid.');
                return false;
            }
    
            // ID is valid
            clearError(idField);
            return true;
    
        } catch (error) {
            showError(idField, 'Error connecting to the server.');
            return false;
        }
    }
    
    // FIRSTNAME, LASTNAME, CITY, PROVINCE AND COUNTRY  -----------------------------------------------------------------------------------------
    function validateEnhancedCharacterField(field) {
        if (!checkIfEmpty(field)) return false;

        const value = field.value;
        if (value.length < 2 || value.length > 20) {
            showError(field, 'Must be 2-20 characters.');
            return false;
        }
        if (/\d/.test(value)) {
            showError(field, 'No numbers allowed.');
            return false;
        }
        if (/^\s|\s$/.test(value)) {
            showError(field, 'No double spaces allowed.');
            return false;
        }
        if (/\s{2,}/.test(value)) {
            showError(field, 'No double spaces allowed.');
            return false;
        }
        
        const words = value.split(' ');
        const invalidWords = words
            .map((word, index) => ({
                word,
                index: index + 1, // To represent "Firstname", "Secondname", etc.
                isValid: /^[A-Z][a-z]*$/.test(word)
            }))
            .filter(wordObj => !wordObj.isValid);

            if (invalidWords.length > 0) {
                const errorMessages = invalidWords.map(wordObj => {
                    // Construct specific error message for each invalid word
                    return `Name  (${wordObj.word}) should start with a capital letter followed by lowercase letters only, not capital letters.`;
                }).join(' ');

                showError(field, errorMessages);
                return false;
            }
        
        if (/(.)\1{2,}/.test(value)) {
            showError(field, 'No three consecutive duplicate letters are allowed.');
            return false;
        }
        if (/[^a-zA-Z\s]/.test(value)) {
            showError(field, 'No special characters are allowed.');
            return false;
        }
        return true;
    }

    // MIDDLE INITIAL  -----------------------------------------------------------------------------------------
    function validateMiddleInitial() {
        const middleInitialField = document.getElementById('middle_initial');
        const value = middleInitialField.value;
    
        // Clear any existing errors
        clearError(middleInitialField);
    
        // Allow the field to be empty (optional field)
        if (value === '') {
            return true; // Valid if empty
        }
        
        if (/[^a-zA-Z\s]/.test(value)) {
            showError(middleInitialField, 'No special characters are allowed.');
            return false;
        }
        if (/\d/.test(value)) {
            showError(middleInitialField, 'No numbers allowed.');
            return false;
        }
        if (/\s{2,}/.test(value)) {
            showError(middleInitialField, 'No double spaces allowed.');
            return false;
        }
        
        
        return true; // Passes all validations
    }

    // EXTENSION NAME -----------------------------------------------------------------------------------------
    function validateExtensionName() {
        const extensionNameField = document.getElementById('extension_name');
        const value = extensionNameField.value.trim();
    
        // Clear any existing errors
        clearError(extensionNameField);
    
        // Allow the field to be empty (optional)
        if (value === '') {
            return true; // Valid if empty
        }
    
        // Regex to validate Jr., Sr., or valid uppercase Roman numerals
        const validExtensions = /^(Jr|Sr|(?:M{0,3})(?:CM|CD|D?C{0,3})(?:XC|XL|L?X{0,3})(?:IX|IV|V?I{0,3}))$/;
    
        // Check if the value matches the allowed formats
        if (!validExtensions.test(value)) {
            showError(extensionNameField, 'Must be Jr, Sr, or a valid uppercase Roman numeral.');
            return false;
        }
    
        return true; // Valid if it matches the regex
    }

    // CALCULATE AGE -----------------------------------------------------------------------------------------
    function calculateAge() {
        const birthdateField = document.getElementById('birthdate');
        const ageField = document.getElementById('age');
    
        // Get the birthdate value
        const birthdateValue = birthdateField.value.trim();
    
        // Clear existing error
        clearError(birthdateField);
    
        if (!birthdateValue) {
            showError(birthdateField, 'Birthdate is required.');
            return;
        }
    
        // Calculate the age based on the birthdate
        const birthdate = new Date(birthdateValue);
        const today = new Date();
        let age = today.getFullYear() - birthdate.getFullYear();
    
        // Adjust for cases where the birth month/day hasn't been reached yet
        const monthDiff = today.getMonth() - birthdate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
    
        // Set the age value in the age input field
        ageField.value = age;
    
        // Validate if the user is at least 18 years old
        if (age < 18) {
            showError(birthdateField, 'You must be at least 18 to register.');
        } else {
            clearError(birthdateField);
        }
    }
    document.getElementById('birthdate').addEventListener('change', calculateAge);

    // PUROK AND BARANGAY  -----------------------------------------------------------------------------------------
    function validatePurokBarangayField(field) {
        if (!checkIfEmpty(field)) return false;

        
        const value = field.value;
        if (value.length < 1 || value.length > 20) {
            showError(field, 'Must have 1-20 characters.');
            return false;
        }
        
        if (!/^[A-Za-z0-9\s-]+$/.test(value)) {
            showError(field, 'No special characters except dashes are allowed.');
            return false;
        }
        if (!/^[A-Z0-9]/.test(value)) {
            showError(field, 'The input must start with a capital letter or a number.');
            return false;
        }        
        if (/^\s|\s$/.test(value)) {
            showError(field, 'No double spaces allowed.');
            return false;
        }
        

        if (/(.)\1{2,}/.test(value)) {
            showError(field, 'No 3 consecutive duplicate letters.');
            return false;
        }

        const words = value.split(' ');
        // Check if the input contains a mix of letters and numbers within a single word
        if (words.some(word => 
            /[a-zA-Z]/.test(word) && // Contains letters
            /\d/.test(word) &&       // Contains numbers
            !/^[a-zA-Z]?-?\d+$/.test(word) // Doesn't match valid formats like "P1", "P-2", "P 1"
        )) {
            showError(field, 'Cannot start with a number.');
            return false;
        }

        // 'Must be in this format P1, P 1, P-2, a number only, or letters only.
        // const words = value.split(' ');
const invalidWords = words
    .map((word, index) => ({
        word,
        index: index + 1, // To represent "Firstname", "Secondname", etc.
        isValid: 
            (word.length === 1 && /^[0-9]$/.test(word)) || // Allow a single digit
            (/^[A-Z]+$/.test(word)) // Word starts with a capital letter and is all uppercase letters
    }))
    .filter(wordObj => !wordObj.isValid);

if (invalidWords.length > 0) {
    const errorMessages = invalidWords.map(wordObj => {
        // Construct specific error message for each invalid word
        return `Name (${wordObj.word}) should start with a capital letter, and all following letters must also be capital letters (no lowercase letters allowed).`;
    }).join(' ');

    showError(field, errorMessages);
    return false;
}

    


        if (/\s{2,}/.test(value)) {
                showError(field, 'No double spaces are allowed.');
                return false;
            }
        
        if (/\d{3,}/.test(value)) {
            showError(field, 'No 3 consecutive digits are allowed.');
            return false;
        }

        
        if (/(.)\1{2,}/.test(value)) {
            showError(field, 'No 3 consecutive duplicate letters.');
            return false;
        }
        
        return true;
    }

    // ZIPCODE  -----------------------------------------------------------------------------------------
    function validateZipCode(field) {
        if (!checkIfEmpty(field)) return false;
  

        const value = field.value;
        if (/\s/.test(value)) {
            showError(field, 'Zip code must not contain spaces.');
            return false;
        }
        if (/[a-zA-Z]/.test(value)) { 
            showError(field, 'Zip code must not contain letters.');
            return false;
        }
        if (/[^0-9]/.test(value)) {
            showError(field, 'No special characters are allowed.');
            return false;
        }
        
        if (value.length !== 5) {
            showError(field, 'Zip code must be exactly 5 characters long.');
            return false;
        }
        
        return true;
    }

    // EMAIL AND USERNAME -----------------------------------------------------------------------------------------------
        usernameField.addEventListener('blur', async function () {
            if (
                validateNotEmpty(usernameField, 'Username is required.', usernameError) &&
                validateNoDoubleSpaces(usernameField, usernameError)&&
                validateUsernameLength(usernameField, usernameError)
            ) {
                await checkUsernameExists(usernameField, usernameError);
            }
        });

        emailField.addEventListener('blur', async function () {
            // First, ensure the field is not empty and has no double spaces
            const isNotEmpty = validateNotEmpty(emailField, 'Email is required.', emailError);
            const noDoubleSpacesInEmail = validateNoDoubleSpaces(emailField, emailError);
            const isValidEmailFormat = validateEmail(emailField, emailError);
        
            if (isNotEmpty && noDoubleSpacesInEmail && isValidEmailFormat) {
                // Only check if email exists if all validation checks pass
                await checkEmailExists(emailField, emailError);
            }
        });
    

    // Handle form submission -----------------------------------------------------------------------------------------------
    async function handleSubmit(event) {
        event.preventDefault(); // Prevent form submission by default
        let isValid = true; // Start with valid form assumption
    
        // Validate username
        const isUsernameValid = await checkUsernameExists(usernameField, usernameError);
        const isNotEmpty = validateNotEmpty(usernameField, 'Username is required.', usernameError);
        const noDoubleSpaces = validateNoDoubleSpaces(usernameField, usernameError);
        const isValidLength = await validateUsernameLength(usernameField, usernameError);
    
        if (!(isUsernameValid && isNotEmpty && noDoubleSpaces && isValidLength)) {
            isValid = false;
        }
    
        // Validate email using the validateEmail function
        const isValidEmailFormat = validateEmail(emailField, emailError); // Email format check
        const noDoubleSpacesInEmail = validateNoDoubleSpaces(emailField, emailError); // Check for double spaces in email
        let isEmailValid = false;
    
        if (isValidEmailFormat && noDoubleSpacesInEmail) {
            // If both format and no double spaces are valid, check if email exists
            isEmailValid = await checkEmailExists(emailField, emailError);
        }
    
        // If email is invalid, or double spaces exist, set the form as invalid
        if (!isValidEmailFormat || !isEmailValid || !noDoubleSpacesInEmail) {
            isValid = false;
        }
    
        // Calculate and validate age
        calculateAge(); // Assuming you have a function to calculate age
        const ageValue = parseInt(age.value, 10);
        if (isNaN(ageValue) || ageValue < 18) {
            isValid = false;
        }
    
        // Validate address fields (Purok and Barangay)
        if (!validatePurokBarangayField(purokField)) isValid = false;
        if (!validatePurokBarangayField(barangayField)) isValid = false;
    
        // Validate other fields with enhanced character validation
        const fields = [cityField, provinceField, countryField, first_name, last_name];
        fields.forEach(field => {
            if (!validateEnhancedCharacterField(field)) {
                isValid = false;
            }
        });
    
        const zipCodeField = document.getElementById('zip_code');
    
        // Validate the zip code field
        if (!validateZipCode(zipCodeField)) {
            isValid = false;
        }
    
        if (!validateMiddleInitial()) isValid = false;
        if (!validateExtensionName()) isValid = false;
        if (!validateID()) isValid = false;
        
        // If all validations pass, submit the form
        if (isValid) {
            form.submit();
        } else {
            // If validation fails, alert or show a message (optional)
            alert("Please correct the errors in the form before submitting.");
        }
    }
    
    // Attach the handleSubmit function to the form's submit event
    form.addEventListener('submit', handleSubmit);
    
}); 