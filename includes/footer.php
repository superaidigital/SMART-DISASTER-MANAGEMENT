</div> <!-- End .p-3 p-md-4 -->
        
        <!-- Floating Footer Design -->
        <footer class="footer-floating mb-4 mx-3 mx-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body py-3 px-4">
                    <div class="row align-items-center">
                        <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                            <span class="text-muted small fw-medium">
                                <i class="far fa-copyright me-1"></i> <?php echo date('Y'); ?> 
                                <span class="d-none d-sm-inline">ระบบบริหารจัดการศูนย์พักพิงอัจฉริยะ</span>
                                <span class="d-inline d-sm-none">Smart Shelter</span>
                            </span>
                        </div>
                        <div class="col-md-6 text-center text-md-end">
                            <div class="d-flex align-items-center justify-content-center justify-content-md-end gap-3">
                                <span class="badge bg-light text-dark fw-normal border px-3 py-2 rounded-pill">
                                    <i class="fas fa-code-branch me-1 text-primary"></i> v1.2.5
                                </span>
                                <span class="badge bg-success-subtle text-success fw-normal border border-success-subtle px-3 py-2 rounded-pill">
                                    <i class="fas fa-check-circle me-1"></i> ระบบทำงานปกติ
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </footer>

    </div> <!-- End #content -->
</div> <!-- End .wrapper -->

<!-- Overlay for Mobile View -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Custom Footer Style -->
<style>
    .footer-floating {
        margin-top: auto;
    }
    .footer-floating .card {
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        transition: transform 0.3s ease;
    }
    /* ให้ Footer อยู่ล่างสุดเสมอแม้เนื้อหาจะน้อย */
    #content {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
</style>

<!-- Bootstrap 5 JS Bundle (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const btnToggle = document.getElementById('sidebarCollapse');
        const overlay = document.getElementById('sidebarOverlay');
        
        const isMobile = () => window.innerWidth < 992;

        if (btnToggle) {
            btnToggle.addEventListener('click', function() {
                if (isMobile()) {
                    sidebar.classList.toggle('show-mobile');
                    overlay.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('show-mobile') ? 'hidden' : '';
                } else {
                    document.body.classList.toggle('sidebar-collapsed');
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    document.cookie = "sidebar_state=" + (isCollapsed ? "collapsed" : "expanded") + ";path=/;max-age=" + (30*24*60*60);
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show-mobile');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Auto-Expand Active Submenu
        const activeLink = document.querySelector('#sidebar ul.components li a.active');
        if (activeLink) {
            const parentCollapse = activeLink.closest('.collapse');
            if (parentCollapse) {
                const bsCollapse = new bootstrap.Collapse(parentCollapse, { toggle: false });
                bsCollapse.show();
                const parentToggle = document.querySelector(`a[href="#${parentCollapse.id}"]`);
                if (parentToggle) {
                    parentToggle.classList.add('active');
                    parentToggle.setAttribute('aria-expanded', 'true');
                }
            }
        }

        window.addEventListener('resize', function() {
            if (!isMobile()) {
                sidebar.classList.remove('show-mobile');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
</script>

</body>
</html>