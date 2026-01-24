
// Utility to show validation error message
function showError(message, element) {
    element.textContent = message;
    element.style.display = 'block';
}

// Utility to clear validation error message
function clearError(element) {
    element.textContent = '';
    element.style.display = 'none';
}

// Validate if a field is not empty
function validateNotEmpty(field, message, errorElement) {
    const value = field.value;
    if (!value) {
        showError(message, errorElement);
        return false;
    }
    clearError(errorElement);
    return true;
}

// Validate no double spaces in the input
function validateNoDoubleSpaces(field, errorElement) {
    const value = field.value;

    // Check for spaces at the beginning or end
    if (/^\s|\s$/.test(value)) {
        showError('Input must not start or end with spaces.', errorElement);
        return false;
    }

    // Check for double spaces anywhere in the input
    if (/ {2,}/.test(value)) {
        showError('Double spaces are not allowed.', errorElement);
        return false;
    }

    clearError(errorElement);
    return true;
}


// Validate username length and format
function validateUsernameLength(usernameField, errorElement) {
    const value = usernameField.value;
    if (value.length < 3 || value.length > 12) {
        showError('Username must be between 3 and 12 characters.', errorElement);
        return false;
    }
    if (/[A-Z]/.test(value)) {
        showError('No upperscase letters are allowed.', errorElement);
        return false;
    }
    if (/^[^a-z0-9]/.test(value)) {
        showError('Username cannot start with a special character.', errorElement);
        return false;
    } 
    if (/^\d/.test(value)) {
        showError('Username cannot start with a number.', errorElement);
        return false;
    }
    
    if (/  /.test(value)) {
        showError('Double spaces are not allowed.', errorElement);
        return false;
    }
    
    if (/(.)\1{2,}/.test(value)) {
        showError(middleInitialField, 'No three consecutive duplicate letters allowed.');
        return false;
    }
    if (/(\.\.)/.test(value)) {
        showError('User cannot contain consecutive dots.', errorElement);
        return false;
    }
    
    if (!/^[a-zA-Z0-9_.]*$/.test(value)) {
        showError('Username can only contain letters, digits, underscores, and dots.', errorElement);
        return false;
    }
    clearError(errorElement);
    return true;
}

function validateEmail(emailField, errorElement) {
    const value = emailField.value;

    // Clear existing error initially
    clearError(errorElement);

    // Check if email is empty
    if (value === '') {
        showError('Email is required.', errorElement);
        return false;
    }
    if (/^\s|\s\s|\s$/.test(value)) {
        showError('Email cannot have leading, trailing, or double spaces.', errorElement);
        return false;
    }
    // Validate email length
    if (value.length < 5 || value.length > 50) {
        showError('Email must be between 5 and 50 characters.', errorElement);
        return false;
    }
    
    if (/(.)\1{2,}/.test(value)) {
        showError(middleInitialField, 'No three consecutive duplicate letters allowed.');
        return false;
    }
    if (/(\.\.)/.test(value)) {
        showError('Email cannot contain consecutive dots.', errorElement);
        return false;
    }
    if (/^\d/.test(value)) {
        showError('Email cannot start with a number.', errorElement);
        return false;
    }
    if (/[A-Z]/.test(value)) {
        showError('No upperscase letters are allowed.', errorElement);
        return false;
    }
    
    if (/^[^a-z0-9]/.test(value)) {
        showError('Email cannot start with a special character.', errorElement);
        return false;
    }
    
    
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailPattern.test(value)) {
        showError('Please enter a valid email address.', errorElement);
        return false;
    }

    // If all checks pass, return true
    return true;
}


// Asynchronous validation for username existence
async function checkUsernameExists(usernameField, errorElement) {
    const value = usernameField.value;
    try {
        const response = await fetch('checkCredentials.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 'username': value }),
        });
        const data = await response.json();
        if (data.usernameExists) {
            showError('Username already taken.', errorElement);
            return false;
        }
        
        clearError(errorElement);
        return true;
    } catch (error) {
        console.error('Error checking username:', error);
        return false;
    }
}

// Asynchronous validation for email existence
async function checkEmailExists(emailField, errorElement) {
    const value = emailField.value;
    try {
        const response = await fetch('checkCredentials.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 'email': value }),
        });
        const data = await response.json();
        if (data.emailExists) {
            showError('Email already taken.', errorElement);
            return false;
        }
        clearError(errorElement);
        return true;
    } catch (error) {
        console.error('Error checking email:', error);
        return false;
    }
}

