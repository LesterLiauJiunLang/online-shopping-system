document.addEventListener('DOMContentLoaded', function() {
    // Toggle review form
    document.querySelectorAll('.leave-review-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.nextElementSibling;
            container.style.display = container.style.display === 'none' ? 'block' : 'none';
        });
    });

    // Drag & drop / click-to-upload
    document.querySelectorAll('.drag-drop-area').forEach(area => {
        const input = area.querySelector('input[type=file]');
        const text = area.querySelector('.drag-drop-text');

        // Click to open file dialog
        area.addEventListener('click', e => {
            e.stopPropagation(); // prevent accidental bubbling
            input.click();
        });

        // Drag over
        area.addEventListener('dragover', e => {
            e.preventDefault();
            area.classList.add('drag-over');
        });

        // Drag leave
        area.addEventListener('dragleave', e => {
            area.classList.remove('drag-over');
        });

        // Drop files
        area.addEventListener('drop', e => {
            e.preventDefault();
            area.classList.remove('drag-over');
            input.files = e.dataTransfer.files;
            text.textContent = `${input.files.length} file(s) ready to upload`;
        });

        // Input change
        input.addEventListener('change', () => {
            text.textContent = `${input.files.length} file(s) ready to upload`;


            document.querySelectorAll('.review-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const productId = form.dataset.productId;
        const formData = new FormData(form);
        formData.append('product_id', productId);

        fetch('submit_review.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                form.reset();
                form.parentElement.style.display = 'none';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Something went wrong!');
        });
    });
});
         });
    });
});
