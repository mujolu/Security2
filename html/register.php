<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "artlab_db";

try {
  // Create a new PDO connection
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
  // Set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Check if the form is submitted
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $id = trim($_POST['id']);
    $first_name = trim($_POST['first_name']);
    $middle_initial = trim($_POST['middle_initial']);
    $last_name = trim($_POST['last_name']);
    $extension_name = trim($_POST['extension_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $sex = trim($_POST['sex']);
    $purok = trim($_POST['purok']);
    $barangay = trim($_POST['barangay']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $country = trim($_POST['country']);
    $zip_code = trim($_POST['zip_code']);
    $birthdate = trim($_POST['birthdate']);  // Get birthdate
    $age = trim($_POST['age']);  // Get age (if required, but this will be calculated from birthdate)

    // Check if required fields are empty
    // if (
    //   empty($id) || empty($first_name) || empty($last_name) || empty($username) || empty($email) ||
    //   empty($password) || empty($confirm_password) || empty($sex) || empty($purok) ||
    //   empty($barangay) || empty($city) || empty($province) || empty($country) || empty($zip_code) ||
    //   empty($birthdate)
    // ) {
    //   echo "All fields are required!";
    //   exit;
    // }

    // Validate passwords match
    if ($password !== $confirm_password) {
      echo "Passwords do not match!";
      exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM registered_users WHERE username = :username OR email = :email");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
      echo "Username or email already exists!";
      exit;
    }

    // Calculate age from birthdate
    $birthDate = new DateTime($birthdate);
    $currentDate = new DateTime();
    $age = $currentDate->diff($birthDate)->y;

    // Prepare the SQL statement to insert the data into the database
    $stmt = $conn->prepare("INSERT INTO registered_users (id, first_name, middle_initial, last_name, extension_name, username, email, password, sex, purok, barangay, city, province, country, zip_code, birthdate, age) 
                            VALUES (:id, :first_name, :middle_initial, :last_name, :extension_name, :username, :email, :password, :sex, :purok, :barangay, :city, :province, :country, :zip_code, :birthdate, :age)");

    // Bind parameters to prevent SQL injection
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':middle_initial', $middle_initial);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':extension_name', $extension_name);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':sex', $sex);
    $stmt->bindParam(':purok', $purok);
    $stmt->bindParam(':barangay', $barangay);
    $stmt->bindParam(':city', $city);
    $stmt->bindParam(':province', $province);
    $stmt->bindParam(':country', $country);
    $stmt->bindParam(':zip_code', $zip_code);
    $stmt->bindParam(':birthdate', $birthdate);
    $stmt->bindParam(':age', $age);

    // Execute the SQL query
    if ($stmt->execute()) {
      // Show a brief success message then redirect to the login page
      echo '<!DOCTYPE html>';
      echo '<html lang="en">';
      echo '<head>';
      echo '  <meta charset="utf-8">';
      echo '  <meta name="viewport" content="width=device-width, initial-scale=1">';
      echo '  <title>Registration Successful</title>';
      echo '  <meta http-equiv="refresh" content="3;url=login.php">';
      echo '  <style>body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;height:100vh;display:flex;align-items:center;justify-content:center}';
      echo '  .card{background:#fff;padding:24px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.08);max-width:420px;text-align:center}a{color:#2563eb;text-decoration:none}</style>';
      echo '</head>';
      echo '<body>';
      echo '  <div class="card">';
      echo '    <h2 style="margin:0 0 8px">Registration Successful</h2>';
      echo '    <p style="margin:0 0 12px">Your account has been created. Redirecting to login page...</p>';
      echo '    <p style="margin:0"><a href="login.php">Click here if you are not redirected</a></p>';
      echo '  </div>';
      echo '  <script>setTimeout(function(){window.location.href="login.php";}, 3000);</script>';
      echo '</body>';
      echo '</html>';
      exit;
    } else {
      echo "Error: Could not execute the query. " . implode(", ", $stmt->errorInfo());
    }
  }
} 
catch (PDOException $e) {
  // echo "Connection failed: " . $e->getMessage();
}

