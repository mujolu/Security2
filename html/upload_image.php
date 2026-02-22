<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
include 'connection.php';
include 'activity_logger.php';

// Image upload handler
$uploadDir = '../images/uploads/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$uploadedImage = null;
$errorMessage = '';
$successMessage = '';
$userPosts = [];

// Create uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Fetch user's previous posts
try {
    $stmt = $conn->prepare("
        SELECT id, filename, title, description, created_at 
        FROM image_posts 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
    ");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
    $stmt->execute();
    $userPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Error fetching posts: ' . $e->getMessage();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validate file was uploaded without errors
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Get file info
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        
        // Validate file size
        if ($fileSize > $maxFileSize) {
            $errorMessage = 'File size must not exceed 5MB.';
        } else {
            // Get file extension
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file extension
            if (!in_array($fileExtension, $allowedExtensions)) {
                $errorMessage = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
            } else {
                // Validate MIME type for additional security
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmpPath);
                finfo_close($finfo);
                
                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/gif'
                ];
                
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    $errorMessage = 'Invalid image file. Please upload a valid image.';
                } else {
                    // Generate unique filename: timestamp + random string + extension
                    $uniqueFileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $uniqueFileName;
                    
                    // Move uploaded file to uploads directory
                    if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                        // Save to database
                        try {
                            $stmt = $conn->prepare("
                                INSERT INTO image_posts (user_id, filename, title, description) 
                                VALUES (:user_id, :filename, :title, :description)
                            ");
                            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
                            $stmt->bindParam(':filename', $uniqueFileName, PDO::PARAM_STR);
                            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                            $stmt->execute();
                            
                            $uploadedImage = $uniqueFileName;
                            $successMessage = 'Image and post saved successfully!';
                            
                            // Log the activity
                            logUpload($conn, $_SESSION['user_id'], 'image', $title ?: $uniqueFileName);
                            
                            // Refresh posts list
                            $stmt = $conn->prepare("
                                SELECT id, filename, title, description, created_at 
                                FROM image_posts 
                                WHERE user_id = :user_id 
                                ORDER BY created_at DESC
                            ");
                            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_STR);
                            $stmt->execute();
                            $userPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $errorMessage = 'Error saving post to database: ' . $e->getMessage();
                            // Delete the file if database insert failed
                            if (file_exists($uploadPath)) {
                                unlink($uploadPath);
                            }
                        }
                    } else {
                        $errorMessage = 'Failed to save the uploaded file. Please try again.';
                    }
                }
            }
        }
    } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $errorMessage = 'Please select a file to upload.';
    } else {
        $errorMessage = 'An error occurred during the upload. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .image-preview-container {
            width: 100%;
            margin-top: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9fafb;
            box-sizing: border-box;
        }
        
        .uploaded-image {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3b82f6;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .file-input-label:hover {
            background-color: #2563eb;
        }
        
        .file-name-display {
            display: inline-block;
            margin-left: 10px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">ArtLab</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="logOut.php" class="text-red-600 hover:text-red-800 font-semibold">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    <!-- Upload Section -->
    <div class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Share Your Artwork</h1>
                <p class="text-gray-600 mb-6">Upload and showcase your creative work with a title and description.</p>
            
            <!-- Success Message -->
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($errorMessage): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            
            <!-- Upload Form -->
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition">
                    <div class="file-input-wrapper">
                        <input 
                            type="file" 
                            name="image" 
                            id="imageInput" 
                            accept="image/*"
                        >
                        <label for="imageInput" class="file-input-label">
                            Choose Image
                        </label>
                    </div>
                    <p class="file-name-display" id="fileName">No file selected</p>
                    <p class="text-gray-500 text-sm mt-2">JPG, JPEG, PNG, or GIF (Max 5MB)</p>
                </div>
                
                <!-- Preview Before Upload -->
                <div id="previewContainer" class="image-preview-container" style="display: none;">
                    <p class="text-gray-600 text-sm mb-2">Preview:</p>
                    <img id="previewImage" alt="Preview" class="uploaded-image" src="">
                </div>
                
                <!-- Title Field -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Post Title</label>
                    <input 
                        type="text" 
                        name="title" 
                        id="title" 
                        placeholder="Give your post a title (optional)"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                
                <!-- Description Field -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea 
                        name="description" 
                        id="description" 
                        placeholder="Add a description to your post (optional)"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    ></textarea>
                </div>
                
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition">
                    Upload Image
                </button>
            </form>
            
            
            <!-- Display Uploaded Image -->
            <?php if ($uploadedImage): ?>
                <div class="image-preview-container">
                    <h2 class="text-lg font-semibold text-gray-800 mb-3">✓ Image Uploaded!</h2>
                    <img 
                        src="../images/uploads/<?php echo htmlspecialchars($uploadedImage); ?>" 
                        alt="Uploaded Image" 
                        class="uploaded-image"
                    >
                    <p class="text-gray-600 text-sm mt-3">
                        <strong>Filename:</strong> <?php echo htmlspecialchars($uploadedImage); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Previous Posts Gallery -->
    <?php if (!empty($userPosts)): ?>
        <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Your Posts</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($userPosts as $post): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                            <img 
                                src="../images/uploads/<?php echo htmlspecialchars($post['filename']); ?>" 
                                alt="<?php echo htmlspecialchars($post['title'] ?: 'Untitled'); ?>"
                                class="w-full h-64 object-cover"
                            >
                            <div class="p-4">
                                <?php if ($post['title']): ?>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </h3>
                                <?php endif; ?>
                                
                                <?php if ($post['description']): ?>
                                    <p class="text-gray-600 text-sm mb-3">
                                        <?php echo htmlspecialchars(substr($post['description'], 0, 100)); ?>
                                        <?php if (strlen($post['description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.getElementById('previewContainer');
            const previewImage = document.getElementById('previewImage');
            const fileNameDisplay = document.getElementById('fileName');
            
            if (file) {
                // Update filename display
                fileNameDisplay.textContent = file.name;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImage.src = event.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                fileNameDisplay.textContent = 'No file selected';
                previewContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>
