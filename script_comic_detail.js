// ===== FITUR PEMBELIAN CHAPTER (HANYA UNTUK READER) =====

let currentPurchaseData = null;

// Fungsi untuk membeli chapter - HANYA untuk reader
function buyChapter(chapterId, chapterPrice, userPoints) {
    console.log('Buy chapter:', chapterId, 'Price:', chapterPrice, 'User points:', userPoints);
    
    // CEK PENTING: Jangan izinkan writer membeli komik sendiri
    const isComicOwner = document.body.getAttribute('data-is-comic-owner') === 'true';
    if (isComicOwner) {
        showNotification('Anda adalah penulis komik ini! Silakan baca secara gratis.', 'info');
        window.location.href = `read_chapter.php?chapter_id=${chapterId}`;
        return;
    }
    
    if (!chapterId || chapterId === 0) {
        showNotification('Error: Chapter ID tidak valid', 'error');
        return;
    }
    
    // Cek apakah user sudah login
    const isLoggedIn = document.body.getAttribute('data-user-id') !== '0';
    if (!isLoggedIn) {
        showNotification('Silakan login terlebih dahulu untuk membeli chapter', 'error');
        setTimeout(() => {
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
        }, 2000);
        return;
    }
    
    // Cek saldo point
    if (userPoints < chapterPrice) {
        showNotification(`Point tidak cukup! Dibutuhkan ${chapterPrice} point, Anda memiliki ${userPoints} point`, 'error');
        return;
    }
    
    // Tampilkan modal konfirmasi
    showPurchaseModal(chapterId, chapterPrice, userPoints);
}

// Tampilkan modal konfirmasi pembelian
function showPurchaseModal(chapterId, price, userPoints) {
    const modal = document.getElementById('purchaseModal');
    if (!modal) {
        showNotification('Modal pembelian tidak tersedia', 'error');
        return;
    }
    
    const chapterCard = document.querySelector(`[data-chapter-id="${chapterId}"]`);
    const chapterTitle = chapterCard?.querySelector('.chapter-title')?.textContent || 'Chapter';
    const chapterNumber = chapterCard?.querySelector('.chapter-number')?.textContent || '';
    
    currentPurchaseData = { chapterId, price, userPoints };
    
    // Isi detail pembelian
    document.getElementById('purchaseMessage').textContent = `Anda akan membeli ${chapterNumber}: ${chapterTitle}`;
    document.getElementById('detailChapter').textContent = `${chapterNumber}: ${chapterTitle}`;
    document.getElementById('detailPrice').textContent = `${price} points`;
    document.getElementById('detailBalance').textContent = `${userPoints} points`;
    document.getElementById('detailRemaining').textContent = `${userPoints - price} points`;
    
    // Tampilkan modal
    modal.style.display = 'block';
    
    // Setup confirm button
    const confirmBtn = document.getElementById('confirmPurchaseBtn');
    confirmBtn.onclick = () => confirmPurchase(chapterId, price);
}

// Tutup modal pembelian
function closePurchaseModal() {
    const modal = document.getElementById('purchaseModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentPurchaseData = null;
}

// Konfirmasi pembelian
function confirmPurchase(chapterId, chapterPrice) {
    if (!currentPurchaseData) return;
    
    const confirmBtn = document.getElementById('confirmPurchaseBtn');
    const originalText = confirmBtn.innerHTML;
    
    // Show loading state
    confirmBtn.innerHTML = '🔄 Memproses...';
    confirmBtn.disabled = true;
    
    showNotification('Memproses pembelian...', 'info');
    
    // Kirim request pembelian
    const formData = new FormData();
    formData.append('chapter_id', chapterId);
    formData.append('chapter_price', chapterPrice);
    
    fetch('buy_chapter.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closePurchaseModal();
            
            // Update UI setelah pembelian berhasil
            updateChapterUI(chapterId, data.new_balance);
            
            // Redirect ke halaman baca setelah 1.5 detik
            setTimeout(() => {
                window.location.href = `read_chapter.php?chapter_id=${chapterId}`;
            }, 1500);
            
        } else {
            showNotification('Gagal: ' + data.message, 'error');
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Terjadi kesalahan saat memproses pembelian', 'error');
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    });
}

// Update UI setelah pembelian
function updateChapterUI(chapterId, newBalance) {
    const chapterCards = document.querySelectorAll(`[data-chapter-id="${chapterId}"]`);
    
    chapterCards.forEach(chapterCard => {
        chapterCard.classList.add('owned');
        chapterCard.classList.remove('purchasable');
        
        const badgeContainer = chapterCard.querySelector('.chapter-badge');
        const existingOwnedBadge = badgeContainer.querySelector('.badge-owned');
        if (!existingOwnedBadge) {
            const newBadge = document.createElement('span');
            newBadge.className = 'badge-owned';
            newBadge.textContent = '✅ DIMILIKI';
            badgeContainer.appendChild(newBadge);
        }
        
        const priceContainer = chapterCard.querySelector('.chapter-price');
        priceContainer.innerHTML = '<span class="owned-tag">✅ Sudah Dibeli</span>';
        
        const actionContainer = chapterCard.querySelector('.chapter-actions');
        const managementActions = actionContainer.querySelector('.chapter-management-actions');
        
        actionContainer.innerHTML = `
            <a href="read_chapter.php?chapter_id=${chapterId}" class="btn btn-success btn-full">
                📖 Baca Chapter
            </a>
            ${managementActions ? managementActions.outerHTML : ''}
        `;
    });
    
    updateUserPointsDisplay(newBalance);
    document.body.setAttribute('data-user-points', newBalance);
}

