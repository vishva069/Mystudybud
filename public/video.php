<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Course.php';

$auth = new Auth();
$courseModel = new Course();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get video ID from URL
$videoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no video ID provided, redirect to courses page
if ($videoId <= 0) {
    header('Location: courses.php');
    exit;
}

// Get video details
$video = $courseModel->getVideoById($videoId);

// If video not found, redirect to courses page
if (!$video) {
    header('Location: courses.php?error=video_not_found');
    exit;
}

// Check if user is enrolled in the course or if the video is free
$isEnrolled = $courseModel->isUserEnrolled($_SESSION['user_id'], $video['course_id']);
if (!$isEnrolled && !$video['is_free']) {
    header('Location: course.php?id=' . $video['course_id'] . '&error=not_enrolled');
    exit;
}

// Get course videos for navigation
$courseVideos = $courseModel->getCourseVideos($video['course_id']);

// Find current video index and next/previous videos
$currentIndex = -1;
$prevVideo = null;
$nextVideo = null;

foreach ($courseVideos as $index => $v) {
    if ($v['id'] == $videoId) {
        $currentIndex = $index;
        break;
    }
}

if ($currentIndex > 0) {
    $prevVideo = $courseVideos[$currentIndex - 1];
}

if ($currentIndex < count($courseVideos) - 1) {
    $nextVideo = $courseVideos[$currentIndex + 1];
}

// Get video progress
$progress = $courseModel->getVideoProgress($_SESSION['user_id'], $videoId);
$currentPosition = $progress ? $progress['position'] : 0;

// Check if video is saved
$isSaved = $courseModel->isVideoSaved($_SESSION['user_id'], $videoId);

// Handle save/unsave video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_save']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if ($isSaved) {
        $courseModel->unsaveVideo($_SESSION['user_id'], $videoId);
        $isSaved = false;
    } else {
        $courseModel->saveVideo($_SESSION['user_id'], $videoId);
        $isSaved = true;
    }
}

