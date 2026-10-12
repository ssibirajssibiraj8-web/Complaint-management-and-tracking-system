<?php
/**
 * Global Footer Component
 * Campus Complaint Management and Tracking System
 */
?>
    <footer class="mt-auto py-4 bg-white border-top text-secondary small">
        <div class="container text-center">
            <div class="row align-items-center">
                <div class="col-md-6 text-md-start mb-2 mb-md-0">
                    <span class="fw-semibold text-dark">Campus Care</span> &copy; <?= date('Y') ?> &mdash; Campus Complaint Management & Tracking System.
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted">Designed for College Web Application Development (WAD) Project</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3 Bundle JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <!-- Chart.js 4.4 CDN for dynamic interactive reports and admin dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Custom Script -->
    <script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