// Update tampilan point user
function updateUserPointsDisplay(newBalance) {
    const userPointsElement = document.querySelector('.user-points');
    if (userPointsElement) {
        userPointsElement.textContent = `💰 ${newBalance} points`;
    }
    
    const statPoints = document.querySelector('.quick-stats .stat-item:nth-child(3) .stat-text');
    if (statPoints) {
        statPoints.textContent = `${newBalance} Points`;
    }
    
    updatePurchaseButtonsAffordability(newBalance);
}

// Update status affordability tombol beli
function updatePurchaseButtonsAffordability(newBalance) {
    const purchaseButtons = document.querySelectorAll('.btn-warning:not(:disabled)');
    purchaseButtons.forEach(button => {
        const onclickAttr = button.getAttribute('onclick');
        const match = onclickAttr.match(/buyChapter\((\d+),\s*(\d+),\s*(\d+)\)/);
        
        if (match) {
            const chapterId = parseInt(match[1]);
            const chapterPrice = parseInt(match[2]);
            const canAfford = newBalance >= chapterPrice;
            
            if (!canAfford) {
                button.disabled = true;
                button.innerHTML = `❌ Kurang ${chapterPrice - newBalance} points`;
            }
        }
    });
}

// ===== SISTEM KOMENTAR LENGKAP =====

// ===== COMMENT SYSTEM VARIABLES =====
const CommentSystem = {
    isSubmitting: false,
    lastCommentCheck: Date.now(),
    autoRefreshInterval: null,
    isAutoRefreshEnabled: true
};

// ===== COMMENT SYSTEM INITIALIZATION =====
function initCommentSystem() {
    console.log('💬 Initializing Comment System...');
    CommentSystem.bindEvents();
    CommentSystem.bindReplyFormEvents(); 
    CommentSystem.setupAutoSave();
    CommentSystem.startAutoRefresh();
    console.log('✅ Comment System initialized');
}

// ===== COMMENT SYSTEM FUNCTIONS =====
CommentSystem.bindEvents = function() {
    const commentTextarea = document.querySelector('textarea[name="comment_sender_text"]');
    const charCount = document.getElementById('charCount');
    
    if (commentTextarea && charCount) {
        commentTextarea.addEventListener('input', function(e) {
            CommentSystem.updateCharacterCounter(e.target, charCount);
        });
        CommentSystem.updateCharacterCounter(commentTextarea, charCount);
    }

    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            CommentSystem.submitComment();
        });
    }

    // Keyboard shortcut
    if (commentTextarea) {
        commentTextarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                CommentSystem.submitComment();
            }
        });
    }

    this.bindExistingReplyButtons();
};

CommentSystem.bindExistingReplyButtons = function() {
    // Bind untuk komentar yang sudah ada di halaman
    document.querySelectorAll('.btn-reply').forEach(button => {
        // Hapus event listener lama jika ada
        button.replaceWith(button.cloneNode(true));
    });

    // Bind event listener baru
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-reply')) {
            e.preventDefault();
            const commentId = e.target.closest('.comment-card').getAttribute('data-comment-id');
            CommentSystem.showReplyForm(commentId);
        }
    });
};

CommentSystem.updateCharacterCounter = function(textarea, charCount) {
    const length = textarea.value.length;
    charCount.textContent = length;
    
    if (length > 1000) {
        charCount.className = 'char-counter error';
    } else if (length > 800) {
        charCount.className = 'char-counter warning';
    } else {
        charCount.className = 'char-counter';
    }
};

CommentSystem.setupAutoSave = function() {
    const textarea = document.querySelector('textarea[name="comment_sender_text"]');
    if (!textarea) return;

    const comicId = document.body.getAttribute('data-comic-id');
    const storageKey = `comment_draft_${comicId}`;

    // Load saved draft
    const savedDraft = localStorage.getItem(storageKey);
    if (savedDraft) {
        textarea.value = savedDraft;
        textarea.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            showNotification('Draft komentar berhasil dipulihkan', 'info');
        }, 500);
    }

    // Auto-save on input
    let saveTimeout;
    textarea.addEventListener('input', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            if (textarea.value.trim()) {
                localStorage.setItem(storageKey, textarea.value);
            } else {
                localStorage.removeItem(storageKey);
            }
        }, 1000);
    });
};

// ===== AUTO REFRESH SYSTEM =====
CommentSystem.startAutoRefresh = function() {
    // Check every 30 seconds
    this.autoRefreshInterval = setInterval(() => {
        if (this.isAutoRefreshEnabled) {
            this.checkNewComments();
        }
    }, 30000);
    
    // Initial check after 5 seconds
    setTimeout(() => {
        this.checkNewComments();
    }, 5000);
};

