<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว


// ====================== Sync รายรับจาก order_details ======================
$conn->query("
    INSERT INTO transactions (transaction_type, amount, transaction_date, order_detail_id, deleted)
    SELECT 'income', od.quantity * od.price, o.order_date, od.order_detail_id, 0
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    WHERE od.deleted=0 AND o.deleted=0
      AND NOT EXISTS (
          SELECT 1 FROM transactions t WHERE t.order_detail_id = od.order_detail_id AND t.transaction_type='income'
      )
");

// ====================== เพิ่มรายจ่าย ======================
if (isset($_POST['add_transaction'])) {
    $stmt = $conn->prepare("INSERT INTO transactions (transaction_type, amount, transaction_date, expense_type, deleted) VALUES ('expense', ?, ?, ?, 0)");
    $stmt->bind_param("dss", $_POST['amount'], $_POST['transaction_date'], $_POST['expense_type']);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ====================== แก้ไขรายจ่าย ======================
if(isset($_POST['edit_id'])){
    $stmt = $conn->prepare("UPDATE transactions SET amount=?, transaction_date=?, expense_type=? WHERE transaction_id=?");
    $stmt->bind_param("dssi", $_POST['amount'], $_POST['transaction_date'], $_POST['expense_type'], $_POST['edit_id']);
    $stmt->execute();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ====================== ลบแบบ Soft Delete ======================
if(isset($_POST['delete_id'])){
    $stmt = $conn->prepare("UPDATE transactions SET deleted=1 WHERE transaction_id=?");
    $stmt->bind_param("i", $_POST['delete_id']);
    $stmt->execute();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ====================== ดึงข้อมูล transactions ======================
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT t.transaction_id, t.transaction_type, t.amount, t.transaction_date, t.expense_type,
                od.order_detail_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id
        WHERE t.deleted=0 ";

if ($filter == 'income') $sql .= " AND t.transaction_type='income'";
elseif ($filter == 'expense') $sql .= " AND t.transaction_type='expense'";

$sql .= " ORDER BY t.transaction_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข้อมูลรายรับ-รายจ่าย</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* --- Global Styles & Typography --- */
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Sarabun', sans-serif;
  background-color: #f4f7f9;
  color: #2c3e50;
  line-height: 1.6;
  font-size: 16px;
  display: flex;
}

h2 {
  font-size: 2rem;
  margin-bottom: 1.5rem;
  font-weight: 700;
  color: #3498db;
  border-bottom: 3px solid #3498db;
  padding-bottom: 0.5rem;
}

/* --- Sidebar Section --- */
.sidebar {
  width: 250px;
  background-color: #3498db;
  color: white;
  padding: 2rem 1.5rem;
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  transition: transform 0.3s ease-in-out;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
  z-index: 100;
}

.sidebar.hidden {
  transform: translateX(-100%);
}

.logo {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  border: 4px solid rgba(255, 255, 255, 0.3);
  object-fit: cover;
  margin-bottom: 1.5rem;
}

.sidebar h2 {
  font-size: 1.5rem;
  margin-bottom: 2rem;
  font-weight: 700;
  text-align: center;
  border-bottom: none;
  padding-bottom: 0;
  color: white;
}

.sidebar a {
  color: white;
  text-decoration: none;
  font-size: 1.1rem;
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  width: 100%;
  text-align: left;
  transition: background-color 0.2s ease, transform 0.2s ease;
  margin-bottom: 0.5rem;
  display: block;
}

.sidebar a:hover {
  background-color: rgba(255, 255, 255, 0.2);
  transform: translateX(5px);
}

.sidebar a.active {
  background-color: rgba(255, 255, 255, 0.3);
  font-weight: 500;
}

.toggle-btn {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 101;
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  font-size: 1.5rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transition: right 0.3s ease-in-out;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* --- Main Content Section --- */
.content {
  margin-left: 250px;
  padding: 2rem;
  flex-grow: 1;
  transition: margin-left 0.3s ease-in-out;
}

.content.full-width {
  margin-left: 0;
}

/* --- Balance Cards Section (kept for potential future use) --- */
.balance-container {
  display: none; /* Hide since it's not in the HTML */
}

/* --- Search & Actions Section --- */
.search-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  padding: 0.75rem 1.25rem;
  border: 1px solid #e0e6ea;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.search-box:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  color: white;
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background-color: #3498db;
}
.btn-primary:hover {
  background-color: #2980b9;
  transform: translateY(-2px);
}

.btn-success {
  background-color: #2ecc71;
}
.btn-success:hover {
  background-color: #27ae60;
  transform: translateY(-2px);
}

/* --- Table Section --- */
table {
  width: 100%;
  border-collapse: collapse;
  background: #ffffff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

thead {
  background-color: #3498db;
  color: white;
}

th, td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #e0e6ea;
}

th {
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.9rem;
  letter-spacing: 0.5px;
}

tbody tr:nth-child(even) {
  background-color: #f9f9f9;
}

tbody tr:hover {
  background-color: #f1faff;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-action {
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  color: white;
  transition: transform 0.2s ease;
  white-space: nowrap;
}

.btn-delete {
  background-color: #e74c3c;
}

.btn-delete:hover {
  background-color: #c0392b;
  transform: translateY(-2px);
}

.btn-edit {
  background-color: #f39c12;
}

.btn-edit:hover {
  background-color: #e67e22;
  transform: translateY(-2px);
}

/* --- Modal Section --- */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(5px);
  padding-top: 60px;
}

.modal-content {
  background-color: #ffffff;
  margin: 5% auto;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  max-width: 500px;
  position: relative;
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.close-btn {
  color: #aaa;
  position: absolute;
  top: 1rem;
  right: 1.5rem;
  font-size: 2rem;
  font-weight: bold;
  cursor: pointer;
  transition: color 0.2s ease;
}

.close-btn:hover {
  color: #e74c3c;
}

.modal-header {
  font-size: 1.5rem;
  font-weight: 700;
  color: #3498db;
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #3498db;
  padding-bottom: 0.5rem;
}

.modal form label {
  display: block;
  margin-bottom: 1rem;
  font-weight: 500;
  color: #7f8c8d;
}

.modal form input,
.modal form select {
  width: 100%;
  padding: 0.75rem;
  margin-top: 0.5rem;
  border: 1px solid #e0e6ea;
  border-radius: 8px;
  font-size: 1rem;
}

.modal form button {
  width: 100%;
  padding: 1rem;
  background-color: #2ecc71;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  margin-top: 1rem;
}

.modal form button:hover {
  background-color: #27ae60;
  transform: translateY(-2px);
}

/* --- Responsive Design --- */
@media (max-width: 768px) {
  .sidebar {
    width: 200px;
    transform: translateX(-100%);
  }
  .sidebar.show {
    transform: translateX(0);
  }
  .content {
    margin-left: 0;
  }
  .toggle-btn {
    right: 1rem;
  }
  .search-row {
    flex-direction: column;
    align-items: stretch;
  }
  .search-row .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>ข้อมูลสต็อกสินค้า</h2>
  <a href="index.php">
    <i class="fas fa-home"></i>&nbsp; หน้าหลัก
  </a>
  <a href="transactions.php?filter=all" class="<?= $filter == 'all' ? 'active' : '' ?>">
    <i class="fas fa-list"></i>&nbsp; ทั้งหมด
  </a>
  <a href="transactions.php?filter=income" class="<?= $filter == 'income' ? 'active' : '' ?>">
    <i class="fas fa-arrow-down"></i>&nbsp; รายรับ
  </a>
  <a href="transactions.php?filter=expense" class="<?= $filter == 'expense' ? 'active' : '' ?>">
    <i class="fas fa-arrow-up"></i>&nbsp; รายจ่าย
  </a>
</div>

<div class="content" id="content">
<h2>ข้อมูลรายรับ-รายจ่าย</h2>
<div class="search-row">
  <input type="text" id="search-input" class="search-box" placeholder="🔍 ค้นหา"/>
  <button type="button" class="btn btn-primary" onclick="searchTransaction()">🔍 ค้นหา</button>
  <button type="button" class="btn btn-success" onclick="openAddModal()">
    <i class="fas fa-plus-circle"></i> &nbsp; เพิ่มรายจ่าย
  </button>
</div>

<table id="transactions-table">
<thead>
<tr>
    <th>รหัส</th>
    <th>ประเภท</th>
    <th>ราคา</th>
    <th>วันที่</th>
    <th>ประเภท/สินค้า</th>
    <th>รหัสสั่งซื้อ</th>
    <th>จัดการ</th>
</tr>
</thead>
<tbody>
<?php if($result->num_rows>0): while($row=$result->fetch_assoc()): ?>
<tr class="transaction-row">
    <td><?= $row['transaction_id'] ?></td>
    <td><?= $row['transaction_type']=='income'?'<span style="color: #2ecc71;">รายรับ</span>':'<span style="color: #e74c3c;">รายจ่าย</span>' ?></td>
    <td><?= number_format($row['amount'],2) ?></td>
    <td><?= date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date']))+543) ?></td>
    <td><?= $row['transaction_type']=='expense' ? htmlspecialchars($row['expense_type'] ?? '-') : htmlspecialchars($row['product_name'] ?? '-') ?></td>
    <td><?= $row['order_detail_id'] ?? '-' ?></td>
    <td>
        <div class="btn-group">
            <form method="POST" onsubmit="return confirm('ยืนยันลบ?');">
                <input type="hidden" name="delete_id" value="<?= $row['transaction_id'] ?>">
                <button type="submit" class="btn-action btn-delete">
                    <i class="fas fa-trash-alt"></i> ลบ
                </button>
            </form>
            <?php if($row['transaction_type']=='expense'): ?>
            <button type="button" class="btn-action btn-edit"
                onclick="openEditModal(<?= $row['transaction_id'] ?>, <?= $row['amount'] ?>,'<?= $row['transaction_date'] ?>','<?= addslashes($row['expense_type']) ?>')">
                <i class="fas fa-edit"></i> แก้ไข
            </button>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="7">ไม่มีข้อมูล</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Modal Add -->
<div id="add-modal" class="modal">
<div class="modal-content">
<span class="close-btn" onclick="closeAddModal()">&times;</span>
<h3 class="modal-header">เพิ่มรายจ่าย</h3>
<form method="POST">
    <input type="hidden" name="add_transaction" value="1">
    <label>ราคา:
      <input type="number" name="amount" step="0.01" min="0" required>
    </label>
    <label>วันที่:
      <input type="date" name="transaction_date" required>
    </label>
    <label>ประเภทค่าใช้จ่าย:
      <input type="text" name="expense_type" required>
    </label>
    <button type="submit">บันทึก</button>
</form>
</div>
</div>

<!-- Modal Edit -->
<div id="edit-modal" class="modal">
<div class="modal-content">
<span class="close-btn" onclick="closeEditModal()">&times;</span>
<h3 class="modal-header">แก้ไขรายจ่าย</h3>
<form method="POST">
    <input type="hidden" name="edit_id" id="edit-id">
    <label>ราคา:
      <input type="number" name="amount" id="edit-amount" step="0.01" min="0" required>
    </label>
    <label>วันที่:
      <input type="date" name="transaction_date" id="edit-date" required>
    </label>
    <label>ประเภทค่าใช้จ่าย:
      <input type="text" name="expense_type" id="edit-expense-type" required>
    </label>
    <button type="submit">บันทึกการแก้ไข</button>
</form>
</div>
</div>

<script>
    // Get modals
    const addModal = document.getElementById('add-modal');
    const editModal = document.getElementById('edit-modal');

    // Functions to open and close modals
    function openAddModal() {
        addModal.style.display = 'block';
    }

    function closeAddModal() {
        addModal.style.display = 'none';
    }

    function openEditModal(id, amount, date, expenseType) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-amount').value = amount;
        document.getElementById('edit-date').value = date;
        document.getElementById('edit-expense-type').value = expenseType;
        editModal.style.display = 'block';
    }

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    // Function for client-side search
    function searchTransaction() {
        const input = document.getElementById('search-input');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('transactions-table');
        const rows = table.getElementsByClassName('transaction-row');

        for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let found = false;
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            if (found) {
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
    const isMobile = window.matchMedia('(max-width: 768px)');

    function toggleSidebar() {
      sidebar.classList.toggle('hidden');
      if (isMobile.matches) {
        content.classList.toggle('full-width');
      } else {
        content.style.marginLeft = sidebar.classList.contains('hidden') ? '0' : '250px';
      }
    }

    toggleBtn.addEventListener('click', toggleSidebar);

    // Initial state based on screen size
    if (isMobile.matches) {
        sidebar.classList.add('hidden');
        content.classList.add('full-width');
    }
</script>
<?php $conn->close(); ?>
