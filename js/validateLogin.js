document.addEventListener('DOMContentLoaded', function () {
    // Prevent back button navigation
    window.history.pushState(null, null, window.location.href);
   
   // setInterval(function () {
        //window.history.pushState(null, null, window.location.href);
    //}, 100);


    window.onpopstate = function () {
        window.history.pushState(null, null, window.location.href);
    };

    async function fetchData(endpoint, bodyData) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(bodyData),
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error(`Error fetching ${endpoint}:`, error);
            return null;
        }
    }

    // Select DOM elements
    const usernameField = document.getElementById('username');
    const passwordField = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const form = document.getElementById('loginForm');
    const usernameError = document.getElementById('usernameError');
    const passwordError = document.getElementById('passwordError');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const lockoutMessage = document.getElementById('lockoutMessage');
    const logoutLink = document.getElementById('logoutLink');

    let consecutiveErrors = 0;
    let lockoutTime = parseFloat(localStorage.getItem('lockoutTime')) || 0;
    let lockoutAttempts = parseInt(localStorage.getItem('lockoutAttempts'), 10) || 0;
    let finalAttempt = localStorage.getItem('finalAttempt') === 'true';

    const errorTimes = [15, 30, 60]; // Lockout durations in seconds after 3 attempts

    // Save state to localStorage
    function saveState() {
        localStorage.setItem('lockoutTime', lockoutTime);
        localStorage.setItem('lockoutAttempts', lockoutAttempts);
        localStorage.setItem('finalAttempt', finalAttempt);
        localStorage.setItem('consecutiveErrors', consecutiveErrors);
        localStorage.setItem('username', usernameField.value);
        localStorage.setItem('password', passwordField.value);
    }

    // Clear state from localStorage
    function clearState() {
        localStorage.removeItem('lockoutTime');
        localStorage.removeItem('lockoutAttempts');
        localStorage.removeItem('finalAttempt');
        localStorage.removeItem('consecutiveErrors');
        localStorage.removeItem('username');
        localStorage.removeItem('password');
    }

    // Reset fields to saved values
    function restoreFields() {
        const savedUsername = localStorage.getItem('username');
        const savedPassword = localStorage.getItem('password');
        if (savedUsername) {
            usernameField.value = savedUsername;
        }
        if (savedPassword) {
            passwordField.value = savedPassword;
        }
    }

    function resetFields() {
        usernameField.value = '';
        passwordField.value = '';
    }

    function showError(field, errorElement, message) {
        errorElement.textContent = message;
        errorElement.style.color = 'red';
        field.style.borderColor = 'red';
    }

    function clearError(field, errorElement) {
        errorElement.textContent = '';
        field.style.borderColor = '';
    }

    function validateUsernameLength(username) {
        return username.length >= 3 && username.length <= 12;
    }

    function validateUsernameFormat(username) {
        const alphanumericRegex = /^[a-zA-Z0-9]+$/;
        const containsLetters = /[a-zA-Z]/.test(username);
        const containsNumbers = /\d/.test(username);
        return alphanumericRegex.test(username) && containsLetters && containsNumbers;
    }

   
    function showForgotPasswordLink() {
        if (forgotPasswordLink) {
            forgotPasswordLink.style.display = 'inline';
        }
    }


    function isLockedOut() {
        const currentTime = new Date().getTime() / 1000;
        if (lockoutTime > currentTime) {
            const remainingTime = lockoutTime - currentTime;
            showLockoutMessage(Math.ceil(remainingTime));
            return true;
        }
        hideLockoutMessage();
        return false;
    }

    function showLockoutMessage(remainingTime) {
        if (lockoutMessage) {
            lockoutMessage.style.display = 'block';
            document.getElementById('overlay').style.display = 'block';

            form.classList.add('disabled-input');
            const navbar = document.querySelector('.navbar');
            if (navbar) navbar.classList.add('disabled-input');

            const lockoutMessageText = finalAttempt
                ? 'Access Denied. Contact Support.'
                : 'Too many login attempts';

            lockoutMessage.innerHTML = `
                <p>${lockoutMessageText}</p>
                <p>Try again in ${remainingTime} seconds</p>
            `;

            const countdown = setInterval(() => {
                remainingTime -= 1;

                if (remainingTime > 0) {
                    lockoutMessage.innerHTML = `
                        <p>${lockoutMessageText}</p>
                        <p>Try again in ${remainingTime} seconds</p>
                    `;
                } else {
                    clearInterval(countdown);
                    hideLockoutMessage();
                }
            }, 1000);
        }
    }

    function hideLockoutMessage() {
        if (lockoutMessage) {
            lockoutMessage.style.display = 'none';
            document.getElementById('overlay').style.display = 'none';

            form.classList.remove('disabled-input');
            const navbar = document.querySelector('.navbar');
            if (navbar) navbar.classList.remove('disabled-input');
        }
    }

    function handleFailedLoginAttempt() {
        consecutiveErrors++;
        saveState();
        if (consecutiveErrors >= 2) {
            showForgotPasswordLink();
        }

        if (consecutiveErrors >= 3) {
            if (lockoutAttempts < 3) {
                lockoutTime = new Date().getTime() / 1000 + errorTimes[lockoutAttempts];
                lockoutAttempts++;
            } else {
                lockoutTime = new Date().getTime() / 1000 + errorTimes[2];
                finalAttempt = true;
            }
            saveState();
            showLockoutMessage(Math.ceil(lockoutTime - (new Date().getTime() / 1000)));
            consecutiveErrors = 0;
        }
    }

    async function validateAndCheckUsername(usernameField, usernameError) {
        const value = usernameField.value;

        // Clear any previous errors
        clearError(usernameField, usernameError);

        // Validate username length and format

        if (/^\d/.test(value)) {
            showError(usernameField, usernameError, 'Username cannot start with a number.');
            return false;
        }

        // Check if username exists via API
        const usernameCheck = await fetchData('validateLogin.php', { username: value });
        if (!usernameCheck || !usernameCheck.usernameExists) {
            showError(usernameField, usernameError, 'Username does not exist.');
            return false;
        }

        return true;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (isLockedOut()) return;

        const username = usernameField.value;
        const password = passwordField.value;

        // Clear previous errors before validation
        clearError(usernameField, usernameError);
        clearError(passwordField, passwordError);

        // Validate and check username using the new function
        const isUsernameValid = await validateAndCheckUsername(usernameField, usernameError);
        if (!isUsernameValid) return;

        // Validate credentials (username and password)
        const credentialsCheck = await fetchData('validateLogin.php', { username, password });
        if (!credentialsCheck || !credentialsCheck.validCredentials) {
            showError(passwordField, passwordError, 'Invalid password.');
            handleFailedLoginAttempt(); // Handle failed login attempts
            return;
        }

        // Successful login
        clearState();
        form.submit();
    });

    togglePassword.addEventListener('click', function () {
        const type = passwordField.type === 'password' ? 'text' : 'password';
        passwordField.type = type;
        togglePassword.textContent = type === 'password' ? 'Show' : 'Hide';
    });

    if (logoutLink) {
        logoutLink.addEventListener('click', () => {
            resetFields();
            clearState();
        });
    }

    restoreFields();
    isLockedOut();
});
