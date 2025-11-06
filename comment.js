// Enhanced Comment System with Debugging
class CommentSystem {
    constructor() {
        this.debug = true;
        this.init();
    }

    init() {
        this.log('Comment system initializing...');
        this.bindEvents();
        this.setupCharacterCounter();
        this.loadUserData();
        this.log('Comment system ready');
    }

    log(message) {
        if (this.debug) {
            console.log(`💬 CommentSystem: ${message}`);
        }
    }

    bindEvents() {
        const commentForm = document.getElementById('commentForm');
        if (commentForm) {
            commentForm.addEventListener('submit', (e) => this.handleSubmit(e));
            this.log('Form event bound');
        } else {
            this.log('ERROR: Comment form not found');
        }

        // Character counter
        const commentText = document.getElementById('comment_text');
        if (commentText) {
            commentText.addEventListener('input', () => this.updateCharacterCount());
        }

        // Auto-save user data
        const nameInput = document.getElementById('commenter_name');
        const emailInput = document.getElementById('commenter_email');
        
        if (nameInput) nameInput.addEventListener('input', () => this.saveUserData());
        if (emailInput) emailInput.addEventListener('input', () => this.saveUserData());
    }

    setupCharacterCounter() {
        this.updateCharacterCount();
    }

    updateCharacterCount() {
        const textarea = document.getElementById('comment_text');
        const counter = document.getElementById('charCount');
        
        if (textarea && counter) {
            const length = textarea.value.length;
            counter.textContent = length;
            
            // Visual feedback
            if (length > 500) {
                counter.style.color = '#dc3545';
                textarea.style.borderColor = '#dc3545';
            } else if (length > 400) {
                counter.style.color = '#ffc107';
                textarea.style.borderColor = '#ffc107';
            } else {
                counter.style.color = '#28a745';
                textarea.style.borderColor = '#ddd';
            }
        }
    }

    loadUserData() {
        const nameInput = document.getElementById('commenter_name');
        const emailInput = document.getElementById('commenter_email');
        
        const savedName = localStorage.getItem('commenter_name');
        const savedEmail = localStorage.getItem('commenter_email');
        
        if (nameInput && savedName) {
            nameInput.value = savedName;
            this.log('Loaded saved name');
        }
        if (emailInput && savedEmail) {
            emailInput.value = savedEmail;
            this.log('Loaded saved email');
        }
    }

    saveUserData() {
        const nameInput = document.getElementById('commenter_name');
        const emailInput = document.getElementById('commenter_email');
        
        if (nameInput) localStorage.setItem('commenter_name', nameInput.value);
        if (emailInput) localStorage.setItem('commenter_email', emailInput.value);
        
        this.log('User data saved to localStorage');
    }

