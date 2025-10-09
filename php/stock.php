<?php
session_start();
require_once "auth.php";
require_once "db.php";

// ====================================================================================
//  Sync Stock: ทำให้การ Sync สมบูรณ์โดยเพิ่มการ UPDATE กลับไปที่ order_details
// ====================================================================================

// 1. INSERT ข้อมูลการนำออกลงตาราง stock เฉพาะรายการที่ยังไม่เคยถูก Sync (stock_removed = 0)
//    (เพิ่ม WHERE od.stock_removed = 0 เพื่อให้ทำงานเฉพาะกับรายการใหม่จริงๆ)
$conn->query("
    INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id)
    SELECT od.product_id, 'remove', o.order_date, od.quantity, od.order_id
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    WHERE od.stock_removed = 0
");

// 2. UPDATE flag 'stock_removed' ในตาราง order_details ให้เป็น 1 เพื่อไม่ให้ Sync ซ้ำ
$conn->query("
    UPDATE order_details SET stock_removed = 1 WHERE stock_removed = 0
");


// ================== เพิ่มข้อมูลสต็อก ==================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $stock_date = $_POST['stock_date'];
    $quantity = (int)$_POST['quantity'];
    $stock_type = 'import';
    $order_id = NULL; // Order ID is not submitted from the add form

    $stmt = $conn->prepare("INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $product_id, $stock_type, $stock_date, $quantity, $order_id);
    $stmt->execute();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'เพิ่มข้อมูลนำเข้าสต็อกสำเร็จ'];
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
    // Since the form field is removed, $_POST['order_id'] will not be set.
    // This correctly results in $order_id being NULL.
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : NULL;

    $stmt = $conn->prepare("UPDATE stock SET product_id=?, stock_type=?, stock_date=?, quantity=?, order_id=? WHERE stock_id=?");
    $stmt->bind_param("issiii", $product_id, $stock_type, $stock_date, $quantity, $order_id, $stock_id);
    $stmt->execute();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลสต็อกเรียบร้อย'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================== ดึงข้อมูล stock (ใช้ Prepared Statement เพื่อความปลอดภัย) ==================
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT s.*, p.product_name, p.product_type 
        FROM stock s 
        JOIN products p ON s.product_id = p.product_id 
        WHERE p.status = 1";

$params = [];
$types = '';

if ($filter === "import") {
    $sql .= " AND s.stock_type = ?";
    $params[] = 'import';
    $types .= 's';
} elseif ($filter === "remove") {
    $sql .= " AND s.stock_type = ?";
    $params[] = 'remove';
    $types .= 's';
}

$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


// ================== ดึงข้อมูลสินค้าสำหรับ dropdown ==================
$products_result = $conn->query("SELECT product_id, product_name, product_type FROM products WHERE status = 1 ORDER BY product_name");

// ================== คำนวณสต็อกคงเหลือ ==================
$balances_result = $conn->query("
    SELECT 
        p.product_type,
        COALESCE(SUM(CASE WHEN s.stock_type='import' THEN s.quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN s.stock_type='remove' THEN s.quantity ELSE 0 END), 0) AS balance
    FROM products p
    LEFT JOIN stock s ON p.product_id = s.product_id
    WHERE p.status = 1
    GROUP BY p.product_type
    ORDER BY p.product_type
");

$balances = [];
if ($balances_result) {
    while ($row = $balances_result->fetch_assoc()) {
        $balances[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>จัดการข้อมูลคลังสินค้า</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS ทั้งหมดเหมือนเดิม */
        /* ==========================
    📌 Import Fonts
========================== */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

        /* ==========================
    🎨 CSS Variables
========================== */
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

        /* ==========================
    🌍 Global Reset
========================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--light-teal-bg);
            color: var(--text-color);
            display: flex;
        }

        /* ==========================
    📂 Sidebar
========================== */
        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
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
            z-index: 1000;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.3);
            font-weight: 500;
        }

        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1001;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* ==========================
    📑 Main Content Layout
========================== */
        .content {
            margin-left: 250px;
            padding: 2rem;
            flex-grow: 1;
            transition: margin-left 0.3s ease-in-out;
        }

        .content.full-width {
            margin-left: 0;
        }

        /* ==========================
    🏷️ Header
========================== */
        .header-main {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .header-main h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--navy-blue);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* ==========================
    💳 Balance Cards
========================== */
        .balance-container {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .balance-card {
            background: linear-gradient(135deg, var(--primary-color), #5dade2);
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex: 1;
            min-width: 280px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: var(--white);
        }

        .balance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.4);
        }

        .balance-icon {
            background-color: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2rem;
        }

        .balance-info {
            text-align: right;
            flex-grow: 1;
        }

        .balance-type {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
        }

        .balance-value {
            font-size: 2.5rem;
            font-weight: 700;
        }

        /* ==========================
    🔎 Search & Actions
========================== */
        .container {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .search-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex-grow: 1;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--gray-border);
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }

        /* Buttons */
        .action-btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            color: white;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .add-btn {
            background-color: var(--success);
        }

        .add-btn:hover {
            background-color: #27ae60;
        }

        /* ==========================
    📊 Table
========================== */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background-color: var(--navy-blue);
            color: var(--white);
            padding: 15px;
            text-align: left;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #333;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:nth-child(even) {
            background-color: var(--light-gray);
        }

        tbody tr:hover {
            background-color: #d4eaf7;
        }

        /* Action Buttons in Table */
        .btn-group {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
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

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .btn-edit {
            background-color: var(--warning);
            color: #212529;
        }

        .btn-edit:hover {
            background-color: #e67e22;
        }

        /* ==========================
    📌 Modal
========================== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 31, 63, 0.6);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--white);
            margin: auto;
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 550px;
            position: relative;
            animation: fadeInScale 0.4s ease-out;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .close-btn {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }

        .close-btn:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }

        .modal h3 {
            font-size: 2rem;
            color: var(--navy-blue);
            text-align: center;
            margin-bottom: 25px;
        }

        .modal form {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .modal form label {
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--secondary-color);
        }

        .modal form input,
        .modal form select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--gray-border);
            font-size: 1rem;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }

        .modal form input:focus,
        .modal form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.25);
        }

        .modal form button {
            width: 100%;
            padding: 12px;
            font-size: 1.1rem;
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: white;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
        }

        #addModal button {
            background-color: var(--success);
        }

        #addModal button:hover {
            background-color: #27ae60;
        }

        #editModal button {
            background-color: var(--warning);
            color: #212529;
        }

        #editModal button:hover {
            background-color: #e67e22;
        }
    </style>
</head>

<body>

    <button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>จัดการข้อมูลคลังสินค้า</h2>
        <a href="index.php"><i class="fas fa-home"></i>&nbsp; <span>หน้าหลัก</span></a>
        <a href="stock.php?filter=all" class="<?= $filter == 'all' ? 'active' : '' ?>"><i class="fas fa-list"></i>&nbsp; <span>ทั้งหมด</span></a>
        <a href="stock.php?filter=import" class="<?= $filter == 'import' ? 'active' : '' ?>"><i class="fas fa-arrow-down"></i>&nbsp; <span>นำเข้า</span></a>
        <a href="stock.php?filter=remove" class="<?= $filter == 'remove' ? 'active' : '' ?>"><i class="fas fa-arrow-up"></i>&nbsp; <span>นำออก</span></a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i>&nbsp; <span>คำสั่งซื้อ</span></a>
        <a href="stock_dashboard.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>จัดการรายงาน</span></a>
    </div>

    <div class="content" id="content">
        <div class="header-main">
            <h2><i class="fas fa-warehouse"></i>จัดการข้อมูลคลังสินค้า</h2>
        </div>

        <div class="balance-container">
            <?php if (!empty($balances)): ?>
                <?php foreach ($balances as $balance): ?>
                    <div class="balance-card">
                        <div class="balance-icon">
                            <?php if (mb_strpos($balance['product_type'], 'ถัง') !== false): ?>
                                <i class="fas fa-tint"></i>
                            <?php elseif (mb_strpos($balance['product_type'], 'แพ็ค') !== false): ?>
                                <i class="fas fa-box-open"></i>
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        <div class="balance-info">
                            <div class="balance-type">คงเหลือ: <?= htmlspecialchars($balance['product_type']) ?></div>
                            <div class="balance-value"><?= number_format($balance['balance']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>ไม่พบข้อมูลยอดคงเหลือ</p>
            <?php endif; ?>
        </div>

        <div class="container">
            <div class="search-row">
                <input type="text" id="search-input" class="search-box" placeholder="ค้นหารายการสต็อก..." onkeyup="searchStock()" />
                <button type="button" class="action-btn add-btn" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i> &nbsp; เพิ่มรายการนำเข้า
                </button>
            </div>
            <div class="table-wrapper">
                <table id="stock-table">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>สินค้า</th>
                            <th>ประเภท</th>
                            <th>วันที่</th>
                            <th>จำนวน</th>
                            <th>รหัสสั่งซื้อ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="stock-row">
                                    <td style="text-align:center;"><?= $row['stock_id'] ?></td>
                                    <td style="text-align:left;"><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td style="text-align:center;"><?= $row['stock_type'] == 'import' ? '<span style="color: #27ae60; font-weight:500;">นำเข้า</span>' : '<span style="color: #c0392b; font-weight:500;">นำออก</span>' ?></td>
                                    <td style="text-align:center;"><?= date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543) ?></td>
                                    <td style="text-align:center; font-weight:bold;"><?= $row['quantity'] ?></td>
                                    <td style="text-align:center;">
                                        <?php if (!empty($row['order_id'])): ?>
                                            <a href="https://computer.surin.rmuti.ac.th/watersystem/bigproject/php/order_details.php?order_id=<?= $row['order_id'] ?>"
                                                style="color:#2980b9; font-weight:bold; text-decoration:none;"
                                                title="ดูรายละเอียดคำสั่งซื้อ #<?= $row['order_id'] ?>"
                                                target="_blank">
                                                <?= $row['order_id'] ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <!-- //โค้ดเดิม -->
                                    <!-- <td style="text-align:center;"><?= $row['order_id'] ?? '-' ?></td> -->
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($row['stock_type'] == 'import'): ?>
                                                <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:2rem;">ไม่พบข้อมูลสต็อก</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('addModal')">&times;</span>
            <h3><i class="fas fa-plus-circle"></i> เพิ่มข้อมูลสต็อก (นำเข้า)</h3>
            <form method="POST">
                <input type="hidden" name="add_stock" value="1" />
                <label for="add-product-id">สินค้า</label>
                <select name="product_id" id="add-product-id" required>
                    <option value="">-- เลือกสินค้า --</option>
                    <?php if ($products_result) {
                        $products_result->data_seek(0);
                        while ($prod = $products_result->fetch_assoc()): ?>
                            <option value="<?= $prod['product_id'] ?>"><?= htmlspecialchars($prod['product_name']) ?></option>
                    <?php endwhile;
                    } ?>
                </select>
                <label for="add-stock-date">วันที่</label>
                <input type="date" name="stock_date" id="add-stock-date" value="<?= date('Y-m-d') ?>" required />
                <label for="add-quantity">จำนวน</label>
                <input type="number" name="quantity" id="add-quantity" min="1" required />
                <button type="submit"><i class="fas fa-save"></i> บันทึก</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
            <h3><i class="fas fa-edit"></i> แก้ไขข้อมูลสต็อก</h3>
            <form method="POST">
                <input type="hidden" name="edit_stock" value="1" />
                <input type="hidden" name="stock_id" id="edit_stock_id" />
                <input type="hidden" name="stock_type" id="edit_stock_type" />
                <label for="edit-product-id">สินค้า</label>
                <select name="product_id" id="edit_product_id" required>
                    <option value="">-- เลือกสินค้า --</option>
                    <?php if ($products_result) {
                        $products_result->data_seek(0);
                        while ($prod = $products_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($prod['product_id']) ?>"><?= htmlspecialchars($prod['product_name']) ?></option>
                    <?php endwhile;
                    } ?>
                </select>
                <label for="edit-stock-date">วันที่</label>
                <input type="date" name="stock_date" id="edit_stock_date" required />
                <label for="edit-quantity">จำนวน</label>
                <input type="number" name="quantity" id="edit_quantity" min="1" required />
                <button type="submit"><i class="fas fa-sync-alt"></i> บันทึกการแก้ไข</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openAddModal() {
            openModal('addModal');
        }

        function openEditModal(rowData) {
            document.getElementById('edit_stock_id').value = rowData.stock_id;
            document.getElementById('edit_product_id').value = rowData.product_id;
            document.getElementById('edit_stock_type').value = rowData.stock_type;
            document.getElementById('edit_stock_date').value = rowData.stock_date;
            document.getElementById('edit_quantity').value = rowData.quantity;
            openModal('editModal');
        }

        function searchStock() {
            const input = document.getElementById('search-input');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('stock-table');
            const rows = table.getElementsByClassName('stock-row');
            for (let i = 0; i < rows.length; i++) {
                let rowText = '';
                const cells = rows[i].getElementsByTagName('td');
                for (let j = 0; j < cells.length - 1; j++) {
                    rowText += (cells[j].textContent || cells[j].innerText) + ' ';
                }
                if (rowText.toUpperCase().indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

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

        <?php if (isset($_SESSION['alert'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['alert']['type'] ?>',
                title: '<?= $_SESSION['alert']['message'] ?>',
                showConfirmButton: false,
                timer: 1800,
                toast: true,
                position: 'top-end',
                timerProgressBar: true
            });
        <?php unset($_SESSION['alert']);
        endif; ?>
    </script>
</body>

</html>
<?php $conn->close(); ?>