CommentSystem.checkNewComments = async function() {
    if (this.isSubmitting) return;
    
    try {
        const comicId = document.body.getAttribute('data-comic-id');
        const lastCheck = this.lastCommentCheck;
        
        if (!comicId || comicId === '0') return;
        
        const response = await fetch(`check_new_comments.php?comic_id=${comicId}&last_check=${lastCheck}&t=${Date.now()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.new_comments && data.new_comments.length > 0) {
            console.log(`🆕 Found ${data.new_comments.length} new comments`);
            
            let addedCount = 0;
            data.new_comments.forEach(comment => {
                if (!document.querySelector(`[data-comment-id="${comment.comment_id}"]`)) {
                    this.addNewCommentToUI(comment, false);
                    addedCount++;
                }
            });
            
            this.lastCommentCheck = Date.now();
            
            if (addedCount > 0) {
                this.showNewCommentsNotification(addedCount);
            }
        }
    } catch (error) {
        console.error('Error checking new comments:', error);
    }
};

CommentSystem.showNewCommentsNotification = function(count) {
    // Remove existing notification
    const existingNotification = document.querySelector('.new-comments-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = 'new-comments-notification';
    notification.innerHTML = `
        <span>💬 ${count} komentar baru</span>
        <button onclick="this.parentElement.remove()" title="Tutup">×</button>
    `;
    
    // Add click to scroll to comments
    notification.addEventListener('click', function() {
        const commentsSection = document.getElementById('comments');
        if (commentsSection) {
            commentsSection.scrollIntoView({ behavior: 'smooth' });
        }
        notification.remove();
    });
    
    document.body.appendChild(notification);
    
    // Auto remove after 8 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 8000);
};

// ===== MANUAL REFRESH =====
CommentSystem.manualRefreshComments = function() {
    console.log('🔄 Manual refresh comments...');
    this.checkNewComments();
    showNotification('Memperbarui komentar...', 'info');
};

// ===== TOGGLE AUTO REFRESH =====
CommentSystem.toggleAutoRefresh = function() {
    this.isAutoRefreshEnabled = !this.isAutoRefreshEnabled;
    const status = this.isAutoRefreshEnabled ? 'diaktifkan' : 'dinonaktifkan';
    showNotification(`Auto-refresh komentar ${status}`, 'info');
    this.updateAutoRefreshUI();
};

CommentSystem.updateAutoRefreshUI = function() {
    const statusElement = document.getElementById('autoRefreshStatus');
    const toggleButton = document.getElementById('autoRefreshToggle');
    
    if (statusElement && toggleButton) {
        statusElement.textContent = this.isAutoRefreshEnabled ? 'Aktif' : 'Nonaktif';
        statusElement.style.color = this.isAutoRefreshEnabled ? '#28a745' : '#dc3545';
        
        toggleButton.textContent = this.isAutoRefreshEnabled ? 'Nonaktifkan' : 'Aktifkan';
        toggleButton.className = this.isAutoRefreshEnabled ? 'auto-refresh-toggle' : 'auto-refresh-toggle disabled';
    }
};

// ===== REPLY SYSTEM =====
CommentSystem.showReplyForm = function(commentId) {
    // Hide other open reply forms
    this.hideAllReplyForms();
    
    const replyForm = document.getElementById(`replyForm-${commentId}`);
    if (replyForm) {
        replyForm.style.display = 'block';
        
        // Focus on textarea
        const textarea = replyForm.querySelector('textarea');
        if (textarea) {
            textarea.focus();
            
            // Auto-resize textarea
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        
        // Scroll to reply form
        setTimeout(() => {
            replyForm.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center'
            });
        }, 100);
    }else {
        console.error('❌ Reply form not found for comment:', commentId);
    }
};

CommentSystem.hideReplyForm = function(commentId) {
    const replyForm = document.getElementById(`replyForm-${commentId}`);
    if (replyForm) {
        replyForm.style.display = 'none';
        const textarea = replyForm.querySelector('textarea');
        if (textarea) {
            textarea.value = '';
            textarea.style.height = 'auto';
        }
        const charCount = replyForm.querySelector('.reply-char-count');
        if (charCount) {
            charCount.textContent = '0';
            charCount.className = 'reply-char-count';
        }
    }
};

CommentSystem.hideAllReplyForms = function() {
    document.querySelectorAll('.reply-form-container').forEach(form => {
        form.style.display = 'none';
        const textarea = form.querySelector('textarea');
        if (textarea) {
            textarea.value = '';
            textarea.style.height = 'auto';
        }
    });
};

CommentSystem.submitReply = async function(event, commentId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    // Validation
    const replyText = formData.get('reply_text').trim();
    if (!replyText) {
        showNotification('Balasan tidak boleh kosong!', 'error');
        return;
    }

    if (replyText.length > 500) {
        showNotification('Balasan terlalu panjang! Maksimal 500 karakter.', 'error');
        return;
    }

    // Show loading
    submitBtn.innerHTML = '🔄 Mengirim...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('add_reply.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('💬 Balasan berhasil dikirim!', 'success');
            this.hideReplyForm(commentId);
            this.loadReplies(commentId);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Terjadi kesalahan', 'error');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
};

CommentSystem.loadReplies = async function(commentId) {
    const repliesContainer = document.getElementById(`replies-${commentId}`);
    if (!repliesContainer) return;

    try {
        const response = await fetch(`get_replies.php?comment_id=${commentId}`);
        const data = await response.json();
        
        if (data.success) {
            repliesContainer.innerHTML = data.replies_html;
            
            // Bind events untuk balasan baru
            this.bindReplyEvents(commentId);
        }
    } catch (error) {
        console.error('Error loading replies:', error);
    }
};

CommentSystem.bindReplyEvents = function(commentId) {
    const repliesContainer = document.getElementById(`replies-${commentId}`);
    if (!repliesContainer) return;

    // Bind character counter untuk reply forms
    const replyTextareas = repliesContainer.querySelectorAll('textarea[name="reply_text"]');
    replyTextareas.forEach(textarea => {
        const charCount = textarea.parentElement.querySelector('.reply-char-count');
        if (charCount) {
            textarea.addEventListener('input', function(e) {
                const length = e.target.value.length;
                charCount.textContent = length;
                charCount.className = length > 500 ? 'reply-char-count error' : 'reply-char-count';
            });
        }
    });
};

// Tambahkan di bindEvents atau buat fungsi terpisah
CommentSystem.bindReplyFormEvents = function() {
    // Event delegation untuk reply form submission
    document.addEventListener('submit', function(e) {
        if (e.target.closest('.reply-form')) {
            e.preventDefault();
            const form = e.target.closest('.reply-form');
            const commentId = form.getAttribute('data-comment-id');
            CommentSystem.submitReply(e, commentId);
        }
    });

    // Event delegation untuk cancel buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-outline') && e.target.closest('.reply-form')) {
            e.preventDefault();
            const button = e.target.closest('.btn-outline');
            const commentId = button.getAttribute('data-comment-id');
            CommentSystem.hideReplyForm(commentId);
        }
    });

    // Event delegation untuk character counter di reply forms
    document.addEventListener('input', function(e) {
        if (e.target.closest('.reply-form textarea')) {
            const textarea = e.target;
            const charCount = textarea.parentElement.querySelector('.reply-char-count');
            if (charCount) {
                const length = textarea.value.length;
                charCount.textContent = length;
                charCount.className = length > 500 ? 'reply-char-count error' : 'reply-char-count';
            }
        }
    });
};

CommentSystem.deleteReply = async function(replyId) {
    if (!confirm('Apakah Anda yakin ingin menghapus balasan ini?')) {
        return;
    }

    try {
        const response = await fetch('delete_reply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reply_id: replyId })
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('🗑️ Balasan berhasil dihapus!', 'success');
            // Remove from UI
            const replyCard = document.querySelector(`[data-reply-id="${replyId}"]`);
            if (replyCard) {
                replyCard.remove();
            }
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Terjadi kesalahan', 'error');
    }
};

// Update generateCommentHTML untuk include reply form
CommentSystem.generateCommentHTML = function(comment) {
    const isOwner = document.body.getAttribute('data-username') === comment.comment_sender_name;
    const userRole = document.body.getAttribute('data-user-role');
    const comicWriter = document.querySelector('.writer-name')?.textContent;
    const isComicOwner = comment.comment_sender_name === comicWriter;
    
    const commentTime = this.formatCommentTime(comment.created_at);
    
    return `
        <div class="comment-header">
            <div class="comment-author">
                <div class="author-avatar">
                    ${comment.comment_sender_name.charAt(0).toUpperCase()}
                </div>
                <div class="author-info">
                    <span class="author-name ${isComicOwner ? 'owner' : ''}">
                        ${this.escapeHtml(comment.comment_sender_name)}
                        ${isComicOwner ? ' ✍️' : ''}
                    </span>
                    <span class="comment-meta">
                        <span class="comment-time">${commentTime}</span>
                    </span>
                </div>
            </div>
            <div class="comment-actions">
                ${isOwner ? `
                    <button class="btn-icon" onclick="CommentSystem.deleteComment(${comment.comment_id})" title="Hapus">🗑️</button>
                ` : ''}
            </div>
        </div>
        <div class="comment-content">
            <p class="comment-text">${this.escapeHtml(comment.comment_sender_text)}</p>
        </div>
        <div class="comment-footer">
            <button class="btn-like" onclick="CommentSystem.likeComment(${comment.comment_id})">
                <span class="like-icon">👍</span>
                <span class="like-count">${comment.comment_likes || 0}</span>
            </button>
            <button class="btn-reply" data-comment-id="${comment.comment_id}">
                <span class="reply-icon">↩️</span>
                Balas
            </button>
            <span class="comment-views">👁️ ${comment.comment_views || 0}</span>
        </div>
        
        <!-- Reply Form -->
        <div class="reply-form-container" id="replyForm-${comment.comment_id}" style="display: none;">
            <form class="reply-form" data-comment-id="${comment.comment_id}">
                <div class="form-group">
                    <textarea name="reply_text" placeholder="Tulis balasan..." rows="2" maxlength="500" required></textarea>
                    <div class="char-counter-small">
                        <span class="reply-char-count">0</span>/500
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline btn-small" data-comment-id="${comment.comment_id}">Batal</button>
                    <button type="submit" class="btn btn-primary btn-small">Kirim Balasan</button>
                </div>
            </form>
        </div>
        
        <!-- Replies List -->
        <div class="replies-list" id="replies-${comment.comment_id}"></div>
    `;
};

// Load all replies when page loads
CommentSystem.loadAllReplies = function() {
    const commentCards = document.querySelectorAll('.comment-card');
    commentCards.forEach(card => {
        const commentId = card.getAttribute('data-comment-id');
        this.loadReplies(commentId);
    });
};

// Update initialization untuk load replies
CommentSystem.startAutoRefresh = function() {
    // ... kode existing ...
    
    // Load replies setelah 2 detik
    setTimeout(() => {
        this.loadAllReplies();
    }, 2000);
};

// ===== SUBMIT COMMENT =====
CommentSystem.submitComment = async function() {
    if (this.isSubmitting) return;

    const form = document.getElementById('commentForm');
    const formData = new FormData(form);
    const submitBtn = document.getElementById('submitCommentBtn');
    const loadingIcon = document.getElementById('commentLoading');
    const btnText = document.getElementById('commentBtnText');

    // Validation
    const commentText = formData.get('comment_sender_text').trim();
    if (!commentText) {
        showNotification('Komentar tidak boleh kosong!', 'error');
        return;
    }

    if (commentText.length > 1000) {
        showNotification('Komentar terlalu panjang! Maksimal 1000 karakter.', 'error');
        return;
    }

    // Show loading state
    this.setLoadingState(submitBtn, loadingIcon, btnText, true);
    this.isSubmitting = true;

    try {
        const response = await fetch('add_comment_instant.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
    
        if (result.success) {
            this.lastCommentCheck = Date.now();
            this.handleCommentSuccess(result);
            
            const comicId = document.body.getAttribute('data-comic-id');
            localStorage.removeItem(`comment_draft_${comicId}`);
        } else {
            this.handleCommentError(result.message || 'Terjadi kesalahan tidak diketahui');
        }
    } catch (error) {
        console.error('Comment submission error:', error);
        this.handleCommentError('Terjadi kesalahan saat mengirim komentar: ' + error.message);
    } finally {
        this.setLoadingState(submitBtn, loadingIcon, btnText, false);
        this.isSubmitting = false;
    }
};

CommentSystem.setLoadingState = function(submitBtn, loadingIcon, btnText, isLoading) {
    if (isLoading) {
        submitBtn.disabled = true;
        loadingIcon.style.display = 'inline';
        btnText.textContent = 'Mengirim...';
        submitBtn.style.opacity = '0.7';
    } else {
        submitBtn.disabled = false;
        loadingIcon.style.display = 'none';
        btnText.textContent = 'Kirim Komentar';
        submitBtn.style.opacity = '1';
    }
};

CommentSystem.handleCommentSuccess = function(result) {
    showNotification('✅ Komentar berhasil dikirim!', 'success');
    this.resetCommentForm();

    if (result.comment_data) {
        this.addNewCommentToUI(result.comment_data, true);
    } else {
        setTimeout(() => window.location.reload(), 1500);
    }
};

CommentSystem.handleCommentError = function(message) {
    showNotification('❌ ' + message, 'error');
};

CommentSystem.resetCommentForm = function() {
    const form = document.getElementById('commentForm');
    if (form) {
        form.reset();
        const charCount = document.getElementById('charCount');
        if (charCount) {
            charCount.textContent = '0';
            charCount.className = 'char-counter';
        }
    }
};

// ===== ADD COMMENT TO UI =====
CommentSystem.addNewCommentToUI = function(commentData, isOwnComment = false) {
    const commentsList = document.getElementById('commentsList');
    const emptyState = document.querySelector('.empty-comments-state');
    
    // Remove empty state if it exists
    if (emptyState) {
        emptyState.remove();
    }

    // Create new comment card
    const commentCard = document.createElement('div');
    commentCard.className = `comment-card new-comment ${isOwnComment ? 'own-comment' : ''}`;
    commentCard.setAttribute('data-comment-id', commentData.comment_id);
    commentCard.innerHTML = this.generateCommentHTML(commentData);

    // Add to top of comments list
    if (commentsList) {
        if (commentsList.children.length > 0) {
            commentsList.insertBefore(commentCard, commentsList.firstChild);
        } else {
            commentsList.appendChild(commentCard);
        }
    } else {
        // Create comments list if it doesn't exist
        const commentsContainer = document.querySelector('.comments-list-container');
        const newCommentsList = document.createElement('div');
        newCommentsList.className = 'comments-list';
        newCommentsList.id = 'commentsList';
        newCommentsList.appendChild(commentCard);
        commentsContainer.appendChild(newCommentsList);
    }

    // Update comments count
    this.updateCommentsCount(1);

    // Add animation and scroll to new comment (only for own comments)
    if (isOwnComment) {
        setTimeout(() => {
            commentCard.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center'
            });
        }, 100);
    }
};

CommentSystem.generateCommentHTML = function(comment) {
    const isOwner = document.body.getAttribute('data-username') === comment.comment_sender_name;
    const userRole = document.body.getAttribute('data-user-role');
    const comicWriter = document.querySelector('.writer-name')?.textContent;
    const isComicOwner = comment.comment_sender_name === comicWriter;
    
    // Format waktu yang lebih user-friendly
    const commentTime = this.formatCommentTime(comment.created_at);
    
    return `
        <div class="comment-header">
            <div class="comment-author">
                <div class="author-avatar">
                    ${comment.comment_sender_name.charAt(0).toUpperCase()}
                </div>
                <div class="author-info">
                    <span class="author-name ${isComicOwner ? 'owner' : ''}">
                        ${this.escapeHtml(comment.comment_sender_name)}
                        ${isComicOwner ? ' ✍️' : ''}
                    </span>
                    <span class="comment-meta">
                        <span class="comment-time">${commentTime}</span>
                    </span>
                </div>
            </div>
            <div class="comment-actions">
                ${isOwner ? `
                    <button class="btn-icon" onclick="CommentSystem.deleteComment(${comment.comment_id})" title="Hapus">🗑️</button>
                ` : ''}
            </div>
        </div>
        <div class="comment-content">
            <p class="comment-text">${this.escapeHtml(comment.comment_sender_text)}</p>
        </div>
        <div class="comment-footer">
            <button class="btn-like" onclick="CommentSystem.likeComment(${comment.comment_id})">
                <span class="like-icon">👍</span>
                <span class="like-count">${comment.comment_likes || 0}</span>
            </button>
            <span class="comment-views">👁️ ${comment.comment_views || 0}</span>
        </div>
    `;
};

CommentSystem.formatCommentTime = function(createdAt) {
    const now = new Date();
    const commentTime = new Date(createdAt);
    const diffMs = now - commentTime;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins} menit lalu`;
    if (diffHours < 24) return `${diffHours} jam lalu`;
    if (diffDays < 7) return `${diffDays} hari lalu`;
    
    return commentTime.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

CommentSystem.updateCommentsCount = function(increment) {
    const sectionTitle = document.querySelector('.comments-section .section-title');
    if (sectionTitle) {
        const currentText = sectionTitle.textContent;
        const regex = /\((\d+)\)/;
        const match = currentText.match(regex);
        
        if (match) {
            const currentCount = parseInt(match[1]);
            const newCount = Math.max(0, currentCount + increment);
            sectionTitle.textContent = currentText.replace(regex, `(${newCount})`);
            
            const quickStatsComment = document.querySelector('.quick-stats .stat-item:nth-child(2) .stat-text');
            if (quickStatsComment) {
                quickStatsComment.textContent = `${newCount} Komentar`;
            }
        }
    }
};

// ===== LIKE SYSTEM =====
CommentSystem.likeComment = async function(commentId) {
    const likeBtn = document.querySelector(`[data-comment-id="${commentId}"] .btn-like`);
    const likeCount = likeBtn.querySelector('.like-count');
    
    if (likeBtn.classList.contains('liked')) {
        showNotification('Anda sudah menyukai komentar ini', 'info');
        return;
    }

    // Optimistic UI update
    const currentLikes = parseInt(likeCount.textContent);
    likeCount.textContent = currentLikes + 1;
    likeBtn.classList.add('liked');

    try {
        const response = await fetch('like_comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });

        const data = await response.json();
        
        if (!data.success) {
            // Revert optimistic update if failed
            likeCount.textContent = currentLikes;
            likeBtn.classList.remove('liked');
            showNotification(data.message, 'error');
        } else {
            showNotification('❤️ Terima kasih atas suka Anda!', 'success');
        }
    } catch (error) {
        // Revert optimistic update on error
        likeCount.textContent = currentLikes;
        likeBtn.classList.remove('liked');
        showNotification('Terjadi kesalahan', 'error');
    }
};

// ===== DELETE COMMENT =====
CommentSystem.deleteComment = async function(commentId) {
    if (!confirm('Apakah Anda yakin ingin menghapus komentar ini?')) {
        return;
    }

    try {
        const response = await fetch('delete_comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('🗑️ Komentar berhasil dihapus!', 'success');
            this.removeCommentFromUI(commentId);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Terjadi kesalahan', 'error');
    }
};

CommentSystem.removeCommentFromUI = function(commentId) {
    const commentCard = document.querySelector(`[data-comment-id="${commentId}"]`);
    if (commentCard) {
        commentCard.style.opacity = '0';
        commentCard.style.transform = 'translateX(-100%)';
        setTimeout(() => {
            commentCard.remove();
            this.updateCommentsCount(-1);
        }, 300);
    }
};

// ===== UTILITY FUNCTIONS =====
CommentSystem.escapeHtml = function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

// ===== CLEANUP =====
CommentSystem.destroy = function() {
    if (this.autoRefreshInterval) {
        clearInterval(this.autoRefreshInterval);
        this.autoRefreshInterval = null;
    }
};

// ===== GLOBAL FUNCTIONS FOR HTML ONCLICK =====
function manualRefreshComments() {
    CommentSystem.manualRefreshComments();
}

function toggleAutoRefresh() {
    CommentSystem.toggleAutoRefresh();
}

function sortComments(sortBy) {
    const url = new URL(window.location.href);
    url.searchParams.set('comment_sort', sortBy);
    url.hash = 'comments';
    window.location.href = url.toString();
}

function resetCommentForm() {
    CommentSystem.resetCommentForm();
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 ShrineComics - Detail Komik loaded!');
    
    // Initialize comment system
    initCommentSystem();
    
    // Update UI controls
    setTimeout(() => {
        CommentSystem.updateAutoRefreshUI();
    }, 1000);
    
    // ... rest of your existing initialization code ...
});

console.log('✅ script_comic_detail.js loaded successfully!');
// ===== GLOBAL FUNCTIONS =====

// Function untuk dipanggil dari HTML
function manualRefreshComments() {
    if (window.commentSystem) {
        window.commentSystem.manualRefreshComments();
    } else {
        showNotification('Sistem komentar belum siap', 'error');
    }
}

function toggleAutoRefresh() {
    if (window.commentSystem) {
        window.commentSystem.toggleAutoRefresh();
    } else {
        showNotification('Sistem komentar belum siap', 'error');
    }
}

function sortComments(sortBy) {
    const url = new URL(window.location.href);
    url.searchParams.set('comment_sort', sortBy);
    url.hash = 'comments';
    window.location.href = url.toString();
}

function resetCommentForm() {
    if (window.commentSystem) {
        window.commentSystem.resetCommentForm();
    }
}

// ===== FITUR UMUM =====

// Bookmark functionality
function toggleBookmark(comicId) {
    const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
    const isBookmarked = bookmarks.includes(comicId);
    
    if (!isBookmarked) {
        bookmarks.push(comicId);
        localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
        showNotification('⭐ Komik berhasil ditambahkan ke bookmark!', 'success');
    } else {
        const updatedBookmarks = bookmarks.filter(id => id !== comicId);
        localStorage.setItem('bookmarks', JSON.stringify(updatedBookmarks));
        showNotification('📕 Bookmark berhasil dihapus!', 'success');
    }
    
    updateBookmarkButton(comicId, !isBookmarked);
}

function updateBookmarkButton(comicId, isBookmarked) {
    const bookmarkBtns = document.querySelectorAll(`[onclick="toggleBookmark(${comicId})"]`);
    bookmarkBtns.forEach(btn => {
        if (isBookmarked) {
            btn.innerHTML = '⭐ Bookmarked';
            btn.style.background = '#28a745';
        } else {
            btn.innerHTML = '⭐ Bookmark';
            btn.style.background = '#6c757d';
        }
    });
}

// Share functionality
function shareComic() {
    const comicTitle = document.querySelector('.comic-title').textContent;
    const shareText = `Baca "${comicTitle}" di ShrineComics!`;
    
    if (navigator.share) {
        navigator.share({
            title: comicTitle,
            text: shareText,
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(shareText).then(() => {
            showNotification('📤 Link komik berhasil disalin!', 'success');
        });
    }
}

// ===== NOTIFICATION SYSTEM =====

function showNotification(message, type = 'success') {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${getNotificationIcon(type)}</span>
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '15px 20px',
        background: getNotificationColor(type),
        color: 'white',
        borderRadius: '8px',
        boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
        zIndex: '1000',
        transform: 'translateX(100%)',
        transition: 'transform 0.3s ease',
        maxWidth: '400px',
        minWidth: '300px'
    });
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 4000);
    
    notification.addEventListener('mouseenter', () => {
        notification.style.transition = 'none';
    });
    
    notification.addEventListener('mouseleave', () => {
        notification.style.transition = 'transform 0.3s ease';
    });
}

function getNotificationIcon(type) {
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };
    return icons[type] || '💡';
}

function getNotificationColor(type) {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        info: '#17a2b8',
        warning: '#ffc107'
    };
    return colors[type] || '#007bff';
}

// ===== INITIALIZATION =====

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 ShrineComics - Detail Komik loaded!');
    
    // Initialize comment system
    try {
        //window.commentSystem = new CommentSystem();
        console.log('✅ CommentSystem initialized successfully');
        
        // Update UI controls
        setTimeout(() => {
            if (window.commentSystem) {
                window.commentSystem.updateAutoRefreshUI();
            }
        }, 1000);
    } catch (error) {
        console.error('❌ Error initializing CommentSystem:', error);
    }
    
    // Check bookmarks
    const comicId = new URLSearchParams(window.location.search).get('id');
    const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
    const isBookmarked = bookmarks.includes(parseInt(comicId));
    if (isBookmarked) {
        updateBookmarkButton(comicId, true);
    }
    
    // Close modal ketika klik di luar
    const modal = document.getElementById('purchaseModal');
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closePurchaseModal();
            }
        });
    }
    
    // Scroll to comments section if URL hash is #comments
    if (window.location.hash === '#comments') {
        setTimeout(() => {
            const commentsSection = document.getElementById('comments');
            if (commentsSection) {
                commentsSection.scrollIntoView({ behavior: 'smooth' });
            }
        }, 500);
    }
});

