<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Course.php';

$auth = new Auth();
$courseModel = new Course();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();

// Get enrolled courses
$enrolledCourses = $courseModel->getUserEnrollments($_SESSION['user_id'], 1, 3);

// Get saved videos
$savedVideos = $courseModel->getSavedVideos($_SESSION['user_id'], 1, 3);

// Get featured courses
$featuredCourses = $courseModel->getFeaturedCourses(3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">StudyBud</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">
                            <i class="bi bi-book"></i> Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="library.php">
                            <i class="bi bi-collection"></i> Library
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="saved.php">
                            <i class="bi bi-bookmark"></i> Saved
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                    </li>
                </ul>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i> <?php echo h($user['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1>Welcome, <?php echo h($user['full_name']); ?>!</h1>
                <p class="lead">Continue your learning journey.</p>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search courses...">
                    <button class="btn btn-primary" type="button">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Continue Watching</h5>
                        <a href="history.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (empty($enrolledCourses)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-collection-play fs-1 text-muted mb-3 d-block"></i>
                                <h5 class="text-muted">You haven't enrolled in any courses yet</h5>
                                <a href="courses.php" class="btn btn-primary mt-3">Browse Courses</a>
                            </div>
                            <?php else: ?>
                                <?php foreach ($enrolledCourses as $course): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <img src="<?php echo $course['thumbnail'] ? h($course['thumbnail']) : 'assets/img/course-placeholder.jpg'; ?>" 
                                             class="card-img-top" alt="<?php echo h($course['title']); ?>" style="height: 150px; object-fit: cover;">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo h($course['title']); ?></h5>
                                            <p class="card-text small text-muted"><?php echo h($course['category'] ?? 'General'); ?></p>
                                            <?php 
                                                $progress = 0;
                                                if ($course['total_videos'] > 0) {
                                                    $progress = ($course['completed_videos'] / $course['total_videos']) * 100;
                                                }
                                            ?>
                                            <div class="progress mb-2" style="height: 5px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%;" 
                                                     aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <p class="card-text small text-muted">
                                                <?php echo round($progress); ?>% complete 
                                                (<?php echo $course['completed_videos']; ?>/<?php echo $course['total_videos']; ?> videos)
                                            </p>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">Continue</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recommended Courses</h5>
                        <a href="courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (empty($featuredCourses)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-stars fs-1 text-muted mb-3 d-block"></i>
                                <h5 class="text-muted">No featured courses available</h5>
                            </div>
                            <?php else: ?>
                                <?php foreach ($featuredCourses as $course): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <img src="<?php echo $course['thumbnail'] ? h($course['thumbnail']) : 'assets/img/course-placeholder.jpg'; ?>" 
                                             class="card-img-top" alt="<?php echo h($course['title']); ?>" style="height: 150px; object-fit: cover;">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo h($course['title']); ?></h5>
                                            <p class="card-text">
                                                <?php echo strlen($course['description']) > 100 ? h(substr($course['description'], 0, 100)) . '...' : h($course['description']); ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-success"><?php echo (int)$course['video_count']; ?> videos</span>
                                                <span class="text-muted small">By <?php echo h($course['instructor_name']); ?></span>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">View Course</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-success">10 videos</span>
                                            <span class="text-muted small">5.5 hours</span>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="#" class="btn btn-primary btn-sm">Enroll Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-light mt-5">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-6">
                    <h5>StudyBud</h5>
                    <p>Making learning accessible to everyone.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> StudyBud. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
