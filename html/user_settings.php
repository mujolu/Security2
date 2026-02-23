<?php
session_start();
require 'connection.php';
include 'activity_logger.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

logActivity($conn, $_SESSION['user_id'], 'Accessed Artist Settings Page', 'user_activity_logs');

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
    if (isset($_POST['edit_mode']) && $_POST['edit_mode'] == 1) {
        logActivity($conn, $user_id, 'Opened security questions editor', 'user_activity_logs');
    }

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
        logActivity($conn, $user_id, 'Updated security questions', 'user_activity_logs');
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
<body class="bg-linear-to-br from-gray-50 to-gray-200 min-h-screen">
    <header class="p-4 md:px-6 bg-gray-800 text-white flex justify-between items-center shadow-lg sticky top-0 z-20">
        <div class="flex items-center gap-3">
            <div class="text-xl font-bold tracking-wide">ARTLAB</div>
            <div class="hidden sm:block text-sm text-gray-200">Account Settings</div>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden md:block text-sm text-gray-200">Logged in as <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
            <a href="logOut.php" class="bg-yellow-600 px-4 py-2 rounded-lg hover:bg-yellow-700 transition shadow-sm">Logout</a>
        </div>
    </header>

    <div class="flex">
        <aside class="w-64 bg-gray-800 min-h-screen p-6 flex flex-col shadow-xl">
            <h2 class="text-2xl font-bold text-white mb-5">ARTLAB</h2>

            <div class="flex flex-col items-center text-center mt-8">
                <img src="/Security2/images/profilepic.jpg" alt="Profile" class="w-24 h-24 rounded-full object-cover border-4 border-yellow-500 shadow-md mb-4">
                <h4 class="text-white font-semibold mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h4>
                <p class="text-xs text-gray-400">Artist Panel</p>
            </div>

            <nav class="flex flex-col gap-4 mt-8">
                <a href="user_artwork.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Artwork</a>
                <a href="user_collaborations.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Collaborations</a>
                <a href="user_marketplace.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">Marketplace</a>
                <a href="user_activity_logs.php" class="sidebar-link bg-gray-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-700 transition-colors duration-300">My Activity Logs</a>
                <a href="user_settings.php" class="sidebar-link bg-yellow-700 text-white rounded-lg px-4 py-3 font-medium hover:bg-yellow-600 transition-colors duration-300">Settings</a>
            </nav>
        </aside>

        <main class="flex-1 p-6 md:p-10 bg-transparent min-h-screen">
            <div class="mb-8 max-w-5xl">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Account Settings</h1>
                <p class="text-gray-600 mt-2">Keep your account recovery secure by maintaining your security questions.</p>
            </div>

            <?php if($message && $success): ?>
                <div class="max-w-5xl bg-green-100 text-green-800 border border-green-300 px-4 py-3 rounded-xl mb-6 shadow-sm">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php elseif($message): ?>
                <div class="max-w-5xl bg-red-100 text-red-800 border border-red-300 px-4 py-3 rounded-xl mb-6 shadow-sm">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section id="settings-section" class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-5xl overflow-hidden">
                <div class="px-6 md:px-8 py-5 border-b bg-gray-50">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-800">Security Settings</h2>
                    <p class="text-sm text-gray-500 mt-1">Set 3 questions you can use to recover your account.</p>
                </div>

                <div class="p-6 md:p-8">
                <?php if ($hasSecurityQuestions && !$editMode): ?>
                    <h3 class="text-xl font-semibold text-gray-800 mb-5">Your Current Questions</h3>

                    <div class="space-y-4 mb-6">
                        <?php foreach ($existing as $num => $row): ?>
                            <div class="p-4 md:p-5 rounded-xl bg-white border-2 border-gray-300 hover:border-yellow-500 hover:shadow-sm transition">
                                <p class="text-sm font-bold text-gray-800 uppercase tracking-wide">Security Question <?= $num ?></p>
                                <div class="mt-2 border-t-2 border-gray-200 pt-3">
                                    <p class="text-gray-700 font-medium"><?= htmlspecialchars($row['security_question']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" class="mt-6" action="">
                        <input type="hidden" name="edit_mode" value="1">
                        <button type="submit" class="inline-flex items-center gap-2 bg-yellow-600 text-white px-6 py-2.5 rounded-lg hover:bg-yellow-700 transition shadow-sm">
                            Edit Security Questions
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($showForm): ?>
                    <h3 class="text-xl font-semibold text-gray-800 mb-5"><?= $hasSecurityQuestions ? 'Update Security Questions' : 'Set Security Questions' ?></h3>
                    <form method="POST" class="space-y-6">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="border-2 border-gray-300 p-5 md:p-6 rounded-xl bg-white shadow-sm">
                                <div class="pb-3 mb-4 border-b-2 border-gray-200">
                                    <h4 class="text-base font-bold text-gray-800">Security Question <?= $i ?></h4>
                                    <p class="text-xs text-gray-500 mt-1">Choose one question and provide your answer</p>
                                </div>

                                <label class="block text-sm font-semibold text-gray-700 mb-2">Question</label>
                                <select name="security_question_<?= $i ?>" required class="w-full border-2 border-gray-400 rounded-lg px-3 py-2.5 bg-gray-50 focus:ring-2 focus:ring-yellow-400 focus:border-yellow-500 outline-none transition">
                                    <option value="">-- Select a Security Question --</option>
                                    <?php foreach ($questionsList as $q): ?>
                                        <option value="<?= $q ?>" <?= (($existing[$i]['security_question'] ?? '') === $q) ? 'selected' : '' ?>>
                                            <?= $q ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label class="block text-sm font-semibold text-gray-700 mt-4 mb-2">Answer</label>
                                <input type="text" name="security_answer_<?= $i ?>" required class="w-full border-2 border-gray-400 rounded-lg px-3 py-2.5 bg-gray-50 focus:ring-2 focus:ring-yellow-400 focus:border-yellow-500 outline-none transition" placeholder="Enter your answer">
                            </div>
                        <?php endfor; ?>

                        <button type="submit" class="inline-flex items-center gap-2 bg-gray-800 text-white rounded-lg px-6 py-2.5 hover:bg-yellow-600 transition shadow-sm">
                            Save Settings
                        </button>
                    </form>
                <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
