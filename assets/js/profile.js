/**
 * Profile Page JavaScript
 * Handles profile photo upload modal and form interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const changePhotoBtn = document.getElementById('change-photo-btn');
    const photoModal = document.getElementById('photo-modal');
    const closeModal = document.getElementById('close-modal');
    const cancelUpload = document.getElementById('cancel-upload');
    const modalDropZone = document.getElementById('modal-drop-zone');
    const modalFileInput = document.getElementById('modal-file-input');
    const confirmUpload = document.getElementById('confirm-upload');
    const mainFormFileInput = document.getElementById('photo');
    const currentPhotoPreview = document.getElementById('current-photo-preview');
    const modalPreview = document.getElementById('modal-preview');
    const modalPreviewImage = document.getElementById('modal-preview-image');
    const modalFileName = document.getElementById('modal-file-name');
    const modalFileSize = document.getElementById('modal-file-size');
    
    let selectedFile = null;
    
    // Open modal when "Change Profile Picture" is clicked
    if (changePhotoBtn) {
        changePhotoBtn.addEventListener('click', function() {
            photoModal.style.display = 'flex';
            // Reset modal state
            resetModal();
        });
    }
    
    // Close modal functions
    function closeModalFunc() {
        photoModal.style.display = 'none';
        resetModal();
    }
    
    if (closeModal) closeModal.addEventListener('click', closeModalFunc);
    if (cancelUpload) cancelUpload.addEventListener('click', closeModalFunc);
    
    // Click on modal drop zone to browse files
    if (modalDropZone) {
        modalDropZone.addEventListener('click', function(e) {
            if (e.target !== modalFileInput && modalFileInput) {
                modalFileInput.click();
            }
        });
    }
    
    // Handle file selection from browse
    if (modalFileInput) {
        modalFileInput.addEventListener('change', function(e) {
            if (e.target.files[0]) {
                handleFileSelection(e.target.files[0]);
            }
        });
    }
    
    // Drag & Drop functionality for modal
    if (modalDropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            modalDropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            modalDropZone.addEventListener(eventName, function() {
                modalDropZone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            modalDropZone.addEventListener(eventName, function() {
                modalDropZone.classList.remove('drag-over');
            }, false);
        });
        
        // Handle drop
        modalDropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length) {
                handleFileSelection(files[0]);
            }
        });
    }
    
    // Confirm upload button
    if (confirmUpload) {
        confirmUpload.addEventListener('click', function() {
            if (selectedFile) {
                // Set the file to the main form's hidden input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(selectedFile);
                if (mainFormFileInput) {
                    mainFormFileInput.files = dataTransfer.files;
                }
                
                // Update the preview on the main page
                updateMainPreview(selectedFile);
                
                // Close modal
                closeModalFunc();
            }
        });
    }
    
    // File handling functions
    function handleFileSelection(file) {
        // Validate file
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file (JPG, PNG, or GIF)');
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            return;
        }
        
        selectedFile = file;
        
        // Show preview in modal
        showModalPreview(file);
        
        // Enable confirm button
        if (confirmUpload) confirmUpload.disabled = false;
    }
    
    function showModalPreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (modalPreviewImage) modalPreviewImage.src = e.target.result;
            if (modalFileName) modalFileName.textContent = file.name;
            if (modalFileSize) modalFileSize.textContent = formatFileSize(file.size);
            
            // Show preview area, hide drag drop content
            if (modalDropZone) {
                const dragDropContent = modalDropZone.querySelector('.drag-drop-content');
                if (dragDropContent) dragDropContent.style.display = 'none';
            }
            if (modalPreview) modalPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
    
    function updateMainPreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (currentPhotoPreview) currentPhotoPreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
    function resetModal() {
        selectedFile = null;
        if (modalDropZone) {
            const dragDropContent = modalDropZone.querySelector('.drag-drop-content');
            if (dragDropContent) dragDropContent.style.display = 'block';
            modalDropZone.classList.remove('drag-over');
        }
        if (modalPreview) modalPreview.style.display = 'none';
        if (confirmUpload) confirmUpload.disabled = true;
    }
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Close modal when clicking outside
    if (photoModal) {
        photoModal.addEventListener('click', function(e) {
            if (e.target === photoModal) {
                closeModalFunc();
            }
        });
    }
    
    // Form submission loading state
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const submitBtn = profileForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                
                // Re-enable after 3 seconds or on page reload
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    }
});