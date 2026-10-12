/**
 * Campus Complaint Management and Tracking System
 * Modern Client-Side Interactivity & UX Enhancements
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const dashboardSidebar = document.querySelector('.dashboard-sidebar');
    
    if (sidebarToggleBtn && dashboardSidebar) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dashboardSidebar.classList.toggle('show');
        });

        // Close sidebar when tapping outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && 
                dashboardSidebar.classList.contains('show') && 
                !dashboardSidebar.contains(e.target) && 
                e.target !== sidebarToggleBtn) {
                dashboardSidebar.classList.remove('show');
            }
        });
    }

    // 2. Image Upload Preview & Validation
    const imageInput = document.querySelector('input[type="file"][name="complaint_image"], input[type="file"][name="resolution_image"], input[type="file"].image-preview-input');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const previewImg = document.getElementById('imagePreviewImg');
    
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // Check size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds the 5MB maximum limit. Please choose a smaller photo.');
                    this.value = '';
                    if (previewContainer) previewContainer.classList.add('d-none');
                    return;
                }
                
                // Check extension
                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file format. Please choose a JPG, PNG, or WEBP image.');
                    this.value = '';
                    if (previewContainer) previewContainer.classList.add('d-none');
                    return;
                }

                if (previewImg && previewContainer) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        previewImg.src = e.target.result;
                        previewContainer.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                }
            } else if (previewContainer) {
                previewContainer.classList.add('d-none');
            }
        });
    }

    // 3. Auto-dismiss alerts after 7 seconds
    const flashAlerts = document.querySelectorAll('.flash-messages-container .alert');
    flashAlerts.forEach(alertEl => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 7000);
    });

    // 4. Initialize Bootstrap tooltips & popovers if any
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 5. Destructive Action Confirmation
    document.querySelectorAll('.btn-confirm-action, .confirm-action-form').forEach(element => {
        element.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed with this action?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
});

/**
 * Mark notification as read via AJAX
 */
async function markNotificationAsRead(id, element) {
    try {
        const response = await fetch(window.APP_BASE_URL + 'api/notifications.php?action=mark_read&id=' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (data.success && element) {
            element.classList.remove('bg-light', 'fw-bold');
            const badge = document.querySelector('.notification-badge-count');
            if (badge) {
                let currentCount = parseInt(badge.textContent) || 0;
                if (currentCount > 1) {
                    badge.textContent = currentCount - 1;
                } else {
                    badge.remove();
                }
            }
        }
    } catch (err) {
        console.error('Error marking notification as read:', err);
    }
}
