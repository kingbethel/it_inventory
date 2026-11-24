<?php
// C:\xampp\htdocs\it_inventory\includes\footer.php
?>
        </main>
        
        <footer class="bg-white border-top py-2 px-4 text-center text-muted small mt-auto">
            &copy; <?= date('Y'); ?> G2 Global Inc. &bull; IT Department Inventory Management System
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Responsive mobile toggle
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    }
</script>
</body>
</html>