<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูล

// ====================== Sync รายการสต็อกจาก order_details (นำออก) ======================
$conn->query("
    INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id, deleted)
    SELECT od.product_id, 'remove', o.order_date, od.quantity, od.order_id, 0
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    WHERE od.deleted=0 AND o.deleted=0
      AND NOT EXISTS (
          SELECT 1 FROM stock s 
          WHERE s.order_id = od.order_id 
            AND s.product_id = od.product_id 
            AND s.stock_type = 'remove'
      )
");

// ================== ลบข้อมูล (soft delete) ==================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("UPDATE stock SET deleted = 1 WHERE stock_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================== เพิ่มข้อมูลสต็อก ==================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $stock_date = $_POST['stock_date'];
    $quantity = (int)$_POST['quantity'];
    $stock_type = $_POST['stock_type']; // import หรือ remove
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : NULL;

    $stmt = $conn->prepare("INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $product_id, $stock_type, $stock_date, $quantity, $order_id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================== แก้ไขข้อมูลสต็อก ==================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_stock'])) {
    $stock_id = (int)$_POST['stock_id'];
    $product_id = (int)$_POST['product_id'];
    $stock_date = $_POST['stock_date'];
    $quantity = (int)$_POST['quantity'];
    $stock_type = $_POST['stock_type'];
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : NULL;

    $stmt = $conn->prepare("UPDATE stock SET product_id=?, stock_type=?, stock_date=?, quantity=?, order_id=? WHERE stock_id=?");
    $stmt->bind_param("issiii", $product_id, $stock_type, $stock_date, $quantity, $order_id, $stock_id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================== ดึงข้อมูล stock ==================
$filter = $_GET['filter'] ?? 'all';
$where = "s.deleted = 0";
if ($filter === "import") $where .= " AND s.stock_type = 'import'";
elseif ($filter === "remove") $where .= " AND s.stock_type = 'remove'";

$sql = "SELECT s.*, p.product_name, p.product_type 
        FROM stock s 
        LEFT JOIN products p ON s.product_id = p.product_id 
        WHERE $where
        ORDER BY s.stock_date DESC, s.stock_id DESC";
$result = $conn->query($sql);

// ================== ดึงข้อมูลสินค้าสำหรับ dropdown ==================
$products_result = $conn->query("SELECT product_id, product_name, product_type FROM products WHERE deleted = 0 ORDER BY product_name");

// ================== คำนวณสต็อกคงเหลือ ==================
$balance = $conn->query("
    SELECT 
        COALESCE(SUM(CASE WHEN p.product_type='ถัง' AND s.stock_type='import' THEN s.quantity ELSE 0 END),0) - 
        COALESCE(SUM(CASE WHEN p.product_type='ถัง' AND s.stock_type='remove' THEN s.quantity ELSE 0 END),0) AS total_t,
        COALESCE(SUM(CASE WHEN p.product_type='แพ็ค' AND s.stock_type='import' THEN s.quantity ELSE 0 END),0) - 
        COALESCE(SUM(CASE WHEN p.product_type='แพ็ค' AND s.stock_type='remove' THEN s.quantity ELSE 0 END),0) AS total_p
    FROM stock s 
    JOIN products p ON s.product_id = p.product_id 
    WHERE s.deleted=0
")->fetch_assoc();

$total_t = $balance['total_t'] ?? 0;
$total_p = $balance['total_p'] ?? 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>ข้อมูลสต็อกสินค้า</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
/* --- General Styles & Typography --- */
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

/* --- Balance Cards Section --- */
.balance-container {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 2rem;
  justify-content: center;
  flex-wrap: wrap;
}

.balance-card {
    background: #3967caff;
    border-radius: 12px;
    /* เพิ่มเงาเพื่อให้ดูมีมิติ */
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
    min-width: 250px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    /* เพิ่มกรอบเส้นบางๆ ที่ขอบ */
    border: 1px solid #e0e6ea;
}


.balance-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.balance-icon {
  background-color: #f4f7f9;
  color: #3498db;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 2rem;
}

.balance-info {
  display: flex;
  flex-direction: column;
}

.balance-type {
  font-size: 1rem;
  color: #e9eff0ff;
  font-weight: 500;
}

.balance-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: #ffffffff;
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
  box-shadow: 0 4px 12px rgba(0, 0, 0, 1.0);
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
  <a href="stock.php?filter=all" class="<?= $filter == 'all' ? 'active' : '' ?>">
    <i class="fas fa-list"></i>&nbsp; ทั้งหมด
  </a>
  <a href="stock.php?filter=import" class="<?= $filter == 'import' ? 'active' : '' ?>">
    <i class="fas fa-arrow-down"></i>&nbsp; นำเข้า
  </a>
  <a href="stock.php?filter=remove" class="<?= $filter == 'remove' ? 'active' : '' ?>">
    <i class="fas fa-arrow-up"></i>&nbsp; นำออก
  </a>
</div>


<div class="content" id="content">
    <h2>ข้อมูลสต็อกสินค้า</h2>
    <div class="search-row">
        <input type="text" id="search-input" class="search-box" placeholder="🔍 ค้นหาสต็อก"/>
        <button type="button" class="btn btn-primary" onclick="searchStock()">🔍 ค้นหา</button>
        <button type="button" class="btn btn-success" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> &nbsp; เพิ่มข้อมูล
        </button>
    </div>

    <div class="balance-container">
        <div class="balance-card">
            <div class="balance-icon">
                <i class="fa-solid fa-glass-water"></i>
            </div>
            <div class="balance-info">
                <div class="balance-type">ถัง</div>
                <div class="balance-value"><?= number_format($total_t) ?></div>
            </div>
        </div>
        <div class="balance-card">
            <div class="balance-icon">
                <i class="fa-solid fa-bottle-water"></i>
            </div>
            <div class="balance-info">
                <div class="balance-type">น้ำแพ็ค</div>
                <div class="balance-value"><?= number_format($total_p) ?></div>
            </div>
        </div>
    </div>

    <table id="stock-table">
      <thead>
        <tr>
          <th>รหัสสต็อก</th>
          <th>สินค้า</th>
          <th>ประเภทสต็อก</th>
          <th>วันที่</th>
          <th>จำนวน</th>
          <th>คำสั่งซื้อ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="stock-row">
                <td><?= $row['stock_id'] ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= $row['stock_type'] == 'import' ? '<span style="color: #2ecc71;">นำเข้า</span>' : '<span style="color: #e74c3c;">นำออก</span>' ?></td>
                <td><?= date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= $row['order_id'] ?? '-' ?></td>
                <td>
                    <div class="btn-group">
                        <form method="GET" onsubmit="return confirm('แน่ใจว่าต้องการลบ?')">
                            <input type="hidden" name="delete_id" value="<?= $row['stock_id'] ?>" />
                            <button type="submit" class="btn-action btn-delete">
                                <i class="fas fa-trash-alt"></i> ลบ
                            </button>
                        </form>
                        <?php if($row['stock_type'] == 'import'): ?>
                        <button class="btn-action btn-edit" onclick='openEditModal(
                            <?= json_encode($row['stock_id']) ?>,
                            <?= json_encode($row['product_id']) ?>,
                            <?= json_encode($row['stock_type']) ?>,
                            <?= json_encode($row['stock_date']) ?>,
                            <?= json_encode($row['quantity']) ?>,
                            <?= json_encode($row['order_id']) ?>
                        )'>
                            <i class="fas fa-edit"></i> แก้ไข
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">ไม่มีข้อมูลสต็อก</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
</div>

<!-- Modal เพิ่มข้อมูล -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAddModal()">&times;</span>
    <h3 class="modal-header">เพิ่มข้อมูลสต็อก</h3>
    <form method="POST">
      <input type="hidden" name="add_stock" value="1" />
      <input type="hidden" name="stock_type" value="import" />
      <label>สินค้า
        <select name="product_id" required>
          <option value="">-- เลือกสินค้า --</option>
          <?php $products_result->data_seek(0); while($prod = $products_result->fetch_assoc()): ?>
            <option value="<?= $prod['product_id'] ?>"><?= htmlspecialchars($prod['product_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </label>
      <label>วันที่
        <input type="date" name="stock_date" required />
      </label>
      <label>จำนวน
        <input type="number" name="quantity" min="1" required />
      </label>
      <label>รหัสคำสั่งซื้อ (ถ้ามี)
        <input type="number" name="order_id" min="1" />
      </label>
      <button type="submit">บันทึก</button>
    </form>
  </div>
</div>


<!-- Modal แก้ไขข้อมูล -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditModal()">&times;</span>
    <h3 class="modal-header">แก้ไขข้อมูลสต็อก</h3>
    <form method="POST">
      <input type="hidden" name="edit_stock" value="1" />
      <input type="hidden" name="stock_id" id="edit_stock_id" />
      <input type="hidden" name="stock_type" id="edit_stock_type" value="import" />

      <label>สินค้า
        <select name="product_id" id="edit_product_id" required>
          <option value="">-- เลือกสินค้า --</option>
          <?php $products_result->data_seek(0); while($prod = $products_result->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($prod['product_id']) ?>"><?= htmlspecialchars($prod['product_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </label>
      <label>วันที่
        <input type="date" name="stock_date" id="edit_stock_date" required />
      </label>
      <label>จำนวน
        <input type="number" name="quantity" id="edit_quantity" min="1" required />
      </label>
      <label>รหัสคำสั่งซื้อ (ถ้ามี)
        <input type="number" name="order_id" id="edit_order_id" min="1" />
      </label>
      <button type="submit">บันทึก</button>
    </form>
  </div>
</div>


<script>
    // Get modals
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');

    // Functions to open and close modals
    function openAddModal() {
        addModal.style.display = 'block';
    }

    function closeAddModal() {
        addModal.style.display = 'none';
    }

    function openEditModal(stockId, productId, stockType, stockDate, quantity, orderId) {
        document.getElementById('edit_stock_id').value = stockId;
        document.getElementById('edit_product_id').value = productId;
        document.getElementById('edit_stock_type').value = stockType;
        document.getElementById('edit_stock_date').value = stockDate;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_order_id').value = orderId;
        editModal.style.display = 'block';
    }

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    // Function for client-side search
    function searchStock() {
        const input = document.getElementById('search-input');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('stock-table');
        const rows = table.getElementsByClassName('stock-row');

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
</body>
</html>