// ===== FITUR HAPUS CHAPTER (UNTUK WRITER/OWNER) =====

/**
 * Fungsi untuk menghapus chapter - HANYA untuk writer pemilik komik
 * @param {number} chapterId - ID chapter yang akan dihapus
 */
function deleteChapter(chapterId) {
    console.log('Delete chapter:', chapterId);
    
    // Validasi: hanya writer pemilik komik yang bisa menghapus
    const isComicOwner = document.body.getAttribute('data-is-comic-owner') === 'true';
    const userRole = document.body.getAttribute('data-user-role');
    
    if (!isComicOwner || userRole !== 'writer') {
        showNotification('❌ Anda tidak memiliki izin untuk menghapus chapter ini!', 'error');
        return;
    }
    
    // Konfirmasi penghapusan
    if (!confirm('⚠️ APAKAH ANDA YAKIN INGIN MENGHAPUS CHAPTER INI?\n\nTindakan ini tidak dapat dibatalkan!')) {
        return;
    }
    
    // Tampilkan loading state
    const deleteBtn = document.querySelector(`[data-chapter-id="${chapterId}"] .btn-icon[onclick*="deleteChapter"]`);
    if (deleteBtn) {
        const originalHTML = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '🔄';
        deleteBtn.disabled = true;
        
        showNotification('Menghapus chapter...', 'info');
        
        // Kirim request penghapusan
        const formData = new FormData();
        formData.append('chapter_id', chapterId);
        
        fetch('delete_chapter.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('✅ Chapter berhasil dihapus!', 'success');
                
                // Hapus chapter dari UI
                removeChapterFromUI(chapterId);
                
            } else {
                throw new Error(data.message || 'Gagal menghapus chapter');
            }
        })
        .catch(error => {
            console.error('Error deleting chapter:', error);
            showNotification('❌ Gagal menghapus chapter: ' + error.message, 'error');
            
            // Reset button state
            if (deleteBtn) {
                deleteBtn.innerHTML = originalHTML;
                deleteBtn.disabled = false;
            }
        });
    }
}