// Handle video progress update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $position = isset($_POST['position']) ? (int)$_POST['position'] : 0;
    $isCompleted = isset($_POST['is_completed']) ? (int)$_POST['is_completed'] : 0;
    
    $courseModel->updateVideoProgress($_SESSION['user_id'], $videoId, $position, $isCompleted);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($video['title']); ?> - StudyBud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            background-color: #000;
            border-radius: 8px;
        }
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .video-actions {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
        }
        .video-navigation {
            display: flex;
            justify-content: space-between;
            margin: 1rem 0;
        }
        .video-list {
            max-height: 400px;
            overflow-y: auto;
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
        .video-item.active {
            background-color: #e9ecef;
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
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
                        <li class="breadcrumb-item"><a href="course.php?id=<?php echo $video['course_id']; ?>"><?php echo h($video['course_title']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo h($video['title']); ?></li>
                    </ol>
                </nav>
                
                <h1 class="mb-4"><?php echo h($video['title']); ?></h1>
                
                <div class="video-container mb-4">
                    <video id="videoPlayer" controls preload="metadata" data-video-id="<?php echo $videoId; ?>">
                        <source src="<?php echo h($video['video_url']); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
                
                <div class="video-navigation">
                    <?php if ($prevVideo): ?>
                    <a href="video.php?id=<?php echo $prevVideo['id']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i> Previous
                    </a>
                    <?php else: ?>
                    <button class="btn btn-outline-primary" disabled>
                        <i class="bi bi-arrow-left me-2"></i> Previous
                    </button>
                    <?php endif; ?>
                    
                    <div class="video-actions">
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="toggle_save" value="1">
                            <button type="submit" class="btn btn-outline-<?php echo $isSaved ? 'danger' : 'primary'; ?>">
                                <i class="bi bi-<?php echo $isSaved ? 'bookmark-fill' : 'bookmark'; ?> me-2"></i>
                                <?php echo $isSaved ? 'Unsave' : 'Save'; ?>
                            </button>
                        </form>
                        
                        <button id="markCompleteBtn" class="btn btn-outline-success">
                            <i class="bi bi-check-circle me-2"></i> Mark as Complete
                        </button>
                    </div>
                    
                    <?php if ($nextVideo): ?>
                    <a href="video.php?id=<?php echo $nextVideo['id']; ?>" class="btn btn-primary" id="nextVideoBtn">
                        Next <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                    <?php else: ?>
                    <a href="course.php?id=<?php echo $video['course_id']; ?>" class="btn btn-success">
                        Complete Course <i class="bi bi-trophy ms-2"></i>
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <strong>Video Description</strong>
                    </div>
                    <div class="card-body">
                        <?php if ($video['description']): ?>
                        <p><?php echo nl2br(h($video['description'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted">No description available for this video.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <strong>Course Content</strong>
                    </div>
                    <div class="list-group list-group-flush video-list">
                        <?php foreach ($courseVideos as $index => $v): ?>
                        <a href="video.php?id=<?php echo $v['id']; ?>" 
                           class="list-group-item list-group-item-action video-item <?php echo $v['id'] == $videoId ? 'active' : ''; ?>">
                            <div class="video-number"><?php echo $index + 1; ?></div>
                            <div class="video-title">
                                <?php echo h($v['title']); ?>
                                <?php if ($v['is_free']): ?>
                                <span class="badge bg-success ms-2">Free</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($v['duration']): ?>
                            <div class="video-duration">
                                <?php echo floor($v['duration'] / 60); ?>:<?php echo str_pad($v['duration'] % 60, 2, '0', STR_PAD_LEFT); ?>
                            </div>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const videoPlayer = document.getElementById('videoPlayer');
        const markCompleteBtn = document.getElementById('markCompleteBtn');
        const nextVideoBtn = document.getElementById('nextVideoBtn');
        const videoId = videoPlayer.dataset.videoId;
        const csrfToken = '<?php echo generateCSRFToken(); ?>';
        let isCompleted = <?php echo $progress && $progress['is_completed'] ? 'true' : 'false'; ?>;
        
        // Set initial time if there's saved progress
        if (<?php echo $currentPosition; ?> > 0) {
            videoPlayer.currentTime = <?php echo $currentPosition; ?>;
        }
        
        // Update progress every 5 seconds
        let progressTimer;
        
        function startProgressTimer() {
            progressTimer = setInterval(function() {
                updateProgress(false);
            }, 5000);
        }
        
        function stopProgressTimer() {
            clearInterval(progressTimer);
        }
        
        // Update progress when video is played
        videoPlayer.addEventListener('play', function() {
            startProgressTimer();
        });
        
        // Update progress when video is paused
        videoPlayer.addEventListener('pause', function() {
            stopProgressTimer();
            updateProgress(false);
        });
        
        // Update progress when video ends
        videoPlayer.addEventListener('ended', function() {
            stopProgressTimer();
            updateProgress(true);
            isCompleted = true;
            updateCompletionUI();
            
            // Redirect to next video after 3 seconds if available
            if (nextVideoBtn) {
                setTimeout(function() {
                    window.location.href = nextVideoBtn.href;
                }, 3000);
            }
        });
        
        // Mark as complete button
        markCompleteBtn.addEventListener('click', function() {
            isCompleted = !isCompleted;
            updateProgress(isCompleted);
            updateCompletionUI();
        });
        
        function updateCompletionUI() {
            if (isCompleted) {
                markCompleteBtn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Completed';
                markCompleteBtn.classList.remove('btn-outline-success');
                markCompleteBtn.classList.add('btn-success');
            } else {
                markCompleteBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Mark as Complete';
                markCompleteBtn.classList.remove('btn-success');
                markCompleteBtn.classList.add('btn-outline-success');
            }
        }
        
        // Update initial UI
        updateCompletionUI();
        
        // Function to update progress
        function updateProgress(completed) {
            const currentTime = Math.floor(videoPlayer.currentTime);
            
            fetch('video.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_progress=1&video_id=${videoId}&position=${currentTime}&is_completed=${completed ? 1 : 0}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Progress updated:', data);
            })
            .catch(error => {
                console.error('Error updating progress:', error);
            });
        }
    });
    </script>
</body>
</html>
