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
$limit = 10; // Show 10 videos per page

// Get saved videos with pagination
$savedVideos = $courseModel->getSavedVideos($_SESSION['user_id'], $page, $limit);
$totalSaved = $courseModel->getSavedVideoCount($_SESSION['user_id']);
$totalPages = ceil($totalSaved / $limit);

// Handle unsave video
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsave']) && isset($_POST['video_id']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $videoId = (int)$_POST['video_id'];
    if ($courseModel->unsaveVideo($_SESSION['user_id'], $videoId)) {
        // Remove the unsaved video from the array
        foreach ($savedVideos as $key => $video) {
            if ($video['id'] == $videoId) {
                unset($savedVideos[$key]);
                break;
            }
        }
        $message = 'Video removed from saved items.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Videos - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .video-item {
            transition: background-color 0.2s;
        }
        .video-item:hover {
            background-color: #f8f9fa;
        }
        .video-thumbnail {
            width: 160px;
            height: 90px;
            object-fit: cover;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .video-thumbnail {
                width: 120px;
                height: 67px;
            }
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1>Saved Videos</h1>
                <p class="text-muted">Videos you've bookmarked for later</p>
            </div>
            <div class="col-md-6 text-end">
                <a href="courses.php" class="btn btn-primary">
                    <i class="bi bi-collection-play me-2"></i> Browse Courses
                </a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo h($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($savedVideos)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bookmark fs-1 text-muted mb-3 d-block"></i>
            <h3 class="text-muted">You haven't saved any videos yet</h3>
            <p class="text-muted mb-4">Save videos to watch them later or keep them for reference</p>
            <a href="courses.php" class="btn btn-primary btn-lg">Browse Courses</a>
        </div>
        <?php else: ?>

        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span><strong>Your Saved Videos</strong> (<?php echo $totalSaved; ?>)</span>
                <span class="text-muted small">Click on a video to watch it</span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($savedVideos as $video): ?>
                <div class="list-group-item video-item p-3">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <a href="video.php?id=<?php echo $video['id']; ?>">
                                <img src="<?php echo $video['thumbnail'] ?? 'assets/img/video-placeholder.jpg'; ?>" 
                                     class="video-thumbnail" alt="Video thumbnail">
                            </a>
                        </div>
                        <div class="col">
                            <a href="video.php?id=<?php echo $video['id']; ?>" class="text-decoration-none text-dark">
                                <h5 class="mb-1"><?php echo h($video['title']); ?></h5>
                            </a>
                            <p class="mb-1 text-muted">
                                <a href="course.php?id=<?php echo $video['course_id']; ?>" class="text-decoration-none text-muted">
                                    <?php echo h($video['course_title']); ?>
                                </a>
                            </p>
                            <p class="mb-0 small text-muted">
                                Saved on <?php echo date('M d, Y', strtotime($video['saved_at'])); ?>
                            </p>
                        </div>
                        <div class="col-auto action-buttons">
                            <a href="video.php?id=<?php echo $video['id']; ?>" class="btn btn-primary btn-sm me-2">
                                <i class="bi bi-play-fill"></i> Watch
                            </a>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <input type="hidden" name="unsave" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm" 
                                        onclick="return confirm('Remove this video from saved items?')">
                                    <i class="bi bi-bookmark-x"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
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