// ===== FITUR HAPUS CHAPTER (UNTUK WRITER/OWNER) =====

/**
 * Fungsi untuk menghapus chapter - HANYA untuk writer pemilik komik
 * @param {number} chapterId - ID chapter yang akan dihapus
 */

// ===== FITUR HAPUS CHAPTER (UNTUK WRITER/OWNER) =====

/**
 * Fungsi untuk menghapus chapter - HANYA untuk writer pemilik komik
 * @param {number} chapterId - ID chapter yang akan dihapus
 */

/**
 * Memperbarui statistik chapter setelah penghapusan
 */

// ===== FITUR HAPUS CHAPTER (UNTUK WRITER/OWNER) =====

/**
 * Fungsi untuk menghapus chapter - HANYA untuk writer pemilik komik
 * @param {number} chapterId - ID chapter yang akan dihapus
 */
// ===== FITUR HAPUS CHAPTER (UNTUK WRITER/OWNER) =====

/**
 * Fungsi untuk menghapus chapter - HANYA untuk writer pemilik komik
 * @param {number} chapterId - ID chapter yang akan dihapus
 */
// ===== FITUR HAPUS CHAPTER =====

/**
 * Fungsi untuk menghapus chapter
 * @param {number} chapterId - ID chapter yang akan dihapus
 */
