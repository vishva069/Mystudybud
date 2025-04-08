<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Course.php';

$auth = new Auth();
$course = new Course();

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$limit = 9; // Show 9 courses per page (3x3 grid)

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Get courses with pagination and filters
$courses = $course->getAllCourses($page, $limit, $category, $search);
$totalCourses = $course->getCourseCount($category, $search);
$totalPages = ceil($totalCourses / $limit);

// Get categories for filter
$categories = $course->getCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - StudyBud</title>
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
        .course-info {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        .badge-level {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .search-form {
            max-width: 500px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1 class="mb-0">Explore Courses</h1>
                <p class="text-muted">Discover our wide range of courses</p>
            </div>
            <div class="col-md-6">
                <form class="d-flex search-form ms-auto" action="courses.php" method="GET">
                    <?php if ($category): ?>
                        <input type="hidden" name="category" value="<?php echo h($category); ?>">
                    <?php endif; ?>
                    <input class="form-control me-2" type="search" name="search" placeholder="Search courses..." value="<?php echo h($search ?? ''); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap">
                    <a href="courses.php" class="btn <?php echo !$category ? 'btn-primary' : 'btn-outline-primary'; ?> me-2 mb-2">
                        All Courses
                    </a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="courses.php?category=<?php echo urlencode($cat); ?>" class="btn <?php echo $category === $cat ? 'btn-primary' : 'btn-outline-primary'; ?> me-2 mb-2">
                        <?php echo h($cat); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (empty($courses)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> No courses found. Please try a different search or category.
        </div>
        <?php endif; ?>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($courses as $c): ?>
            <div class="col">
                <div class="card course-card h-100">
                    <span class="badge bg-<?php 
                        echo $c['level'] === 'Beginner' ? 'success' : 
                            ($c['level'] === 'Intermediate' ? 'warning' : 'danger'); 
                    ?> badge-level"><?php echo h($c['level']); ?></span>
                    
                    <img src="<?php echo $c['thumbnail'] ? h($c['thumbnail']) : 'assets/img/course-placeholder.jpg'; ?>" 
                         class="card-img-top course-thumbnail" alt="<?php echo h($c['title']); ?>">
                    
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo h($c['title']); ?></h5>
                        <p class="card-text text-muted mb-3">
                            <?php echo strlen($c['description']) > 100 ? h(substr($c['description'], 0, 100)) . '...' : h($c['description']); ?>
                        </p>
                        
                        <div class="course-info mt-auto">
                            <span><i class="bi bi-person-circle me-1"></i> <?php echo h($c['instructor_name']); ?></span>
                            <span><i class="bi bi-collection-play me-1"></i> <?php echo (int)$c['video_count']; ?> videos</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <?php if ($c['price'] > 0): ?>
                            <span class="fw-bold">$<?php echo number_format($c['price'], 2); ?></span>
                            <?php else: ?>
                            <span class="badge bg-success">Free</span>
                            <?php endif; ?>
                            
                            <a href="course.php?id=<?php echo $c['id']; ?>" class="btn btn-primary">
                                View Course
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        Previous
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        Next
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
