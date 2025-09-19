<?php
session_start();
require_once "db.php";

// เพิ่มลูกค้า
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['customer_name'])) {
    $stmt = $conn->prepare("INSERT INTO customers (full_name, phone, address) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $_POST['customer_name'], $_POST['customer_phone'], $_POST['customer_address']);
    $stmt->execute();
    header("Location: customers.php"); exit();
}

// ลบลูกค้า (soft delete)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $stmt = $conn->prepare("UPDATE customers SET deleted = 1 WHERE customer_id = ?");
    $stmt->bind_param("i", $_POST['delete_id']);
    $stmt->execute();
    header("Location: customers.php"); exit();
}

// แก้ไขลูกค้า
if (isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("UPDATE customers SET full_name=?, phone=?, address=? WHERE customer_id=?");
    $stmt->bind_param("sssi", $_POST['edit_name'], $_POST['edit_phone'], $_POST['edit_address'], $_POST['edit_id']);
    $stmt->execute();
    header("Location: customers.php"); exit();
}
$sql = "SELECT customer_id, full_name, phone, address FROM customers WHERE deleted=0 ORDER BY customer_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>ข้อมูลลูกค้า</title>
<link rel="stylesheet" href="../css/customers.css"/>

</head>
<body>
<div class="stock-container">
<header>
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
    <h1>ข้อมูลลูกค้า</h1>
    <a href="index.php" class="home-button">🏠 หน้าหลัก </a>
</header>

<div class="content">
<h4>ค้นหาข้อมูลลูกค้า</h4>
<div class="search-row">
    <input type="text" id="search-customer" class="search-box" placeholder="🔍 ค้นหาลูกค้า" />
    <button class="search-btn" onclick="searchCustomer()">  🔍  ค้นหา</button>
    <button class="search-btn" onclick="openModal()">  ➕  เพิ่มข้อมูลลูกค้า </button>
</div>

<table>
<thead>
<tr>
<th>รหัส</th>
<th>ชื่อ-นามสกุล</th>
<th>เบอร์โทรศัพท์</th>
<th>ที่อยู่</th>
<th>จัดการ</th>
</tr>
</thead>
<tbody>
<?php
$sql = "SELECT customer_id, full_name, phone, address 
        FROM customers 
        WHERE deleted = 0 
        ORDER BY customer_id DESC";

$result = $conn->query($sql);
$thai_months = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน",
                "กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
if ($result->num_rows > 0):
  while($row = $result->fetch_assoc()):
?>
<tr>
<td><?= $row['customer_id'] ?></td>
<td><?= htmlspecialchars($row['full_name']) ?></td>
<td><?= htmlspecialchars($row['phone']) ?></td>
<td><?= htmlspecialchars($row['address']) ?></td>
<td style="display:flex;gap:5px;">
<form method="POST" onsubmit="return confirm('แน่ใจหรือไม่ว่าต้องการลบ?')">
<input type="hidden" name="delete_id" value="<?= $row['customer_id'] ?>">
<button type="submit" class="delete-btn">ลบ</button>
</form>
<button class="edit-btn" onclick="openEditModal(<?= $row['customer_id'] ?>,'<?= htmlspecialchars($row['full_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($row['phone'],ENT_QUOTES) ?>','<?= htmlspecialchars($row['address'],ENT_QUOTES) ?>')">แก้ไข</button>
<button onclick="openOrdersModal(<?= $row['customer_id'] ?>,'<?= htmlspecialchars($row['full_name'],ENT_QUOTES) ?>')">ดูคำสั่งซื้อ</button>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="5">ไม่มีข้อมูลลูกค้า</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- Modal เพิ่ม -->
<div id="customer-modal" class="modal">
<div class="modal-content">
<span class="close-btn" onclick="closeModal()">&times;</span>
<h3>เพิ่มข้อมูลลูกค้า</h3>
<form method="POST">
<label>ชื่อ-นามสกุล :</label>
<input type="text" name="customer_name" required><br>
<label>เบอร์โทรศัพท์:</label>
<input type="tel" name="customer_phone" required><br>
<label>ที่อยู่ :</label>
<input type="text" name="customer_address" required><br>
<button type="submit">บันทึก</button>
</form>
</div>
</div>

<!-- Modal แก้ไข -->
<div id="edit-modal" class="modal">
<div class="modal-content">
<span class="close-btn" onclick="closeEditModal()">&times;</span>
<h3>แก้ไขข้อมูลลูกค้า</h3>
<form method="POST">
<input type="hidden" name="edit_id" id="edit-id">
<label>ชื่อ-นามสกุล :</label>
<input type="text" name="edit_name" id="edit-name" required><br>
<label>เบอร์โทรศัพท์:</label>
<input type="tel" name="edit_phone" id="edit-phone" required><br>
<label>ที่อยู่ :</label>
<input type="text" name="edit_address" id="edit-address" required><br>
<button type="submit">บันทึกการแก้ไข</button>
</form>
</div>
</div>

<!-- Modal คำสั่งซื้อ -->
<div id="orders-modal" class="modal">
<div class="modal-content">
<span class="close-btn" onclick="closeOrdersModal()">&times;</span>
<h3 id="orders-customer-name">คำสั่งซื้อของลูกค้า</h3>
<label>เดือน:</label>
<select id="filter-month">
<option value="">ทั้งหมด</option>
<?php for($m=1;$m<=12;$m++): ?>
<option value="<?= $m ?>"><?= $thai_months[$m-1] ?></option>
<?php endfor; ?>
</select>
<label>ปี:</label>
<select id="filter-year">
<option value="">ทั้งหมด</option>
<?php for($y=2023;$y<=2035;$y++): ?>
<option value="<?= $y ?>"><?= $y ?></option>
<?php endfor; ?>
</select>
<button onclick="loadOrders()">ค้นหา</button>
<table id="orders-table">
<thead>
<tr>
<th>ลำดับ</th>
<th>รหัสคำสั่งซื้อ</th>
<th>วันที่</th>
<th>สินค้า</th>
<th>จำนวน</th>
<th>ราคา</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>

<script src="../js/customers.js"></script>
</body>
</html>
