// ===== SCRIPT UNTUK EDIT CHAPTER =====

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Edit chapter script loaded');
    
    initializeEditChapterForm();
    setupFormValidation();
    setupAutoSave();
});

// Initialize edit chapter form
function initializeEditChapterForm() {
    const form = document.getElementById('editChapterForm');
    if (!form) return;
    
    console.log('📝 Initializing edit chapter form');
    
    // Load draft if exists
    loadEditDraft();
    
    // Setup real-time validation
    setupRealTimeValidation();
    
    // Setup price formatting
    setupPriceFormatting();
}

// Setup form validation
function setupFormValidation() {
    const form = document.getElementById('editChapterForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm()) {
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="btn-loading">⏳ Menyimpan...</span>';
            submitBtn.disabled = true;
            
            // Clear draft on successful submit
            clearEditDraft();
            
            // Submit form
            setTimeout(() => {
                form.submit();
            }, 500);
        }
    });
}

// Real-time form validation
function setupRealTimeValidation() {
    const inputs = document.querySelectorAll('#editChapterForm .form-input, #editChapterForm .form-select');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            clearFieldError(this);
        });
    });
}

// Validate individual field
function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.getAttribute('name');
    
    switch (fieldName) {
        case 'chapter_number':
            if (!value || value < 1) {
                showFieldError(field, 'Nomor chapter harus lebih dari 0');
                return false;
            }
            break;
            
        case 'chapter_name':
            if (!value) {
                showFieldError(field, 'Nama chapter harus diisi');
                return false;
            }
            if (value.length < 2) {
                showFieldError(field, 'Nama chapter terlalu pendek');
                return false;
            }
            break;
            
        case 'chapter_page':
            if (!value || value < 1 || value > 100) {
                showFieldError(field, 'Jumlah halaman harus antara 1-100');
                return false;
            }
            break;
            
        case 'chapter_price':
            if (value < 0 || value > 1000) {
                showFieldError(field, 'Harga harus antara 0-1000 points');
                return false;
            }
            break;
            
        case 'chapter_release_date':
            if (!value) {
                showFieldError(field, 'Tanggal rilis harus diisi');
                return false;
            }
            break;
    }
    
    return true;
}

// Validate entire form
function validateForm() {
    const form = document.getElementById('editChapterForm');
    const inputs = form.querySelectorAll('.form-input, .form-select');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    if (!isValid) {
        showNotification('Harap perbaiki error pada form sebelum menyimpan', 'error');
        // Scroll to first error
        const firstError = form.querySelector('.field-error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    return isValid;
}

// Show field error
function showFieldError(field, message) {
    clearFieldError(field);
    
    field.style.borderColor = '#dc3545';
    field.style.boxShadow = '0 0 0 3px rgba(220,53,69,0.1)';
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '5px';
    errorDiv.innerHTML = `❌ ${message}`;
    
    field.parentNode.appendChild(errorDiv);
}

// Clear field error
function clearFieldError(field) {
    field.style.borderColor = '';
    field.style.boxShadow = '';
    
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Setup price formatting
function setupPriceFormatting() {
    const priceInput = document.getElementById('chapter_price');
    if (!priceInput) return;
    
    priceInput.addEventListener('input', function(e) {
        let value = parseInt(e.target.value) || 0;
        if (value < 0) value = 0;
        if (value > 1000) value = 1000;
        e.target.value = value;
        
        // Update price indicator
        updatePriceIndicator(value);
    });
    
    // Initial indicator update
    updatePriceIndicator(parseInt(priceInput.value) || 0);
}

// Update price indicator
function updatePriceIndicator(price) {
    let indicator = document.getElementById('price-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'price-indicator';
        indicator.style.fontSize = '0.85rem';
        indicator.style.marginTop = '5px';
        document.getElementById('chapter_price').parentNode.appendChild(indicator);
    }
    
    if (price === 0) {
        indicator.innerHTML = '🆓 Chapter gratis';
        indicator.style.color = '#28a745';
    } else if (price <= 100) {
        indicator.innerHTML = `💰 ${price} points (Murah)`;
        indicator.style.color = '#17a2b8';
    } else if (price <= 500) {
        indicator.innerHTML = `💰 ${price} points (Standar)`;
        indicator.style.color = '#ffc107';
    } else {
        indicator.innerHTML = `💰 ${price} points (Premium)`;
        indicator.style.color = '#dc3545';
    }
}

// Auto-save draft
let draftTimer;
function setupAutoSave() {
    const form = document.getElementById('editChapterForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(draftTimer);
            draftTimer = setTimeout(saveDraft, 1000);
        });
        
        input.addEventListener('change', function() {
            clearTimeout(draftTimer);
            saveDraft();
        });
    });
}

// Save draft to localStorage
function saveDraft() {
    const form = document.getElementById('editChapterForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const draft = {};
    
    for (let [key, value] of formData.entries()) {
        draft[key] = value;
    }
    
    localStorage.setItem('chapter_edit_draft', JSON.stringify(draft));
    console.log('💾 Draft disimpan');
    
    // Show auto-save indicator
    showAutoSaveIndicator();
}

// Load draft from localStorage
function loadEditDraft() {
    const draft = localStorage.getItem('chapter_edit_draft');
    if (!draft) return;
    
    try {
        const draftData = JSON.parse(draft);
        const form = document.getElementById('editChapterForm');
        
        for (let key in draftData) {
            const element = form.querySelector(`[name="${key}"]`);
            if (element) {
                element.value = draftData[key];
                
                // Trigger change event for dependent elements
                if (key === 'chapter_price') {
                    updatePriceIndicator(parseInt(draftData[key]) || 0);
                }
            }
        }
        
        console.log('📂 Draft dimuat');
        showNotification('Draft sebelumnya dimuat otomatis', 'info');
        
    } catch (error) {
        console.error('Error loading draft:', error);
    }
}

// Clear draft
function clearEditDraft() {
    localStorage.removeItem('chapter_edit_draft');
    console.log('🗑️ Draft dibersihkan');
}

// Show auto-save indicator
function showAutoSaveIndicator() {
    let indicator = document.getElementById('auto-save-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'auto-save-indicator';
        indicator.style.position = 'fixed';
        indicator.style.bottom = '20px';
        indicator.style.right = '20px';
        indicator.style.background = '#17a2b8';
        indicator.style.color = 'white';
        indicator.style.padding = '10px 15px';
        indicator.style.borderRadius = '6px';
        indicator.style.fontSize = '0.9rem';
        indicator.style.zIndex = '1000';
        document.body.appendChild(indicator);
    }
    
    indicator.textContent = '💾 Disimpan otomatis';
    indicator.style.opacity = '1';
    
    setTimeout(() => {
        indicator.style.opacity = '0';
        setTimeout(() => {
            indicator.remove();
        }, 300);
    }, 2000);
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${getNotificationIcon(type)}</span>
            <span class="notification-message">${message}</span>
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
        maxWidth: '400px'
    });
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
    
    notification.addEventListener('click', () => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
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

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('editChapterForm').dispatchEvent(new Event('submit'));
    }
    
    // Escape to cancel
    if (e.key === 'Escape') {
        if (confirm('Batalkan editing? Perubahan yang belum disimpan akan hilang.')) {
            window.history.back();
        }
    }
});

console.log('✅ Edit chapter JavaScript loaded successfully');