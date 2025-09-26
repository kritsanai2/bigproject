<?php
session_start();
require_once "db.php"; 

// ====================== Sync รายรับจาก order_details ======================
$conn->query("
    INSERT INTO transactions (transaction_type, amount, transaction_date, order_detail_id)
    SELECT 'income', od.quantity * od.price, o.order_date, od.order_detail_id
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    WHERE NOT EXISTS (
          SELECT 1 FROM transactions t WHERE t.order_detail_id = od.order_detail_id AND t.transaction_type='income'
    )
");

// ====================== เพิ่มรายจ่าย ======================
if (isset($_POST['add_transaction'])) {
    $stmt = $conn->prepare("INSERT INTO transactions (transaction_type, amount, transaction_date, expense_type) VALUES ('expense', ?, ?, ?)");
    $stmt->bind_param("dss", $_POST['amount'], $_POST['transaction_date'], $_POST['expense_type']);
    $stmt->execute();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'เพิ่มข้อมูลรายจ่ายสำเร็จ'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ====================== แก้ไขรายจ่าย ======================
if(isset($_POST['edit_id'])){
    $stmt = $conn->prepare("UPDATE transactions SET amount=?, transaction_date=?, expense_type=? WHERE transaction_id=?");
    $stmt->bind_param("dssi", $_POST['amount'], $_POST['transaction_date'], $_POST['expense_type'], $_POST['edit_id']);
    $stmt->execute();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลรายจ่ายเรียบร้อย'];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ====================== ลบแบบ Hard Delete ======================
if(isset($_POST['delete_id'])){
    $stmt = $conn->prepare("DELETE FROM transactions WHERE transaction_id=?");
    $stmt->bind_param("i", $_POST['delete_id']);
    $stmt->execute();
    $_SESSION['alert'] = ['type' => 'info', 'message' => 'ลบข้อมูลเรียบร้อย'];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ====================== ดึงข้อมูล transactions ======================
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT t.transaction_id, t.transaction_type, t.amount, t.transaction_date, t.expense_type,
               o.order_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN orders o ON od.order_id = o.order_id
        LEFT JOIN products p ON od.product_id = p.product_id
        WHERE 1=1 ";

if ($filter == 'income') $sql .= " AND t.transaction_type='income'";
elseif ($filter == 'expense') $sql .= " AND t.transaction_type='expense'";

$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข้อมูลรายรับ-รายจ่าย</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --light-teal-bg: #eaf6f6;
        --navy-blue: #001f3f;
        --gold-accent: #fca311;
        --white: #ffffff;
        --light-gray: #f8f9fa;
        --gray-border: #ced4da;
        --text-color: #495057;
        --success: #2ecc71;
        --danger: #e74c3c;
        --warning: #f39c12;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        color: var(--text-color);
        display: flex;
    }

    /* --- Sidebar (คงสไตล์เดิมไว้) --- */
    .sidebar { width: 250px; background-color: var(--primary-color); color: white; padding: 2rem 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s ease-in-out; box-shadow: 2px 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; align-items: center; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .logo { width: 100px; height: 100px; border-radius: 50%; border: 4px solid rgba(255, 255, 255, 0.3); object-fit: cover; margin-bottom: 1.5rem; }
    .sidebar h3 { font-size: 1.5rem; margin-bottom: 2rem; font-weight: 700; text-align: center; color: white; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1.5rem; border-radius: 8px; width: 100%; text-align: left; transition: background-color 0.2s ease, transform 0.2s ease; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
    .sidebar a:hover { background-color: rgba(255, 255, 255, 0.2); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(255, 255, 255, 0.3); font-weight: 500; }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; justify-content: center; align-items: center; }

    /* --- Main Content Layout --- */
    .content { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s ease-in-out; }
    .content.full-width { margin-left: 0; }

    /* --- Header --- */
    .header-main {
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 1.5rem;
        margin-bottom: 2rem;
    }
    .header-main h2 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem; color: var(--navy-blue);
        margin: 0; border: none;
        display: flex; align-items: center; gap: 1rem;
    }

    /* --- Search & Actions --- */
    .container { background-color: var(--white); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .search-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; flex-wrap: wrap; }
    .search-box {
        flex-grow: 1; padding: 0.8rem 1rem; border-radius: 8px;
        border: 1px solid var(--gray-border); font-size: 1rem;
        transition: all 0.3s;
    }
    .search-box:focus {
        outline: none; border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
    }
    .action-btn {
        padding: 0.8rem 1.5rem; border: none; border-radius: 8px;
        font-weight: 500; cursor: pointer; color: white; font-size: 1rem;
        display: flex; align-items: center; gap: 0.5rem;
        transition: all 0.2s;
    }
    .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .find-btn { background-color: var(--primary-color); }
    .find-btn:hover { background-color: #2980b9; }
    .add-btn { background-color: var(--danger); }
    .add-btn:hover { background-color: #c0392b; }

    /* --- Table --- */
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        background-color: var(--navy-blue); color: var(--white);
        padding: 15px; text-align: left; font-size: 0.9rem;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    tbody td {
        padding: 15px; border-bottom: 1px solid #e0e0e0; color: #333;
    }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }

    .btn-group { display: flex; gap: 0.5rem; justify-content: flex-start; }
    .btn-action { border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; color: white; transition: transform 0.2s ease; white-space: nowrap; }
    .btn-action:hover { transform: translateY(-2px); }
    .btn-delete { background-color: var(--danger); }
    .btn-delete:hover { background-color: #c0392b; }
    .btn-edit { background-color: var(--warning); color: #212529; }
    .btn-edit:hover { background-color: #e67e22; }

    /* --- Modal --- */
    .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 31, 63, 0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
    .modal-content { background-color: var(--white); margin: auto; padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 550px; position: relative; animation: fadeInScale 0.4s ease-out; }
    @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    .close-btn { color: #aaa; position: absolute; top: 15px; right: 20px; font-size: 2rem; font-weight: bold; cursor: pointer; transition: color 0.2s, transform 0.2s; }
    .close-btn:hover { color: var(--danger); transform: rotate(90deg); }
    .modal h3 { font-size: 2rem; color: var(--dark-teal); text-align: center; margin-bottom: 25px; }
    .modal form { display: flex; flex-direction: column; gap: 5px; }
    .modal form label { display: block; margin-top: 10px; margin-bottom: 5px; font-weight: 500; color: var(--secondary-color); }
    .modal form input, .modal form select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; font-family: 'Sarabun', sans-serif; transition: all 0.3s; }
    .modal form input:focus, .modal form select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 8px rgba(52, 152, 219, 0.25); }
    .modal form button { width: 100%; padding: 12px; font-size: 1.1rem; margin-top: 20px; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: 500; transition: background-color 0.3s, transform 0.2s; }
    
    #add-modal button { background-color: var(--danger); }
    #add-modal button:hover { background-color: #c0392b; }
    #edit-modal button { background-color: var(--warning); color:#212529; }
    #edit-modal button:hover { background-color: #e67e22; }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
    <h3>ข้อมูลรายรับ-รายจ่าย</h3>
    <a href="index.php"><i class="fas fa-home"></i>&nbsp; <span>หน้าหลัก</span></a>
    <a href="transactions.php?filter=all" class="<?= $filter == 'all' ? 'active' : '' ?>"><i class="fas fa-list"></i>&nbsp; <span>ทั้งหมด</span></a>
    <a href="transactions.php?filter=income" class="<?= $filter == 'income' ? 'active' : '' ?>"><i class="fas fa-arrow-down"></i>&nbsp; <span>รายรับ</span></a>
    <a href="transactions.php?filter=expense" class="<?= $filter == 'expense' ? 'active' : '' ?>"><i class="fas fa-arrow-up"></i>&nbsp; <span>รายจ่าย</span></a>
</div>

<div class="content" id="content">
    <div class="header-main">
        <h2><i class="fas fa-exchange-alt"></i> ข้อมูลรายรับ-รายจ่าย</h2>
    </div>

    <div class="container">
        <div class="search-row">
            <input type="text" id="search-input" class="search-box" placeholder="ค้นหารายละเอียด, จำนวนเงิน..." onkeyup="searchTransaction()"/>
            <button class="action-btn find-btn" onclick="searchTransaction()"><i class="fas fa-search"></i> ค้นหา</button>
            <button type="button" class="action-btn add-btn" onclick="openAddModal()">
                <i class="fas fa-minus-circle"></i> &nbsp; เพิ่มรายจ่าย
            </button>
        </div>
        <div class="table-wrapper">
            <table id="transactions-table">
                <thead>
                    <tr>
                        <th>รหัส</th> <th>ประเภท</th> <th>จำนวนเงิน (บาท)</th> <th>วันที่</th> <th>รายละเอียด</th> <th>รหัสสั่งซื้อ</th> <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                <tr class="transaction-row">
                    <td style="text-align:center;"><?= $row['transaction_id'] ?></td>
                    <td style="text-align:center;"><?= $row['transaction_type']=='income' ? '<span style="color: #27ae60; font-weight:bold;"><i class="fas fa-plus-circle"></i> รายรับ</span>' : '<span style="color: #c0392b; font-weight:bold;"><i class="fas fa-minus-circle"></i> รายจ่าย</span>' ?></td>
                    <td style="text-align:right; font-weight:bold;"><?= number_format($row['amount'], 2) ?></td>
                    <td style="text-align:center;"><?= date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543) ?></td>
                    <td style="text-align:left;"><?= $row['transaction_type']=='expense' ? htmlspecialchars($row['expense_type'] ?? '-') : htmlspecialchars($row['product_name'] ?? 'N/A') ?></td>
                    <td style="text-align:center;"><?= $row['order_id'] ?? '-' ?></td>
                    <td>
                        <div class="btn-group">
                            <?php if($row['transaction_type']=='expense'): ?>
                                <button type="button" class="btn-action btn-edit"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                    <i class="fas fa-edit"></i> แก้ไข
                                </button>
                                <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0">
                                    <input type="hidden" name="delete_id" value="<?= $row['transaction_id'] ?>">
                                    <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash-alt"></i> ลบ</button>
                                </form>
                            <?php elseif($row['transaction_type']=='income'): ?>
                                <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0">
                                    <input type="hidden" name="delete_id" value="<?= $row['transaction_id'] ?>">
                                    <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash-alt"></i> ลบ</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" style="text-align:center; padding: 2rem;">ไม่มีข้อมูล</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="add-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('add-modal')">&times;</span>
        <h3><i class="fas fa-minus-circle"></i> เพิ่มรายการรายจ่าย</h3>
        <form method="POST">
            <input type="hidden" name="add_transaction" value="1">
            <label for="add-amount">จำนวนเงิน:</label>
            <input type="number" name="amount" id="add-amount" step="0.01" min="0" required>
            
            <label for="add-date">วันที่:</label>
            <input type="date" name="transaction_date" id="add-date" value="<?= date('Y-m-d')?>" required>
            
            <label for="add-expense-type">ประเภทค่าใช้จ่าย:</label>
            <input type="text" name="expense_type" id="add-expense-type" placeholder="เช่น ค่าไฟ, ค่าน้ำมัน" required>
            
            <button type="submit"><i class="fas fa-save"></i> บันทึกรายจ่าย</button>
        </form>
    </div>
</div>

<div id="edit-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('edit-modal')">&times;</span>
        <h3><i class="fas fa-edit"></i> แก้ไขรายการรายจ่าย</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit-id">
            <label for="edit-amount">จำนวนเงิน:</label>
            <input type="number" name="amount" id="edit-amount" step="0.01" min="0" required>
            
            <label for="edit-date">วันที่:</label>
            <input type="date" name="transaction_date" id="edit-date" required>
            
            <label for="edit-expense-type">ประเภทค่าใช้จ่าย:</label>
            <input type="text" name="expense_type" id="edit-expense-type" required>
            
            <button type="submit"><i class="fas fa-sync-alt"></i> บันทึกการแก้ไข</button>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
    
    function openAddModal() { openModal('add-modal'); }

    function openEditModal(rowData) {
        document.getElementById('edit-id').value = rowData.transaction_id;
        document.getElementById('edit-amount').value = rowData.amount;
        document.getElementById('edit-date').value = rowData.transaction_date;
        document.getElementById('edit-expense-type').value = rowData.expense_type;
        openModal('edit-modal');
    }
    
    function confirmDelete(event, form) {
        event.preventDefault(); 
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: "คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้อย่างถาวร?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    function searchTransaction() {
        const input = document.getElementById('search-input');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('transactions-table');
        const rows = table.getElementsByClassName('transaction-row');
        for (let i = 0; i < rows.length; i++) {
            let rowText = rows[i].textContent || rows[i].innerText;
            if (rowText.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }

    // Sidebar toggle functionality
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const toggleBtn = document.getElementById('toggle-btn');
    
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
        content.classList.toggle('full-width');
    });

    if (window.matchMedia('(max-width: 768px)').matches) {
        sidebar.classList.add('hidden');
        content.classList.add('full-width');
    }
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
             if (document.getElementById(event.target.id)) {
                closeModal(event.target.id);
            }
        }
    }
    
    // SweetAlert for session messages
    <?php if(isset($_SESSION['alert'])): ?>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['message'] ?>',
        showConfirmButton: false,
        timer: 1800,
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
    <?php unset($_SESSION['alert']); endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>