// // Define error handling functions globally
// function setInlineError(field, message) {
//     let errorElement = field.nextElementSibling;
//     if (!errorElement || !errorElement.classList.contains('error-message')) {
//         errorElement = document.createElement('div');
//         errorElement.classList.add('error-message');
//         errorElement.style.color = 'red';
//         field.parentNode.insertBefore(errorElement, field.nextSibling);
//     }
//     errorElement.textContent = message;
// }

// function clearInlineError(field) {
//     let errorElement = field.nextElementSibling;
//     if (errorElement && errorElement.classList.contains('error-message')) {
//         errorElement.textContent = '';
//     }
//     field.classList.remove('border-red-500'); // Remove red border if exists
// }

// // Function to mark a field as required with a red border and message
// function markAsRequired(field) {
//     field.classList.add('border-red-500'); // Add red border
//     setInlineError(field, 'This field is required.');
// }

// document.addEventListener('DOMContentLoaded', function () {
//     const form = document.getElementById('registrationForm');

//     const fields = {
//         id: document.getElementById('id'),
//         first_name: document.getElementById('first_name'),
//         middle_initial: document.getElementById('middle_initial'),
//         last_name: document.getElementById('last_name'),
//         extension_name: document.getElementById('extension_name'),
//         birthdate: document.getElementById('birthdate'),
//         age: document.getElementById('age')
//     };

//     const errorDisplayed = {};

//     // Reset error state
//     function resetError(fieldId) {
//         errorDisplayed[fieldId] = false;
//         clearInlineError(fields[fieldId]);
//     }

//     // Validate if the field is not empty
//     function validateNotEmpty(field, fieldName) {
//         const value = field.value.trim();
//         if (!value) {
//             markAsRequired(field);
//             return false;
//         }
//         clearInlineError(field);
//         return true;
//     }

//     // Validate ID
//     async function validateID() {
//         const idValue = fields.id.value.trim();
//         if (!validateNotEmpty(fields.id, 'ID')) return false;

//         if (!/^\d{4}-\d{4}$/.test(idValue)) {
//             setInlineError(fields.id, 'ID must be in the format XXXX-XXXX.');
//             return false;
//         }

//         try {
//             const response = await fetch('check_id.php', {
//                 method: 'POST',
//                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
//                 body: new URLSearchParams({ id: idValue })
//             });
//             const result = await response.json();
//             if (result.status === 'error') {
//                 setInlineError(fields.id, result.message || 'Error validating ID.');
//                 return false;
//             }
//             resetError('id');
//             return true;
//         } catch (error) {
//             setInlineError(fields.id, 'Could not validate ID. Try again later.');
//             return false;
//         }
//     }

//     // Validate Name (First, Last)
//     function validateName(field, fieldName) {
//         const nameValue = field.value.trim();
//         if (!validateNotEmpty(field, fieldName)) return false;

//         if (!/^[A-Z][a-z]*( [A-Z][a-z]*)*$/.test(nameValue)) {
//             setInlineError(field, `${fieldName} must start with uppercase and only contain letters.`);
//             return false;
//         }

//         resetError(field.id);
//         return true;
//     }

//     // Validate Middle Initial
//     function validateMiddleInitial() {
//         const value = fields.middle_initial.value.trim();
//         if (value === '') {
//             resetError('middle_initial');
//             return true; // Optional, so valid if empty
//         }
//         if (!/^[A-Z]$/.test(value)) {
//             setInlineError(fields.middle_initial, 'Middle Initial must be a single capital letter.');
//             return false;
//         }
//         resetError('middle_initial');
//         return true;
//     }

//     // Validate Extension Name (Jr, Sr, Roman numerals)
//     function validateExtensionName() {
//         const value = fields.extension_name.value.trim();
//         if (value === '') {
//             resetError('extension_name');
//             return true;
//         }

//         const validExtensions = /^(Jr|Sr|(?:M{0,3})(?:CM|CD|D?C{0,3})(?:XC|XL|L?X{0,3})(?:IX|IV|V?I{0,3}))$/i;
//         if (!validExtensions.test(value)) {
//             setInlineError(fields.extension_name, 'Must be Jr., Sr., or a valid Roman numeral.');
//             return false;
//         }

//         resetError('extension_name');
//         return true;
//     }

//     // Calculate age and validate birthdate
//     function calculateAge() {
//         const birthdateValue = fields.birthdate.value;
//         if (!birthdateValue) {
//             markAsRequired(fields.birthdate);
//             return;
//         }

//         const birthdate = new Date(birthdateValue);
//         const today = new Date();
//         let age = today.getFullYear() - birthdate.getFullYear();
//         const monthDiff = today.getMonth() - birthdate.getMonth();

//         if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
//             age--;
//         }

//         fields.age.value = age;
//         if (age < 18) {
//             setInlineError(fields.birthdate, 'You must be at least 18 to register.');
//         } else {
//             clearInlineError(fields.birthdate);
//         }
//     }

//     fields.birthdate.addEventListener('change', calculateAge);

//     // Validate all fields
//     async function validateAllFields() {
//         let isValid = true;
//         if (!validateName(fields.first_name, 'First Name')) isValid = false;
//         if (!validateMiddleInitial()) isValid = false;
//         if (!validateName(fields.last_name, 'Last Name')) isValid = false;
//         if (!validateExtensionName()) isValid = false;
//         if (fields.birthdate.value.trim() === '') isValid = false;
//         if (!(await validateID())) isValid = false;
//         return isValid;
//     }

//     // Form submit handler
//     async function handleSubmit(event) {
//         event.preventDefault();
//         const button = event.submitter;
//         button.disabled = true;

//         const isValid = await validateAllFields();
//         if (isValid) {
//             form.submit();
//         } else {
//             button.disabled = false;
//         }
//     }

//     form.addEventListener('submit', handleSubmit);
// });