function deleteChapter(chapterId) {
    console.log('Delete chapter:', chapterId);
    
    // Konfirmasi penghapusan
    if (!confirm('⚠️ APAKAH ANDA YAKIN INGIN MENGHAPUS CHAPTER INI?\n\nTindakan ini tidak dapat dibatalkan!')) {
        return;
    }
    
    // Tampilkan loading state
    const deleteBtn = document.querySelector(`[data-chapter-id="${chapterId}"] .btn-icon[onclick*="deleteChapter"]`);
    if (deleteBtn) {
        const originalHTML = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '🔄';
        deleteBtn.disabled = true;
        
        showNotification('Menghapus chapter...', 'info');
        
        // Kirim request penghapusan
        const formData = new FormData();
        formData.append('chapter_id', chapterId);
        
        fetch('delete_chapter.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                showNotification('✅ Chapter berhasil dihapus!', 'success');
                
                // Hapus chapter dari UI
                removeChapterFromUI(chapterId);
                
            } else {
                throw new Error(data.message || 'Gagal menghapus chapter');
            }
        })
        .catch(error => {
            console.error('Error deleting chapter:', error);
            showNotification('❌ Gagal menghapus chapter: ' + error.message, 'error');
            
            // Reset button state
            if (deleteBtn) {
                deleteBtn.innerHTML = originalHTML;
                deleteBtn.disabled = false;
            }
        });
    }
}

