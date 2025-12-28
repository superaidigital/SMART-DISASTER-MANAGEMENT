<?php
/**
 * distribution_manager.php
 * ระบบบันทึกการแจกจ่ายพัสดุรายบุคคลและหน่วยงาน (รองรับหลายรายการ)
 * แก้ไข: ปรับปรุงระบบ JavaScript Submit ให้เสถียรขึ้น และรองรับการแจ้งเตือนจาก Session
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'guest';
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// 2. จัดการเรื่อง Shelter ID (สิทธิ์ Admin เลือกศูนย์ได้ / Staff ล็อกตามศูนย์ตัวเอง)
$shelter_id = ($role === 'admin') ? (isset($_GET['shelter_id']) ? (int)$_GET['shelter_id'] : 0) : $my_shelter_id;

// 3. จัดการกรณีสแกน QR Code (ได้รับ evacuee_id มาทาง URL)
$prefilled_evacuee_id = isset($_GET['evacuee_id']) ? (int)$_GET['evacuee_id'] : 0;
$prefilled_name = "";

if ($prefilled_evacuee_id > 0) {
    $stmt_pre = $conn->prepare("SELECT first_name, last_name FROM evacuees WHERE id = ?");
    $stmt_pre->bind_param("i", $prefilled_evacuee_id);
    $stmt_pre->execute();
    $res_pre = $stmt_pre->get_result();
    if ($row_pre = $res_pre->fetch_assoc()) {
        $prefilled_name = $row_pre['first_name'] . ' ' . $row_pre['last_name'];
    }
}

// 4. ดึงข้อมูลพื้นฐาน (ศูนย์พักพิง และ รายการสินค้า)
$shelters = [];
if ($role === 'admin') {
    $shelters = $conn->query("SELECT id, name FROM shelters WHERE status != 'closed' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
}

$items = [];
if ($shelter_id > 0) {
    $stmt_it = $conn->prepare("SELECT * FROM inventory WHERE shelter_id = ? AND quantity > 0 ORDER BY item_name ASC");
    $stmt_it->bind_param("i", $shelter_id);
    $stmt_it->execute();
    $items = $stmt_it->get_result()->fetch_all(MYSQLI_ASSOC);
}

include 'includes/header.php';
?>

<style>
    .card-header-dist { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border-bottom: 3px solid #fbbf24; }
    .form-section-title { border-left: 5px solid #fbbf24; padding-left: 12px; font-weight: bold; margin-bottom: 20px; color: #1e293b; }
    .item-row { transition: all 0.2s ease; border-bottom: 1px solid #f1f5f9; padding: 12px 0; }
    .item-row:hover { background-color: #f8fafc; }
    .btn-remove-row { color: #ef4444; cursor: pointer; transition: 0.2s; padding: 8px; }
    .btn-remove-row:hover { color: #b91c1c; transform: scale(1.1); }
    .stock-info-small { font-size: 0.75rem; color: #64748b; margin-top: 4px; font-weight: 500; }
    .select2-container--bootstrap-5 .select2-selection { border-radius: 10px; padding: 0.4rem; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0 text-dark"><i class="fas fa-boxes-stacked text-primary me-2"></i>บันทึกการแจกจ่ายพัสดุ</h3>
            <p class="text-muted small mb-0">บันทึกประวัติการรับสิ่งของและตัดสต็อกออกจากคลังสินค้า</p>
        </div>
        <div class="btn-group shadow-sm">
            <a href="distribution_history.php" class="btn btn-white border btn-sm px-3">
                <i class="fas fa-history me-1"></i> ดูประวัติล่าสุด
            </a>
            <a href="qr_scanner.php" class="btn btn-warning btn-sm px-3 fw-bold">
                <i class="fas fa-qrcode me-1"></i> สแกนบัตร (QR)
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-12">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
                <div class="card-header card-header-dist py-3 px-4">
                    <h5 class="mb-0 fw-bold">แบบฟอร์มบันทึกรายการเบิกจ่าย</h5>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <!-- ฟอร์มส่งข้อมูลไปยัง distribution_save.php -->
                    <form action="distribution_save.php" method="POST" id="distForm">
                        
                        <?php if ($role === 'admin'): ?>
                        <div class="mb-5 bg-light p-4 rounded-4 border border-dashed border-primary">
                            <label class="form-label fw-bold text-primary small text-uppercase"><i class="fas fa-map-marker-alt me-1"></i> ศูนย์พักพิงต้นทาง <span class="text-danger">*</span></label>
                            <select name="shelter_id" id="shelter_select" class="form-select border-0 shadow-sm" onchange="if(this.value) window.location.href='?shelter_id='+this.value">
                                <option value="">-- โปรดเลือกศูนย์เพื่อแสดงรายการสินค้า --</option>
                                <?php foreach ($shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="shelter_id" id="shelter_select" value="<?php echo $my_shelter_id; ?>">
                        <?php endif; ?>

                        <?php if ($shelter_id > 0): ?>
                        
                        <div class="row g-5">
                            <div class="col-lg-4">
                                <div class="form-section-title">1. รายละเอียดผู้รับ</div>
                                
                                <div class="mb-4">
                                    <div class="btn-group w-100 shadow-sm" role="group">
                                        <input type="radio" class="btn-check" name="recipient_type" id="type_evacuee" value="evacuee" checked onchange="toggleRecipient('evacuee')">
                                        <label class="btn btn-outline-primary py-2" for="type_evacuee">ผู้ประสบภัย</label>

                                        <input type="radio" class="btn-check" name="recipient_type" id="type_general" value="general" onchange="toggleRecipient('general')">
                                        <label class="btn btn-outline-secondary py-2" for="type_general">หน่วยงาน/กลุ่ม</label>
                                    </div>
                                </div>

                                <div id="evacuee_section" class="p-3 bg-light rounded-4 border border-2 border-white shadow-sm">
                                    <?php if ($prefilled_evacuee_id > 0): ?>
                                        <div class="p-3 rounded-3 bg-white border border-success d-flex align-items-center mb-3">
                                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="fw-bold text-success text-truncate"><?php echo htmlspecialchars($prefilled_name); ?></div>
                                                <small class="text-muted">ID: <?php echo $prefilled_evacuee_id; ?></small>
                                                <input type="hidden" name="evacuee_id" value="<?php echo $prefilled_evacuee_id; ?>">
                                            </div>
                                            <a href="distribution_manager.php?shelter_id=<?php echo $shelter_id; ?>" class="ms-auto btn btn-sm btn-light text-danger"><i class="fas fa-times"></i></a>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">ค้นหาผู้ประสบภัย <span class="text-danger">*</span></label>
                                            <select name="evacuee_id" id="evacuee_id_select" class="form-select select2-ajax" style="width: 100%;"></select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <label class="form-label text-muted small">ชื่อผู้มารับแทน (ถ้ามี)</label>
                                        <input type="text" name="proxy_receiver" class="form-control border-0 shadow-sm" placeholder="กรณีคนอื่นมารับแทน">
                                    </div>
                                </div>

                                <div id="general_section" class="p-3 bg-light rounded-4 border border-2 border-white shadow-sm d-none">
                                    <label class="form-label fw-bold small">ระบุหน่วยงาน/ปลายทาง <span class="text-danger">*</span></label>
                                    <input type="text" name="recipient_group" id="recipient_group_input" class="form-control border-0 shadow-sm" placeholder="เช่น จุดโรงครัว, ทีมกู้ภัย">
                                </div>

                                <div class="mt-4">
                                    <label class="form-label fw-bold small text-muted">หมายเหตุเบื้องต้น</label>
                                    <textarea name="note" class="form-control border-0 bg-light shadow-sm" rows="3" placeholder="ระบุเหตุผลหรืออ้างอิงใบเบิก"></textarea>
                                </div>
                            </div>

                            <div class="col-lg-8 border-start ps-lg-5">
                                <div class="form-section-title d-flex justify-content-between align-items-center">
                                    <span>2. รายการพัสดุที่แจกจ่าย</span>
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" id="btnAddRow">
                                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table align-middle border-0">
                                        <thead class="text-muted small text-uppercase bg-light">
                                            <tr>
                                                <th style="width: 60%;" class="ps-3">รายการพัสดุ</th>
                                                <th style="width: 20%;">จำนวน</th>
                                                <th style="width: 15%;">หน่วย</th>
                                                <th style="width: 5%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsContainer">
                                            <tr class="item-row">
                                                <td>
                                                    <select name="item_id[]" class="form-select inventory-select" required>
                                                        <option value="">-- เลือกรายการ --</option>
                                                        <?php foreach ($items as $itm): ?>
                                                            <option value="<?php echo $itm['id']; ?>" data-unit="<?php echo $itm['unit']; ?>" data-qty="<?php echo $itm['quantity']; ?>">
                                                                <?php echo htmlspecialchars($itm['item_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="stock-info-small">ในคลัง: <span class="current-stock">-</span></div>
                                                </td>
                                                <td>
                                                    <input type="number" name="quantity[]" class="form-control dist-qty fw-bold text-primary" min="1" required value="1">
                                                </td>
                                                <td><span class="unit-text text-muted small">-</span></td>
                                                <td class="text-center">
                                                    <i class="fas fa-trash-alt btn-remove-row d-none"></i>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5 opacity-25">

                        <div class="text-end">
                            <a href="inventory_dashboard.php" class="btn btn-light border px-4 py-2 me-2">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow fw-bold" id="btnSubmit">
                                <i class="fas fa-save me-2"></i> ยืนยันและบันทึกข้อมูล
                            </button>
                        </div>

                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // --- ระบบแจ้งเตือนหลังบันทึกเรียบร้อย (Check Session) ---
        <?php if(isset($_SESSION['success'])): ?>
            Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?php echo $_SESSION['success']; ?>', timer: 3000, showConfirmButton: false });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: '<?php echo $_SESSION['error']; ?>' });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // --- ระบบจัดการแถวพัสดุ (Multi-item) ---
        const rowTemplate = $('.item-row').first().clone();
        rowTemplate.find('.btn-remove-row').removeClass('d-none');
        
        function initInventorySelect(element) {
            $(element).select2({ theme: 'bootstrap-5', placeholder: '-- ค้นหาพัสดุ --', width: '100%' });
        }
        initInventorySelect('.inventory-select');

        $('#btnAddRow').on('click', function() {
            const newRow = rowTemplate.clone();
            newRow.find('.current-stock').text('-');
            newRow.find('.unit-text').text('-');
            newRow.find('.dist-qty').val(1);
            $('#itemsContainer').append(newRow);
            initInventorySelect(newRow.find('.inventory-select'));
        });

        $(document).on('click', '.btn-remove-row', function() { $(this).closest('.item-row').remove(); });

        $(document).on('change', '.inventory-select', function() {
            const row = $(this).closest('.item-row');
            const sel = $(this).find(':selected');
            if (sel.val()) {
                row.find('.current-stock').text(sel.data('qty') + ' ' + sel.data('unit'));
                row.find('.unit-text').text(sel.data('unit'));
                row.find('.dist-qty').attr('max', sel.data('qty'));
            }
        });

        // --- ค้นหาผู้ประสบภัยผ่าน AJAX ---
        $('#evacuee_id_select').select2({
            theme: 'bootstrap-5',
            ajax: {
                url: 'search_evacuee.php',
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, shelter_id: $('#shelter_select').val() }),
                processResults: data => ({
                    results: $.map(data, it => ({ id: it.id, text: it.full_name + ' (เลขบัตร: ' + it.id_card.slice(-4) + ')' }))
                }),
                cache: true
            },
            placeholder: 'พิมพ์ชื่อ นามสกุล หรือเลขบัตรเพื่อค้นหา...',
            minimumInputLength: 2
        });

        // --- ระบบยืนยันการบันทึก (Fix: มั่นใจว่า form.submit() ทำงาน) ---
        $('#distForm').on('submit', function(e) {
            e.preventDefault(); // หยุดการส่งปกติก่อน
            const form = this;
            const itemCount = $('.item-row').length;

            // ตรวจสอบความครบถ้วน
            let valid = true;
            $('.inventory-select').each(function() { if ($(this).val() === "") valid = false; });
            if (!valid) {
                Swal.fire('ข้อมูลไม่ครบ', 'กรุณาเลือกรายการพัสดุในทุกแถว', 'warning');
                return false;
            }

            // แสดง Popup ยืนยัน
            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                text: "คุณกำลังจะบันทึกการแจกจ่ายรวม " + itemCount + " รายการ",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ยืนยัน บันทึกข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                    // ใช้ native DOM submit เพื่อข้าม jQuery preventDefault
                    form.submit(); 
                }
            });
        });
    });

    function toggleRecipient(type) {
        if(type === 'evacuee') {
            $('#evacuee_section').removeClass('d-none');
            $('#general_section').addClass('d-none');
        } else {
            $('#evacuee_section').addClass('d-none');
            $('#general_section').removeClass('d-none');
        }
    }
</script>

<?php include 'includes/footer.php'; ?>