    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?= base_url('assets/js/app.js'); ?>"></script>
    
    <?php if (isset($page_js)): ?>
    <script><?php echo $page_js; ?></script>
    <?php endif; ?>
    
    <?php if (isset($page_scripts)): ?>
    <script src="<?php echo $page_scripts; ?>"></script>
    <?php endif; ?>
</body>
</html>