/**
 * Menghapus chapter dari tampilan UI
 */
function removeChapterFromUI(chapterId) {
    const chapterCard = document.querySelector(`[data-chapter-id="${chapterId}"]`);
    if (chapterCard) {
        // Animasi fade out
        chapterCard.style.opacity = '0';
        chapterCard.style.transform = 'translateX(-100%)';
        chapterCard.style.transition = 'all 0.3s ease';
        
        // Hapus dari DOM setelah animasi selesai
        setTimeout(() => {
            chapterCard.remove();
            updateChapterStatsAfterDeletion();
        }, 300);
    }
}

/**
 * Memperbarui statistik chapter setelah penghapusan
 */
function updateChapterStatsAfterDeletion() {
    const remainingChapters = document.querySelectorAll('.chapter-card').length;
    
    // Update semua tampilan jumlah chapter
    const elementsToUpdate = [
        '.chapters-section .section-title',
        '.quick-stats .stat-item:nth-child(1) .stat-text',
        '.chapter-stats .stat-badge:first-child'
    ];
    
    elementsToUpdate.forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            if (selector.includes('section-title')) {
                element.textContent = `📚 Daftar Chapter (${remainingChapters})`;
            } else if (selector.includes('stat-text')) {
                element.textContent = `${remainingChapters} Chapter`;
            } else if (selector.includes('stat-badge')) {
                element.textContent = `Total: ${remainingChapters} chapter`;
            }
        }
    });
    
    // Tampilkan empty state jika tidak ada chapter
    if (remainingChapters === 0) {
        showEmptyChaptersState();
    }
}

function showEmptyChaptersState() {
    const chaptersSection = document.querySelector('.chapters-section');
    const existingEmptyState = chaptersSection.querySelector('.empty-state');
    const comicId = document.body.getAttribute('data-comic-id');
    
    if (!existingEmptyState) {
        const emptyStateHTML = `
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>Belum Ada Chapter</h3>
                <p>Chapter pertama sedang dalam persiapan.</p>
                <div class="empty-actions">
                    <a href="add_chapter.php?comic_id=${comicId}" class="btn btn-primary">
                        <span class="btn-icon">➕</span>
                        Tambah Chapter Pertama
                    </a>
                </div>
            </div>
        `;
        
        const chaptersGrid = chaptersSection.querySelector('.chapters-grid');
        if (chaptersGrid) {
            chaptersGrid.innerHTML = emptyStateHTML;
        }
    }
}
console.log('✅ script_comic_detail.js loaded successfully!');