// Zoom functionality
let currentZoom = 100;

function zoomIn() {
    if (currentZoom < 200) {
        currentZoom += 25;
        updateZoom();
    }
}

function zoomOut() {
    if (currentZoom > 50) {
        currentZoom -= 25;
        updateZoom();
    }
}

function updateZoom() {
    const pages = document.querySelectorAll('.comic-page');
    const zoomLevel = document.querySelector('.zoom-level');
    
    pages.forEach(page => {
        page.style.transform = `scale(${currentZoom / 100})`;
        page.style.transformOrigin = 'top center';
    });
    
    if (zoomLevel) {
        zoomLevel.textContent = `${currentZoom}%`;
    }
}

// Fullscreen functionality
function toggleFullscreen() {
    const pagesContainer = document.getElementById('pagesContainer');
    
    if (!document.fullscreenElement) {
        if (pagesContainer.requestFullscreen) {
            pagesContainer.requestFullscreen();
        } else if (pagesContainer.webkitRequestFullscreen) {
            pagesContainer.webkitRequestFullscreen();
        } else if (pagesContainer.msRequestFullscreen) {
            pagesContainer.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const prevBtn = document.querySelector('.btn-prev');
    const nextBtn = document.querySelector('.btn-next');
    
    switch(e.key) {
        case 'ArrowLeft':
            if (prevBtn && !prevBtn.classList.contains('btn-disabled')) {
                prevBtn.click();
            }
            break;
        case 'ArrowRight':
            if (nextBtn && !nextBtn.classList.contains('btn-disabled')) {
                nextBtn.click();
            }
            break;
        case '+':
        case '=':
            e.preventDefault();
            zoomIn();
            break;
        case '-':
            e.preventDefault();
            zoomOut();
            break;
        case 'f':
        case 'F':
            e.preventDefault();
            toggleFullscreen();
            break;
        case 'Escape':
            if (document.fullscreenElement) {
                toggleFullscreen();
            }
            break;
    }
});

// Image error handling
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('.comic-page');
    
    images.forEach(img => {
        img.addEventListener('error', function() {
            this.src = 'placeholder-page.jpg';
            this.alt = 'Gambar tidak tersedia';
        });
        
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
    });
    
    // Progress tracking
    trackReadingProgress();
});

// Track reading progress
function trackReadingProgress() {
    const chapterId = new URLSearchParams(window.location.search).get('chapter_id');
    
    // Simpan ke localStorage
    const readChapters = JSON.parse(localStorage.getItem('readChapters') || '{}');
    const comicTitle = document.querySelector('.comic-name')?.textContent;
    
    if (comicTitle && chapterId) {
        readChapters[comicTitle] = chapterId;
        localStorage.setItem('readChapters', JSON.stringify(readChapters));
    }
}

// Auto-scroll (optional)
let autoScrollInterval = null;

function startAutoScroll() {
    if (autoScrollInterval) {
        stopAutoScroll();
        return;
    }
    
    const speed = 50; // pixels per second
    autoScrollInterval = setInterval(() => {
        window.scrollBy(0, 1);
        
        // Stop when reaching bottom
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
            stopAutoScroll();
        }
    }, 1000 / speed);
}

function stopAutoScroll() {
    if (autoScrollInterval) {
        clearInterval(autoScrollInterval);
        autoScrollInterval = null;
    }
}

// Add auto-scroll controls (optional)
document.addEventListener('DOMContentLoaded', function() {
    const controls = document.querySelector('.chapter-controls');
    if (controls) {
        const autoScrollBtn = document.createElement('button');
        autoScrollBtn.className = 'btn btn-control';
        autoScrollBtn.innerHTML = '▶️';
        autoScrollBtn.title = 'Auto Scroll';
        autoScrollBtn.onclick = startAutoScroll;
        controls.appendChild(autoScrollBtn);
    }
});

console.log('Chapter Reader loaded successfully!');

// Fungsi untuk membaca chapter
function readChapter(chapterId) {
    console.log('Membuka chapter ID:', chapterId);
    
    // Tambahkan loading state
    const buttons = document.querySelectorAll(`[onclick="readChapter(${chapterId})"]`);
    buttons.forEach(btn => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '📖 Membuka...';
        btn.disabled = true;
        
        // Reset setelah 2 detik (fallback)
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }, 2000);
    });
    
    // Redirect ke halaman baca chapter
    window.location.href = `read_chapter.php?chapter_id=${chapterId}`;
}