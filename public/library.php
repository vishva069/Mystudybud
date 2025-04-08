<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Course.php';

$auth = new Auth();
$courseModel = new Course();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user = $auth->getCurrentUser();

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$limit = 9; // Show 9 courses per page (3x3 grid)

// Get enrolled courses with pagination
$enrolledCourses = $courseModel->getUserEnrollments($_SESSION['user_id'], $page, $limit);
$totalEnrollments = $courseModel->getUserEnrollmentCount($_SESSION['user_id']);
$totalPages = ceil($totalEnrollments / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Library - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .course-card {
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .course-thumbnail {
            height: 180px;
            object-fit: cover;
        }
        .progress-container {
            margin-top: 15px;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        .course-info {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1>My Library</h1>
                <p class="text-muted">Courses you've enrolled in</p>
            </div>
            <div class="col-md-6 text-end">
                <a href="courses.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i> Explore More Courses
                </a>
            </div>
        </div>

        <?php if (empty($enrolledCourses)): ?>
        <div class="text-center py-5">
            <i class="bi bi-collection-play fs-1 text-muted mb-3 d-block"></i>
            <h3 class="text-muted">You haven't enrolled in any courses yet</h3>
            <p class="text-muted mb-4">Explore our courses and start learning today!</p>
            <a href="courses.php" class="btn btn-primary btn-lg">Browse Courses</a>
        </div>
        <?php else: ?>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($enrolledCourses as $course): ?>
            <div class="col">
                <div class="card course-card h-100">
                    <img src="<?php echo $course['thumbnail'] ? h($course['thumbnail']) : 'assets/img/course-placeholder.jpg'; ?>" 
                         class="card-img-top course-thumbnail" alt="<?php echo h($course['title']); ?>">
                    
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo h($course['title']); ?></h5>
                        <p class="card-text text-muted mb-3">
                            <?php echo strlen($course['description']) > 100 ? h(substr($course['description'], 0, 100)) . '...' : h($course['description']); ?>
                        </p>
                        
                        <div class="progress-container mt-auto">
                            <?php 
                                $progress = 0;
                                if ($course['total_videos'] > 0) {
                                    $progress = ($course['completed_videos'] / $course['total_videos']) * 100;
                                }
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small"><?php echo round($progress); ?>% complete</span>
                                <span class="small"><?php echo $course['completed_videos']; ?>/<?php echo $course['total_videos']; ?> videos</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%;" 
                                     aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge bg-<?php 
                                echo $course['level'] === 'Beginner' ? 'success' : 
                                    ($course['level'] === 'Intermediate' ? 'warning' : 'danger'); 
                            ?>"><?php echo h($course['level'] ?? 'Beginner'); ?></span>
                            
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                Continue Learning
                            </a>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        <small>Enrolled on: <?php echo date('M d, Y', strtotime($course['enrolled_at'])); ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
