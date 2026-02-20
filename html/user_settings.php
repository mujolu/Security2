<?php
require 'connection.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    echo "<p class='text-red-500'>Please login to access settings.</p>";
    return;
}

$editMode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == 1;


$message = '';
$success = false;

$questionsList = [
    "What is your mother's maiden name?",
    "What was your first pet's name?",
    "What was the name of your first school?",
    "What is your favorite color?",
    "What city were you born in?",
    "What was your childhood nickname?",
    "What was your first car model?"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filled = 0;

    for ($i = 1; $i <= 3; $i++) {
        if (!empty($_POST["security_question_$i"]) && !empty($_POST["security_answer_$i"])) {
            $filled++;
        }
    }

    if ($filled < 3) {
        $message = "Please complete all 3 security questions.";
    } else {
        for ($i = 1; $i <= 3; $i++) {
            $question = $_POST["security_question_$i"];
            $answer   = password_hash($_POST["security_answer_$i"], PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO user_security_settings (user_id, question_number, security_question, security_answer)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 security_question = VALUES(security_question),
                 security_answer = VALUES(security_answer)"
            );
            $stmt->execute([$user_id, $i, $question, $answer]);
        }

        $message = "Security questions saved successfully!";
        $success = true;
    }
}

// Fetch existing
$stmt = $conn->prepare(
    "SELECT question_number, security_question 
     FROM user_security_settings WHERE user_id = ?"
);
$stmt->execute([$user_id]);
$existing = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row['question_number']] = $row;
}
$hasSecurityQuestions = count($existing) === 3;
$showForm = !$hasSecurityQuestions || $editMode;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex justify-center items-start p-6">
        <?php if($message): ?>
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

   <section id="settings-section" class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 mx-auto mb-10">

    <h2 class="text-2xl font-bold text-gray-800 mb-6">Account Settings</h2>

    <!-- 🔐 DISPLAY MODE -->
    <?php if ($hasSecurityQuestions && !$editMode): ?>
    <section id="settings-section" class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 mx-auto mb-10">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Security Questions</h2>

        <div class="space-y-4">
            <?php foreach ($existing as $num => $row): ?>
                <div class="p-4 rounded-lg bg-gray-50 border">
                    <p class="font-semibold text-gray-700">Question <?= $num ?></p>
                    <p class="text-gray-600 mt-1"><?= htmlspecialchars($row['security_question']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" class="mt-6" action="">
            <input type="hidden" name="edit_mode" value="1">
            <button type="submit"
                class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 transition">
                Edit Security Questions
            </button>
        </form>
    </section>
    <?php endif; ?>


    <!-- ✏️ FORM MODE -->
    <?php if ($showForm): ?>
<section id="settings-section" class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 mx-auto mb-10">


    <form method="POST" class="space-y-6">
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="border p-4 rounded-lg bg-gray-50">
            <label class="block font-medium mb-1">Security Question <?= $i ?></label>
            <select name="security_question_<?= $i ?>" required
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-yellow-400">
                <option value="">-- Select a Security Question --</option>
                <?php foreach ($questionsList as $q): ?>
                    <option value="<?= $q ?>"
                        <?= (($existing[$i]['security_question'] ?? '') === $q) ? 'selected' : '' ?>>
                        <?= $q ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="block font-medium mt-2">Answer</label>
            <input type="text" name="security_answer_<?= $i ?>" required
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-yellow-400"
                placeholder="Enter your answer">
        </div>
        <?php endfor; ?>

        <button type="submit"
            class="bg-gray-800 text-white rounded-lg px-6 py-2 hover:bg-yellow-600 transition">
            Save Settings
        </button>
    </form>
</section>
<?php endif; ?>


</section>


</body>
</html>
