<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Course.php';

$auth = new Auth();
$courseModel = new Course();

// Get course ID from URL
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no course ID provided, redirect to courses page
if ($courseId <= 0) {
    header('Location: courses.php');
    exit;
}

// Get course details
$course = $courseModel->getCourseById($courseId);

// If course not found, redirect to courses page
if (!$course) {
    header('Location: courses.php?error=course_not_found');
    exit;
}

// Get course videos
$videos = $courseModel->getCourseVideos($courseId);

// Check if user is enrolled
$isEnrolled = false;
if ($auth->isLoggedIn()) {
    $isEnrolled = $courseModel->isUserEnrolled($_SESSION['user_id'], $courseId);
}

// Handle enrollment
$enrollmentError = '';
$enrollmentSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll']) && $auth->isLoggedIn()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $enrollmentError = 'Invalid request';
    } else {
        if ($courseModel->enrollUser($_SESSION['user_id'], $courseId)) {
            $enrollmentSuccess = 'You have successfully enrolled in this course!';
            $isEnrolled = true;
        } else {
            $enrollmentError = 'Failed to enroll in this course. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($course['title']); ?> - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .course-header {
            background-color: #f8f9fa;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        .course-thumbnail {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .course-stats {
            display: flex;
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        .course-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .video-list {
            border-radius: 8px;
            overflow: hidden;
        }
        .video-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }
        .video-item:hover {
            background-color: #f8f9fa;
        }
        .video-item.locked {
            opacity: 0.7;
        }
        .video-number {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e9ecef;
            border-radius: 50%;
            margin-right: 1rem;
        }
        .video-title {
            flex-grow: 1;
        }
        .video-duration {
            color: #6c757d;
            font-size: 0.9rem;
            margin-left: 1rem;
        }
        .enrollment-card {
            position: sticky;
            top: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="course-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo h($course['title']); ?></li>
                        </ol>
                    </nav>
                    
                    <h1 class="mb-3"><?php echo h($course['title']); ?></h1>
                    
                    <p class="lead"><?php echo h($course['description']); ?></p>
                    
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-<?php 
                            echo $course['level'] === 'Beginner' ? 'success' : 
                                ($course['level'] === 'Intermediate' ? 'warning' : 'danger'); 
                        ?> me-2"><?php echo h($course['level']); ?></span>
                        
                        <?php if ($course['category']): ?>
                        <span class="badge bg-primary me-2"><?php echo h($course['category']); ?></span>
                        <?php endif; ?>
                        
                        <?php if ($course['price'] <= 0): ?>
                        <span class="badge bg-success">Free</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="course-stats">
                        <div class="course-stat">
                            <i class="bi bi-person-circle"></i>
                            <span><?php echo h($course['instructor_full_name']); ?></span>
                        </div>
                        <div class="course-stat">
                            <i class="bi bi-collection-play"></i>
                            <span><?php echo (int)$course['video_count']; ?> videos</span>
                        </div>
                        <div class="course-stat">
                            <i class="bi bi-people"></i>
                            <span><?php echo (int)$course['enrollment_count']; ?> students</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <img src="<?php echo $course['thumbnail'] ? h($course['thumbnail']) : 'assets/img/course-placeholder.jpg'; ?>" 
                         class="course-thumbnail" alt="<?php echo h($course['title']); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <?php if ($enrollmentError): ?>
            <div class="alert alert-danger"><?php echo h($enrollmentError); ?></div>
        <?php endif; ?>
        
        <?php if ($enrollmentSuccess): ?>
            <div class="alert alert-success"><?php echo h($enrollmentSuccess); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Course Content</h2>
                
                <?php if (empty($videos)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> No videos available for this course yet.
                </div>
                <?php else: ?>
                <div class="card video-list mb-4">
                    <div class="card-header bg-light">
                        <strong><?php echo count($videos); ?> videos</strong>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($videos as $index => $video): ?>
                        <a href="<?php echo $isEnrolled || $video['is_free'] ? 'video.php?id=' . $video['id'] : '#'; ?>" 
                           class="list-group-item list-group-item-action video-item <?php echo (!$isEnrolled && !$video['is_free']) ? 'locked' : ''; ?>">
                            <div class="video-number"><?php echo $index + 1; ?></div>
                            <div class="video-title">
                                <?php echo h($video['title']); ?>
                                <?php if ($video['is_free']): ?>
                                <span class="badge bg-success ms-2">Free</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isEnrolled && !$video['is_free']): ?>
                            <i class="bi bi-lock-fill text-muted"></i>
                            <?php endif; ?>
                            <?php if ($video['duration']): ?>
                            <div class="video-duration">
                                <?php echo floor($video['duration'] / 60); ?>:<?php echo str_pad($video['duration'] % 60, 2, '0', STR_PAD_LEFT); ?>
                            </div>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <strong>About the Instructor</strong>
                    </div>
                    <div class="card-body">
                        <h5><?php echo h($course['instructor_full_name']); ?></h5>
                        <p class="text-muted">@<?php echo h($course['instructor_name']); ?></p>
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam auctor, nisl eget ultricies
                            tincidunt, nisl nisl aliquam nisl, eget ultricies nisl nisl eget nisl.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card enrollment-card">
                    <div class="card-body">
                        <?php if ($course['price'] > 0): ?>
                        <h3 class="card-title mb-4">$<?php echo number_format($course['price'], 2); ?></h3>
                        <?php else: ?>
                        <h3 class="card-title mb-4 text-success">Free</h3>
                        <?php endif; ?>
                        
                        <?php if ($isEnrolled): ?>
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle-fill me-2"></i> You are enrolled in this course
                        </div>
                        
                        <?php if (!empty($videos)): ?>
                        <a href="video.php?id=<?php echo $videos[0]['id']; ?>" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="bi bi-play-circle me-2"></i> Start Learning
                        </a>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="enroll" value="1">
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="bi bi-mortarboard me-2"></i> Enroll Now
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <ul class="list-group list-group-flush mt-3">
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-collection-play me-3"></i>
                                <span><?php echo (int)$course['video_count']; ?> videos</span>
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-clock me-3"></i>
                                <span>Full lifetime access</span>
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-trophy me-3"></i>
                                <span>Certificate of completion</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
