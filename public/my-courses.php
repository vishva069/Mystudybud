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

$userId = $_SESSION['user_id'];
$user = $auth->getCurrentUser();
$courseObj = new Course();

// Get tutor's courses
$tutorCourses = $courseObj->getCoursesByInstructor($userId);

// Process course creation
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $level = filter_input(INPUT_POST, 'level', FILTER_SANITIZE_STRING);
    
    if (empty($title)) {
        $error = 'Course title is required';
    } else {
        try {
            $courseId = $courseObj->createCourse($title, $description, $userId, $category, $level);
            if ($courseId) {
                $success = 'Course created successfully';
                // Redirect to course edit page
                header('Location: edit-course.php?id=' . $courseId);
                exit;
            } else {
                $error = 'Failed to create course';
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
    <title>My Courses - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Courses</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                <i class="bi bi-plus-circle me-2"></i>Create New Course
            </button>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (empty($tutorCourses)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                You haven't created any courses yet. Click the "Create New Course" button to get started.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($tutorCourses as $course): ?>
                    <div class="col">
                        <div class="card h-100">
                            <?php if (!empty($course['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>" style="height: 180px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light text-center py-5">
                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="card-text text-muted small">
                                    <?php echo !empty($course['category']) ? htmlspecialchars($course['category']) : 'Uncategorized'; ?> • 
                                    <?php echo !empty($course['level']) ? htmlspecialchars($course['level']) : 'All Levels'; ?>
                                </p>
                                <p class="card-text">
                                    <?php echo !empty($course['description']) ? 
                                        (strlen($course['description']) > 100 ? 
                                            htmlspecialchars(substr($course['description'], 0, 100)) . '...' : 
                                            htmlspecialchars($course['description'])) : 
                                        '<em>No description</em>'; ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="badge bg-<?php echo $course['is_published'] ? 'success' : 'warning'; ?>">
                                        <?php echo $course['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                    <div>
                                        <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="course-content.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-collection"></i> Content
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <small class="text-muted">
                                    <i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create Course Modal -->
    <div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createCourseModalLabel">Create New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Course Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Select Category</option>
                                <option value="Programming">Programming</option>
                                <option value="Web Development">Web Development</option>
                                <option value="Data Science">Data Science</option>
                                <option value="Mobile Development">Mobile Development</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Science">Science</option>
                                <option value="Language">Language</option>
                                <option value="Business">Business</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="level" class="form-label">Level</label>
                            <select class="form-select" id="level" name="level">
                                <option value="">Select Level</option>
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                                <option value="All Levels">All Levels</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_course" class="btn btn-primary">Create Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
