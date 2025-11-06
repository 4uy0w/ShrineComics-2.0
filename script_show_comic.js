// Comic Reader Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const chaptersSidebar = document.getElementById('chaptersSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            chaptersSidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    if (closeSidebar) {
        closeSidebar.addEventListener('click', closeSidebarFunc);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebarFunc);
    }

    function closeSidebarFunc() {
        chaptersSidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Reading Progress
    function updateReadingProgress() {
        const comicPages = document.getElementById('comicPages');
        const progressBar = document.getElementById('progressBar');
        
        if (!comicPages || !progressBar) return;

        const pages = comicPages.querySelectorAll('.page-container');
        if (pages.length === 0) return;

        const pageHeight = pages[0].offsetHeight;
        const totalHeight = pageHeight * pages.length;
        const scrollPosition = window.pageYOffset;
        const windowHeight = window.innerHeight;
        
        // Calculate which page we're on
        let currentPage = 0;
        pages.forEach((page, index) => {
            const rect = page.getBoundingClientRect();
            if (rect.top < windowHeight / 2) {
                currentPage = index + 1;
            }
        });

        const progress = (currentPage / pages.length) * 100;
        progressBar.style.width = `${progress}%`;
    }

    // Zoom Functionality
    let currentZoom = 1;
    const ZOOM_STEP = 0.2;
    const MIN_ZOOM = 0.5;
    const MAX_ZOOM = 3;

    function applyZoom() {
        const comicImages = document.querySelectorAll('.comic-page');
        comicImages.forEach(img => {
            img.style.transform = `scale(${currentZoom})`;
            if (currentZoom > 1) {
                img.classList.add('zoomed');
            } else {
                img.classList.remove('zoomed');
            }
        });
    }

    // Zoom Out
    const zoomOutBtn = document.getElementById('zoomOut');
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function() {
            if (currentZoom > MIN_ZOOM) {
                currentZoom -= ZOOM_STEP;
                applyZoom();
            }
        });
    }

    // Zoom In
    const zoomInBtn = document.getElementById('zoomIn');
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function() {
            if (currentZoom < MAX_ZOOM) {
                currentZoom += ZOOM_STEP;
                applyZoom();
            }
        });
    }

    // Zoom Reset
    const zoomResetBtn = document.getElementById('zoomReset');
    if (zoomResetBtn) {
        zoomResetBtn.addEventListener('click', function() {
            currentZoom = 1;
            applyZoom();
        });
    }

    // Image Click to Zoom
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('comic-page')) {
            if (e.target.classList.contains('zoomed')) {
                currentZoom = 1;
            } else {
                currentZoom = 2;
            }
            applyZoom();
        }
    });

    // Keyboard Navigation
    document.addEventListener('keydown', function(e) {
        // Close sidebar with ESC
        if (e.key === 'Escape') {
            if (chaptersSidebar.classList.contains('open')) {
                closeSidebarFunc();
            }
        }

        // Next/Previous chapter with arrow keys
        if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            const prevChapter = document.querySelector('.nav-btn.prev:not(.disabled)');
            const nextChapter = document.querySelector('.nav-btn.next:not(.disabled)');
            
            if (e.key === 'ArrowLeft' && prevChapter) {
                prevChapter.click();
            } else if (e.key === 'ArrowRight' && nextChapter) {
                nextChapter.click();
            }
        }
    });

    // Update progress on scroll
    window.addEventListener('scroll', updateReadingProgress);
    window.addEventListener('resize', updateReadingProgress);

    // Initial progress update
    updateReadingProgress();

    console.log('Comic reader initialized successfully');
});

// Global function for chapter selection
function changeChapter(chapterId) {
    if (chapterId) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('chapter_id', chapterId);
        window.location.href = currentUrl.toString();
    }
}

// Delete Chapter Function
function deleteChapter(chapterId, comicId) {
    if (!confirm('Apakah Anda yakin ingin menghapus chapter ini?\n\nSemua halaman dalam chapter juga akan dihapus.\nTindakan ini tidak dapat dibatalkan!')) {
        return;
    }

    const deleteBtn = event.target;
    const originalHTML = deleteBtn.innerHTML;
    
    // Show loading state
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '⏳ Menghapus...';

    // Create form data
    const formData = new FormData();
    formData.append('chapter_id', chapterId);
    formData.append('comic_id', comicId);
    
    fetch('delete_chapter.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network error');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            alert('Chapter berhasil dihapus!');
            
            // Remove chapter from sidebar
            const chapterElement = document.querySelector(`[data-chapter-id="${chapterId}"]`);
            if (chapterElement) {
                chapterElement.style.opacity = '0';
                chapterElement.style.height = '0';
                chapterElement.style.margin = '0';
                chapterElement.style.padding = '0';
                chapterElement.style.overflow = 'hidden';
                
                setTimeout(() => {
                    chapterElement.remove();
                    
                    // If we're currently viewing the deleted chapter, redirect to comic detail
                    const currentUrl = new URL(window.location.href);
                    const currentChapterId = currentUrl.searchParams.get('chapter_id');
                    if (currentChapterId == chapterId) {
                        window.location.href = `comic_detail.php?id=${comicId}`;
                    }
                }, 300);
            } else {
                // Fallback: redirect to comic detail
                window.location.href = `comic_detail.php?id=${comicId}`;
            }
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Gagal menghapus chapter: ' + error.message);
        deleteBtn.innerHTML = originalHTML;
        deleteBtn.disabled = false;
    });
}