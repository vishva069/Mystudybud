// Main JavaScript file for StudyBud

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize video player progress tracking if on video page
    if (document.getElementById('video-player')) {
        initVideoProgress();
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

// Function to handle video progress tracking
function initVideoProgress() {
    const videoPlayer = document.getElementById('video-player');
    const videoId = videoPlayer.dataset.videoId;
    const progressBar = document.getElementById('video-progress');
    
    // Update progress every 5 seconds
    setInterval(function() {
        if (!videoPlayer.paused) {
            updateVideoProgress(videoId, videoPlayer.currentTime, videoPlayer.duration);
        }
    }, 5000);
    
    // Update progress bar as video plays
    videoPlayer.addEventListener('timeupdate', function() {
        const percentage = (videoPlayer.currentTime / videoPlayer.duration) * 100;
        progressBar.style.width = percentage + '%';
    });
}

// Function to send progress update to server
function updateVideoProgress(videoId, currentTime, duration) {
    const progress = Math.floor((currentTime / duration) * 100);
    
    fetch('api/update-progress.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            video_id: videoId,
            current_time: currentTime,
            progress: progress
        })
    })
    .then(response => response.json())
    .then(data => console.log('Progress updated'))
    .catch(error => console.error('Error updating progress:', error));
}
