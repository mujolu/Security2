document.addEventListener('DOMContentLoaded', function () {
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('repassword');
    const strengthMessage = document.getElementById('passwordStrengthMessage');
    const matchMessage = document.getElementById('passwordMatchMessage');
    const form = document.querySelector('form'); // Ensure this matches your form's selector

    // Disable confirm password field by default
    confirmPasswordField.disabled = true;

    // Function to check password strength
    function checkPasswordStrength(password) {
        let strength = 'Weak';
        const hasUpper = /[A-Z]/.test(password); // At least one uppercase letter
        const hasLower = /[a-z]/.test(password); // At least one lowercase letter
        const hasDigit = /\d/.test(password); // At least one digit
        const hasSpecial = /[\W_]/.test(password); // At least one special character
        const isValidLength = password.length >= 8 && password.length <= 20; // Length validation

        // Strong: valid length, upper, lower, digit, and special character
        if (isValidLength && hasUpper && hasLower && hasDigit && hasSpecial) {
            strength = 'Strong';
        } 
        // Medium: valid length and at least 3 of the criteria met
        else if (
            isValidLength &&
            ((hasUpper && hasLower && hasDigit) ||
                (hasUpper && hasLower && hasSpecial) ||
                (hasLower && hasDigit && hasSpecial))
        ) {
            strength = 'Medium';
        }

        return strength;
    }

    // Function to validate password rules
    function isValidPassword(password) {
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasDigit = /\d/.test(password);
        const hasSpecial = /[\W_]/.test(password);
        const isValidLength = password.length >= 8 && password.length <= 20;

        // Allow passwords that are valid in length and characters
        return isValidLength && (hasUpper || hasLower || hasDigit || hasSpecial);
    }

    // Function to check if passwords match
    function checkPasswordMatch() {
        if (passwordField.value !== confirmPasswordField.value) {
            matchMessage.textContent = 'Passwords do not match!';
            matchMessage.style.color = 'red';
            return false;
        } else {
            matchMessage.textContent = 'Passwords match.';
            matchMessage.style.color = 'green';
            return true;
        }
    }

    // Event listener for password input
    passwordField.addEventListener('input', function () {
        const password = passwordField.value;

        // Validate password rules
        if (!isValidPassword(password)) {
            strengthMessage.textContent = 'Password must be 8–20 characters long and include at least one of the following: uppercase letter, lowercase letter, digit, or special character.';
            strengthMessage.style.color = 'red';
            confirmPasswordField.disabled = true; // Disable confirm password field
        } else {
            const strength = checkPasswordStrength(password);
            strengthMessage.textContent = `Password strength: ${strength}`;
            confirmPasswordField.disabled = false; // Enable confirm password field
            if (strength === 'Weak') {
                strengthMessage.style.color = 'red';
            } else if (strength === 'Medium') {
                strengthMessage.style.color = 'orange';
            } else {
                strengthMessage.style.color = 'green';
            }
        }

        // Check if passwords match
        if (!confirmPasswordField.disabled) {
            checkPasswordMatch();
        }
    });

    // Event listener for confirm password input
    confirmPasswordField.addEventListener('input', function () {
        checkPasswordMatch();
    });

    // Function to validate all fields in the form
    function validateAllFields() {
        let allFieldsFilled = true;
        const fields = form.querySelectorAll('[required]'); // Select all required fields

        fields.forEach((field) => {
            const messageElement = document.createElement('div');
            const parent = field.parentNode;
            if (!parent.querySelector('.error-message')) {
                messageElement.className = 'error-message';
                messageElement.style.color = 'red';
                parent.appendChild(messageElement);
            }
            const errorMessage = parent.querySelector('.error-message');
            if (!field.value.trim()) {
                errorMessage.textContent = `${field.name || 'This field'} is required.`;
                allFieldsFilled = false;
            } else {
                errorMessage.textContent = '';
            }
        });

        return allFieldsFilled;
    }

    // Allow form submission only if all validations pass
    form.addEventListener('submit', function (event) {
        // Validate all fields
        if (!validateAllFields()) {
            event.preventDefault(); // Prevent form submission if any field is empty
            return;
        }

        // Check if passwords match
        if (!checkPasswordMatch()) {
            event.preventDefault(); // Prevent form submission
            return;
        }

        // Check if password meets the validation rules
        if (!isValidPassword(passwordField.value)) {
            strengthMessage.textContent = 'Password must be 8–20 characters long and include at least one of the following: uppercase letter, lowercase letter, digit, or special character.';
            strengthMessage.style.color = 'red';
            event.preventDefault(); // Prevent form submission
        }
    });
});