    async handleSubmit(e) {
        e.preventDefault();
        this.log('Form submission started');
        
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('#submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');

        // Show loading state
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        submitBtn.disabled = true;

        // Validate form
        if (!this.validateForm(formData)) {
            this.resetButton(submitBtn, btnText, btnLoading);
            return;
        }

        try {
            this.log('Sending AJAX request...');
            
            const response = await fetch('add_comment.php', {
                method: 'POST',
                body: formData
            });

            this.log(`Response status: ${response.status}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            this.log('Response received:', result);

            if (result.success) {
                this.showMessage(result.message, 'success');
                this.addCommentToDOM(result.comment_data);
                form.reset();
                this.updateCharacterCount();
                this.hideCommentForm();
                this.updateCommentCounter(1);
                this.log('Comment successfully added');
            } else {
                this.showMessage(result.message, 'error');
                this.log('Server returned error: ' + result.message);
            }

        } catch (error) {
            console.error('Comment submission error:', error);
            this.showMessage('Terjadi kesalahan jaringan. Silakan coba lagi.', 'error');
            this.log('Network error: ' + error.message);
        } finally {
            this.resetButton(submitBtn, btnText, btnLoading);
        }
    }

    validateForm(formData) {
        const name = formData.get('commenter_name').trim();
        const email = formData.get('commenter_email').trim();
        const text = formData.get('comment_text').trim();

        this.log(`Validating: name='${name}', email='${email}', text_length=${text.length}`);

        if (!name) {
            this.showMessage('Nama harus diisi!', 'error');
            return false;
        }

        if (!email) {
            this.showMessage('Email harus diisi!', 'error');
            return false;
        }

        if (!this.isValidEmail(email)) {
            this.showMessage('Format email tidak valid!', 'error');
            return false;
        }

        if (!text) {
            this.showMessage('Komentar harus diisi!', 'error');
            return false;
        }

        if (text.length > 500) {
            this.showMessage('Komentar terlalu panjang (max 500 karakter)!', 'error');
            return false;
        }

        return true;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    resetButton(submitBtn, btnText, btnLoading) {
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
    }

    showMessage(message, type) {
        // Remove existing messages
        const existingMsg = document.querySelector('.comment-message');
        if (existingMsg) {
            existingMsg.remove();
        }

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `comment-message ${type}`;
        messageDiv.innerHTML = `
            <strong>${type === 'success' ? '✅' : '❌'}</strong>
            ${message}
        `;

        // Insert after comment form
        const formContainer = document.getElementById('commentFormContainer');
        if (formContainer) {
            formContainer.parentNode.insertBefore(messageDiv, formContainer);
        } else {
            document.getElementById('commentsSection').prepend(messageDiv);
        }

        // Auto remove
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    addCommentToDOM(commentData) {
        const commentsList = document.getElementById('commentsList');
        const noComments = commentsList.querySelector('.no-comments');

        // Remove no comments message
        if (noComments) {
            noComments.remove();
        }

        // Create new comment HTML
        const commentHTML = `
            <div class="comment-card new-comment" id="comment-${commentData.comment_id}">
                <div class="comment-header">
                    <div class="comment-author">
                        <div class="author-avatar">
                            ${commentData.comment_sender_name.charAt(0).toUpperCase()}
                        </div>
                        <div class="author-info">
                            <strong>${this.escapeHtml(commentData.comment_sender_name)}</strong>
                            <span class="comment-date">${commentData.created_at}</span>
                        </div>
                    </div>
                </div>
                <div class="comment-content">
                    <p>${this.escapeHtml(commentData.comment_sender_text).replace(/\n/g, '<br>')}</p>
                </div>
                <div class="comment-actions">
                    <button class="btn-like" onclick="likeComment(${commentData.comment_id})">
                        👍 <span class="like-count">0</span>
                    </button>
                </div>
            </div>
        `;

        // Add to top
        commentsList.insertAdjacentHTML('afterbegin', commentHTML);

        // Scroll to new comment
        setTimeout(() => {
            const newComment = document.getElementById(`comment-${commentData.comment_id}`);
            if (newComment) {
                newComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    }

    updateCommentCounter(increment = 1) {
        const commentsHeader = document.querySelector('.comments-header h2');
        if (commentsHeader) {
            const currentText = commentsHeader.textContent;
            const regex = /\((\d+)\)/;
            const match = currentText.match(regex);
            
            if (match) {
                const currentCount = parseInt(match[1]);
                const newCount = currentCount + increment;
                commentsHeader.textContent = currentText.replace(regex, `(${newCount})`);
            } else {
                commentsHeader.textContent += ` (${increment})`;
            }
        }
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Global functions
function toggleCommentForm() {
    const formContainer = document.getElementById('commentFormContainer');
    const toggleBtn = document.getElementById('toggleCommentBtn');
    
    if (formContainer.style.display === 'none') {
        formContainer.style.display = 'block';
        toggleBtn.textContent = '✖️ Tutup Form';
        document.getElementById('comment_text').focus();
    } else {
        formContainer.style.display = 'none';
        toggleBtn.textContent = '✍️ Tambah Komentar';
        
        // Clear messages
        const message = document.querySelector('.comment-message');
        if (message) message.remove();
    }
}

function likeComment(commentId) {
    const likeBtn = document.querySelector(`#comment-${commentId} .btn-like`);
    const likeCount = likeBtn.querySelector('.like-count');
    
    let currentLikes = parseInt(likeCount.textContent) || 0;
    currentLikes++;
    likeCount.textContent = currentLikes;
    
    likeBtn.style.background = '#007bff';
    likeBtn.style.color = 'white';
    likeBtn.style.borderColor = '#007bff';
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Initializing Comment System...');
    window.commentSystem = new CommentSystem();
});