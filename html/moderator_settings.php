<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Moderator';

logActivity($conn, $user_id, 'Accessed Moderator Settings Page', 'moderator_activity_logs');

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS user_security_settings (
        user_id VARCHAR(9) NOT NULL,
        question_number TINYINT NOT NULL,
        security_question VARCHAR(255) NOT NULL,
        security_answer VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, question_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
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
            $question = trim($_POST["security_question_$i"]);
            $answer   = password_hash(trim($_POST["security_answer_$i"]), PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO user_security_settings (user_id, question_number, security_question, security_answer)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 security_question = VALUES(security_question),
                 security_answer = VALUES(security_answer)"
            );
            $stmt->execute([$user_id, $i, $question, $answer]);
        }

        logActivity($conn, $user_id, 'Updated security questions in Moderator Settings', 'moderator_activity_logs');
        $message = "Security questions saved successfully!";
        $success = true;
        $editMode = false;
    }
}

$stmt = $conn->prepare(
    "SELECT question_number, security_question
     FROM user_security_settings
     WHERE user_id = ?
     ORDER BY question_number ASC"
);
$stmt->execute([$user_id]);
$existing = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[(int)$row['question_number']] = $row;
}
$hasSecurityQuestions = count($existing) === 3;
$showForm = !$hasSecurityQuestions || $editMode;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderator Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gray-800 text-white p-6 flex items-center justify-between shadow-lg">
        <div>
            <h1 class="text-2xl font-bold">ARTLAB Moderator</h1>
            <p class="text-gray-300 text-sm">Account Settings</p>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-gray-200"><?php echo htmlspecialchars($username); ?></span>
            <a href="logOut.php" class="bg-yellow-600 px-4 py-2 rounded hover:bg-yellow-700 transition">Logout</a>
        </div>
    </header>

    <div class="flex min-h-screen">
        <?php include 'moderator_sidebar.php'; ?>

        <main class="flex-1 p-10 bg-white">
            <div class="mb-8">
                <h1 class="text-3xl font-semibold text-gray-800">Moderator Settings</h1>
                <p class="text-gray-500 mt-1">Manage your security questions for account recovery</p>
            </div>

            <?php if($message && $success): ?>
                <div class="bg-green-100 text-green-800 border border-green-300 px-4 py-3 rounded mb-6 max-w-3xl">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php elseif($message): ?>
                <div class="bg-red-100 text-red-800 border border-red-300 px-4 py-3 rounded mb-6 max-w-3xl">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 border border-gray-200">
                <?php if ($hasSecurityQuestions && !$editMode): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Security Questions</h2>

                    <div class="space-y-4">
                        <?php foreach ($existing as $num => $row): ?>
                            <div class="p-4 rounded-lg bg-gray-50 border-2 border-gray-300">
                                <p class="font-semibold text-gray-700">Security Question <?php echo (int)$num; ?></p>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($row['security_question']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" class="mt-6" action="">
                        <input type="hidden" name="edit_mode" value="1">
                        <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 transition">
                            Edit Security Questions
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($showForm): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-6"><?php echo $hasSecurityQuestions ? 'Update Security Questions' : 'Set Security Questions'; ?></h2>
                    <form method="POST" class="space-y-6">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="border-2 border-gray-300 p-5 rounded-xl bg-white shadow-sm">
                                <div class="pb-3 mb-4 border-b-2 border-gray-200">
                                    <h4 class="text-base font-bold text-gray-800">Security Question <?php echo $i; ?></h4>
                                </div>

                                <label class="block text-sm font-semibold text-gray-700 mb-2">Question</label>
                                <select name="security_question_<?php echo $i; ?>" required class="w-full border-2 border-gray-400 rounded-lg px-3 py-2.5 bg-gray-50 focus:ring-2 focus:ring-yellow-400 focus:border-yellow-500 outline-none transition">
                                    <option value="">-- Select a Security Question --</option>
                                    <?php foreach ($questionsList as $q): ?>
                                        <option value="<?php echo htmlspecialchars($q); ?>" <?php echo (($existing[$i]['security_question'] ?? '') === $q) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($q); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label class="block text-sm font-semibold text-gray-700 mt-4 mb-2">Answer</label>
                                <input type="text" name="security_answer_<?php echo $i; ?>" required class="w-full border-2 border-gray-400 rounded-lg px-3 py-2.5 bg-gray-50 focus:ring-2 focus:ring-yellow-400 focus:border-yellow-500 outline-none transition" placeholder="Enter your answer">
                            </div>
                        <?php endfor; ?>

                        <button type="submit" class="bg-gray-800 text-white rounded-lg px-6 py-2.5 hover:bg-yellow-600 transition shadow-sm">
                            Save Settings
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
