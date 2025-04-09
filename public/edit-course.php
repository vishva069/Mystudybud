<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Course.php';

// Check if user is logged in
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if user is a tutor or admin
if (!$auth->isTutorOrAdmin()) {
    header('Location: error.php?message=You do not have permission to access this page');
    exit;
}

// Check if course ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my-courses.php');
    exit;
}

$courseId = $_GET['id'];
$userId = $_SESSION['user_id'];
$courseObj = new Course();

// Get course details
$course = $courseObj->getCourseById($courseId, true);

// Check if course exists and belongs to the current user
if (!$course || $course['instructor_id'] != $userId) {
    header('Location: error.php?message=Course not found or you do not have permission to edit it');
    exit;
}

// Process course update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $level = filter_input(INPUT_POST, 'level', FILTER_SANITIZE_STRING);
    $status = isset($_POST['is_published']) ? 'published' : 'draft';
    
    if (empty($title)) {
        $error = 'Course title is required';
    } else {
        try {
            $data = [
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'level' => $level,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Handle thumbnail upload
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/thumbnails/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['thumbnail']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                // Check if file is an image
                $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($imageFileType, $allowedTypes)) {
                    $error = 'Only JPG, JPEG, PNG & GIF files are allowed for thumbnails';
                } else {
                    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadFile)) {
                        $data['thumbnail'] = $uploadFile;
                    } else {
                        $error = 'Failed to upload thumbnail';
                    }
                }
            }
            
            if (empty($error)) {
                if ($courseObj->updateCourse($courseId, $data)) {
                    $success = 'Course updated successfully';
                    // Refresh course data
                    $course = $courseObj->getCourseById($courseId, true);
                } else {
                    $error = 'Failed to update course';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Edit Course</h1>
            <div>
                <a href="course-content.php?id=<?php echo $courseId; ?>" class="btn btn-primary">
                    <i class="bi bi-collection me-2"></i>Manage Content
                </a>
                <a href="my-courses.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left me-2"></i>Back to My Courses
                </a>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Course Title *</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                <div class="form-text">Provide a detailed description of what students will learn in this course.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category">
                                            <option value="">Select Category</option>
                                            <?php
                                            $categories = ['Programming', 'Web Development', 'Data Science', 'Mobile Development', 
                                                          'Mathematics', 'Science', 'Language', 'Business', 'Other'];
                                            foreach ($categories as $cat) {
                                                $selected = ($course['category'] == $cat) ? 'selected' : '';
                                                echo "<option value=\"$cat\" $selected>$cat</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="level" class="form-label">Level</label>
                                        <select class="form-select" id="level" name="level">
                                            <option value="">Select Level</option>
                                            <?php
                                            $levels = ['Beginner', 'Intermediate', 'Advanced', 'All Levels'];
                                            foreach ($levels as $lvl) {
                                                $selected = ($course['level'] == $lvl) ? 'selected' : '';
                                                echo "<option value=\"$lvl\" $selected>$lvl</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Course Thumbnail</label>
                                <div class="card mb-3">
                                    <?php if (!empty($course['thumbnail'])): ?>
                                        <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" class="card-img-top" alt="Course Thumbnail">
                                    <?php else: ?>
                                        <div class="bg-light text-center py-5">
                                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                            <p class="mt-2 text-muted">No thumbnail</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                <div class="form-text">Recommended size: 1280x720 pixels (16:9 ratio)</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published" <?php echo ($course['status'] === 'published') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_published">Publish Course</label>
                                </div>
                                <div class="form-text">When published, your course will be visible to all students.</div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><strong>Course Statistics</strong></p>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Videos
                                        <span class="badge bg-primary rounded-pill"><?php echo $course['video_count'] ?? 0; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Enrollments
                                        <span class="badge bg-success rounded-pill"><?php echo $course['enrollment_count'] ?? 0; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Created
                                        <span><?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="my-courses.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" name="update_course" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
