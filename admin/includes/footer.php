            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bootstrapAlert = new bootstrap.Alert(alert);
                bootstrapAlert.close();
            });
        }, 5000);

        // Confirm delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('[onclick*="confirm"]');
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item?')) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        });

        // File upload preview
        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
            fileInputs.forEach(function(input) {
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            let preview = document.getElementById(input.id + '_preview');
                            if (!preview) {
                                preview = document.createElement('div');
                                preview.id = input.id + '_preview';
                                preview.innerHTML = '<img src="" style="max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 8px;">';
                                input.parentNode.appendChild(preview);
                            }
                            preview.querySelector('img').src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            });
        });
    </script>
</body>
</html>