// Close the database connection
$conn = null;
?>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registration Form</title>
  <link rel="stylesheet" href="../html/script.php?dir=css&file=register.css">
  <link rel="stylesheet" href="../css/tailwind.css">
  <script src="../html/script.php?dir=js&file=personal_Information.js" defer></script>
  <script src="../html/script.php?dir=js&file=passwordValidation.js" defer></script>
  <script src="../html/script.php?dir=js&file=checkCredentials.js" defer></script>
  <script src="../html/script.php?dir=js&file=addressValidation.js" defer></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-800 flex flex-col min-h-screen">

  <nav class="navbar bg-gray-700 shadow-md">
    <!-- <div class="container  flex justify-between items-center w-full px-4"> -->
    <div class="navbar-logo">
      <a class="text-2xl font-bold text-blue-600">Artlab</a>
    </div>
    <div class="navbar-auth ml-auto">
      <a href="../html/index.php" class="btn-register text-gray-900 hover:text-gray-800 ml-5">Home</a>
      <a href="../html/login.php" class="btn-register text-gray-900 hover:text-gray-800 ml-5">Log in</a>
    </div>
    <!-- </div> -->
  </nav>

  <div class=" flex-grow flex  items-start w-full ">
    <div class="tagline text-left mb-6">
      <h1 class="text-sm font-semibold">Start your art journey with <span class="text-blue-600">ARTLAB</span></h1>
    </div>
    <div class="form-wrapper sign-up bg-white shadow-lg rounded-lg p-10 ml-20 md:w-2/3 lg:w-3/4 max-w-[1000px]">
      <form id="registrationForm" action="register.php" method="POST"  novalidate>
        <h1 class="text-2xl font-semibold mb-2 text-center text-sm">
          <span class="text-yellow-500 text-2xl">CONNECT WITH US!</span><br>
          <span class="text-black">Registration Form</span>
        </h1>

        <!-- Personal Information Section -->
        <div class="mb-6">
          <h2 class="text-lg font-medium mb-4 text-left">Personal Information</h2>
          <div class="border border-slate-300 rounded-lg p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label for="id" class="block mb-1 text-sm font-medium text-gray-700">ID No.<span class="text-xs mt-1" style="color: red;" >*</span> </label></label>
                
                <input id="id" name="id" type="text" required maxlength="9"
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  placeholder="xxxx-xxxx"
                  value="<?php echo isset($_POST['id']) ? $_POST['id'] : ''; ?>"
                  
                  >
                  
              </div>
              <div>
                <label for="first_name" class="block mb-1 text-sm font-medium text-gray-700">First Name <span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="first_name" name="first_name" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                      value="<?php echo isset($_POST['first_name']) ? $_POST['first_name'] : ''; ?>"
                  >
              </div>

              <!-- OPTIONAL -->
              <div>
                <label for="middle_initial" class="block mb-1 text-sm font-medium text-gray-700"  >Middle Initial 
                  <span class="text-xs mt-1" style="color: red;" >(Optional)</span> </label>
                <input id="middle_initial" name="middle_initial" type="text" maxlength="1"
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['middle_initial']) ? $_POST['middle_initial'] : ''; ?>"
                  >
              </div>
              <div>
                <label for="last_name" class="block mb-1 text-sm font-medium text-gray-700">Last Name <span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="last_name" name="last_name" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['last_name']) ? $_POST['last_name'] : ''; ?>"
                  >
              </div>

              <!-- OPTIONAL -->
              <div>
                <label for="extension_name" class="block mb-1 text-sm font-medium text-gray-700">Extension Name <span
                    class="text-xs mt-1" style="color: red;">(Optional)</span></label>
                <input id="extension_name" name="extension_name" type="text" maxlength="3"
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['extension_name']) ? $_POST['extension_name'] : ''; ?>">
              </div>
              <div>
                <label for="sex" class="block mb-1 text-sm font-medium text-gray-700">Sex <span class="text-xs mt-1" style="color: red;" >*</span></label>
                <select id="sex" name="sex" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full">
                  <option value="" disabled selected>Select Sex </option
                  value="<?php echo isset($_POST['sex']) ? $_POST['sex'] : ''; ?>">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>

              <!-- Birthdate and Age -->

              <div>
                <label for="birthdate" class="block mb-1 text-sm font-medium text-gray-700">Birthdate <span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="birthdate" name="birthdate" type="date" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  onchange="calculateAge()"
                  value="<?php echo isset($_POST['birthdate']) ? $_POST['birthdate'] : ''; ?>"
                  >
              </div>
              <div>
                <label for="age" class="block mb-1 text-sm font-medium text-gray-700">Age<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="age" name="age" type="text" readonly
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  
                  value="<?php echo isset($_POST['age']) ? $_POST['age'] : ''; ?>">
              </div>
            </div>
          </div>
        </div>

        <!-- Address Section -->
        <div class="mb-6">
          <h2 class="text-lg font-medium mb-4 text-left">Address<span class="text-xs mt-1" style="color: red;" >*</span></h2>
          <div class="border border-slate-300 rounded-lg p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label for="purok" class="block mb-1 text-sm font-medium text-gray-700">Purok<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="purok" name="purok" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['purok']) ? $_POST['purok'] : ''; ?>"
                  >
              </div>
              <div>
                <label for="barangay" class="block mb-1 text-sm font-medium text-gray-700">Barangay<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="barangay" name="barangay" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['barangay']) ? $_POST['barangay'] : ''; ?>"
                  >
              </div>
              <div>
                <label for="city" class="block mb-1 text-sm font-medium text-gray-700">City<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="city" name="city" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  
                  value="<?php echo isset($_POST['city']) ? $_POST['city'] : ''; ?>">
              </div>
              <div>
                <label for="province" class="block mb-1 text-sm font-medium text-gray-700">Province<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="province" name="province" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['province']) ? $_POST['province'] : ''; ?>"
                  >
              </div>
              <div>
                <label for="country" class="block mb-1 text-sm font-medium text-gray-700">Country<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="country" name="country" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['country']) ? $_POST['country'] : ''; ?>" 
                  >
              </div>
              <div>
                <label for="zip_code" class="block mb-1 text-sm font-medium text-gray-700">Zip Code<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="zip_code" name="zip_code" type="text" required maxlength="5"
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['zip_code']) ? $_POST['zip_code'] : ''; ?>"
                  >
              </div>
            </div>
          </div>
        </div>

        <!-- Credentials Section -->
        <div class="mb-6">
          <h2 class="text-lg font-medium mb-4 text-left">Credentials</h2>
          <div class="border border-slate-300 rounded-lg p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              
              <div>
                <label for="email" class="block mb-1 text-sm font-medium text-gray-700">Email Address<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="email" name="email" type="email" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  
                  value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                <div id="emailError" class="error-message text-red-500 text-sm mt-1"></div>
              </div>
              <div>
                <label for="username" class="block mb-1 text-sm font-medium text-gray-700">Username<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="username" name="username" type="text" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  
                  value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>">
                <div id="usernameError" class="error-message text-red-500 text-sm mt-1 "></div>
              </div>
              <div>
                <label for="password" class="block mb-1 text-sm font-medium text-gray-700">Password<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="password" name="password" type="password" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>">
              </div>
              
              
              <div>
                <label for="repassword" class="block mb-1 text-sm font-medium text-gray-700">Re-enter Password<span class="text-xs mt-1" style="color: red;" >*</span></label>
                <input id="repassword" name="confirm_password" type="password" required
                  class="border border-gray-300 p-2 rounded focus:outline-none focus:border-blue-500 w-full"
                  value="<?php echo isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''; ?>">
              </div>
              <div>
              </div>
              <div>
              </div>
              <div id="passwordStrengthMessage" class="text-sm mt-1"></div>
              <div id="passwordMatchMessage" class="text-sm mt-1"></div>
              <div>
              </div>
              

            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" 
          class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition duration-300">Register</button>

        <div class="signUp-link text-center mt-4">
          <p>
            Already have an account?
            <a href="../html/login.php" class="text-blue-600 hover:text-blue-800">Sign In</a>
          </p>
        </div>
      </form>
    </div>
  </div>


  <footer class="bg-white shadow-md mt-auto">
    <div class="container mx-auto p-4 text-center">
      <p>&copy; 2024 Magdasal. All rights reserved.</p>
    </div>
  </footer>
  <!-- <script>
    document.getElementById("registrationForm").addEventListener("submit", function (event) {
      const form = event.target;

      // Get all input fields
      const inputs = form.querySelectorAll("input, select");

      // Check if all required fields are filled
      let allFilled = true;
      inputs.forEach(input => {
        if (input.value.trim() === "") {
          input.style.borderColor = "red"; // Highlight empty fields
          allFilled = false;
        } else {
          input.style.borderColor = ""; // Reset border for filled fields
        }
      });

      if (!allFilled) {
        event.preventDefault(); // Prevent form submission
        alert("Please fill in the necessary fields.");
      }

    });
  </script> -->
</body>

</html>