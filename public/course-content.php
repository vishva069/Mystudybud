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

// Get course videos
$videos = $courseObj->getVideos($courseId);

// Get course books/PDFs
$books = $courseObj->getBooks($courseId);

// Process content upload
$success = '';
$error = '';

// Handle video upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_video'])) {
    $title = filter_input(INPUT_POST, 'video_title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'video_description', FILTER_SANITIZE_STRING);
    
    if (empty($title)) {
        $error = 'Video title is required';
    } elseif (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a video file to upload';
    } else {
        try {
            $uploadDir = 'uploads/videos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['video_file']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            // Check file type
            $videoFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
            $allowedTypes = ['mp4', 'webm', 'ogg', 'mov'];
            
            if (!in_array($videoFileType, $allowedTypes)) {
                $error = 'Only MP4, WebM, OGG & MOV files are allowed for videos';
            } else {
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadFile)) {
                    // Get video duration (this is a simplified version, in production you'd use a library like getID3)
                    $duration = 0; // Placeholder
                    
                    // Get the next position
                    $position = count($videos) + 1;
                    
                    if ($courseObj->addVideo($courseId, $title, $description, $uploadFile, $duration, $position, $userId)) {
                        $success = 'Video uploaded successfully';
                        // Refresh videos list
                        $videos = $courseObj->getVideos($courseId);
                    } else {
                        $error = 'Failed to save video information';
                        // Remove the uploaded file if database insert fails
                        if (file_exists($uploadFile)) {
                            unlink($uploadFile);
                        }
                    }
                } else {
                    $error = 'Failed to upload video file';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle book/PDF upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_book'])) {
    $title = filter_input(INPUT_POST, 'book_title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'book_description', FILTER_SANITIZE_STRING);
    
    if (empty($title)) {
        $error = 'Book title is required';
    } elseif (!isset($_FILES['book_file']) || $_FILES['book_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a PDF file to upload';
    } else {
        try {
            $uploadDir = 'uploads/books/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['book_file']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            // Check file type
            $fileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
            
            if ($fileType !== 'pdf') {
                $error = 'Only PDF files are allowed for books';
            } else {
                if (move_uploaded_file($_FILES['book_file']['tmp_name'], $uploadFile)) {
                    // Get file size
                    $fileSize = filesize($uploadFile);
                    
                    // Get the next position
                    $position = count($books) + 1;
                    
                    if ($courseObj->addBook($courseId, $title, $description, $uploadFile, $fileSize, $position, $userId)) {
                        $success = 'Book uploaded successfully';
                        // Refresh books list
                        $books = $courseObj->getBooks($courseId);
                    } else {
                        $error = 'Failed to save book information';
                        // Remove the uploaded file if database insert fails
                        if (file_exists($uploadFile)) {
                            unlink($uploadFile);
                        }
                    }
                } else {
                    $error = 'Failed to upload PDF file';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle content deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_video'])) {
    $videoId = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    
    if ($videoId) {
        if ($courseObj->deleteVideo($videoId, $courseId, $userId)) {
            $success = 'Video deleted successfully';
            // Refresh videos list
            $videos = $courseObj->getVideos($courseId);
        } else {
            $error = 'Failed to delete video';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
    
    if ($bookId) {
        if ($courseObj->deleteBook($bookId, $courseId, $userId)) {
            $success = 'Book deleted successfully';
            // Refresh books list
            $books = $courseObj->getBooks($courseId);
        } else {
            $error = 'Failed to delete book';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Content - <?php echo htmlspecialchars($course['title']); ?> - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Course Content: <?php echo htmlspecialchars($course['title']); ?></h1>
            <div>
                <a href="edit-course.php?id=<?php echo $courseId; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-pencil-square me-2"></i>Edit Course Details
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
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Course Content Tabs -->
                <ul class="nav nav-tabs" id="contentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videos" type="button" role="tab" aria-controls="videos" aria-selected="true">
                            <i class="bi bi-camera-video me-2"></i>Videos (<?php echo count($videos); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="books-tab" data-bs-toggle="tab" data-bs-target="#books" type="button" role="tab" aria-controls="books" aria-selected="false">
                            <i class="bi bi-file-pdf me-2"></i>Books/PDFs (<?php echo count($books); ?>)
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content p-3 border border-top-0 bg-white" id="contentTabsContent">
                    <!-- Videos Tab -->
                    <div class="tab-pane fade show active" id="videos" role="tabpanel" aria-labelledby="videos-tab">
                        <?php if (empty($videos)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                No videos have been added to this course yet. Use the form to upload your first video.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($videos as $index => $video): ?>
                                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-secondary me-3"><?php echo $index + 1; ?></span>
                                                <div>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($video['title']); ?></h5>
                                                    <p class="mb-1 text-muted small">
                                                        <?php 
                                                        $duration = $video['duration'] ?? 0;
                                                        $minutes = floor($duration / 60);
                                                        $seconds = $duration % 60;
                                                        echo sprintf('%d:%02d', $minutes, $seconds);
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($video['video_url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-play-circle"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                                <button type="submit" name="delete_video" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this video?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Books Tab -->
                    <div class="tab-pane fade" id="books" role="tabpanel" aria-labelledby="books-tab">
                        <?php if (empty($books)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                No books or PDFs have been added to this course yet. Use the form to upload your first PDF.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($books as $index => $book): ?>
                                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-secondary me-3"><?php echo $index + 1; ?></span>
                                                <div>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($book['title']); ?></h5>
                                                    <p class="mb-1 text-muted small">
                                                        <?php 
                                                        $fileSize = $book['file_size'] ?? 0;
                                                        $sizeInMB = round($fileSize / (1024 * 1024), 2);
                                                        echo $sizeInMB . ' MB';
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($book['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                <button type="submit" name="delete_book" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this book?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Upload Forms -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Video
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="video_title" class="form-label">Video Title *</label>
                                <input type="text" class="form-control" id="video_title" name="video_title" required>
                            </div>
                            <div class="mb-3">
                                <label for="video_description" class="form-label">Description</label>
                                <textarea class="form-control" id="video_description" name="video_description" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="video_file" class="form-label">Video File *</label>
                                <input type="file" class="form-control" id="video_file" name="video_file" accept="video/*" required>
                                <div class="form-text">Max file size: 100MB. Supported formats: MP4, WebM, OGG, MOV</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="upload_video" class="btn btn-primary">
                                    <i class="bi bi-upload me-2"></i>Upload Video
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Upload Book/PDF
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="book_title" class="form-label">Book Title *</label>
                                <input type="text" class="form-control" id="book_title" name="book_title" required>
                            </div>
                            <div class="mb-3">
                                <label for="book_description" class="form-label">Description</label>
                                <textarea class="form-control" id="book_description" name="book_description" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="book_file" class="form-label">PDF File *</label>
                                <input type="file" class="form-control" id="book_file" name="book_file" accept=".pdf" required>
                                <div class="form-text">Max file size: 50MB. Only PDF files are supported.</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="upload_book" class="btn btn-success">
                                    <i class="bi bi-upload me-2"></i>Upload PDF
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
