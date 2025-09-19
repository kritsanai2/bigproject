<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว


// ลบข้อมูลถ้ามี
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("UPDATE orders SET deleted = 1 WHERE order_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: orders.php");
    exit();
}

// เพิ่มข้อมูลถ้ามี
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['order_date']) && isset($_POST['customer_id']) && !isset($_POST['order_id'])) {
    $order_date = $_POST['order_date'];
    $total_price = 0; // ตั้งเป็น 0 เพราะราคาจะคำนวณจาก order_details
    $customer_id = (int)$_POST['customer_id'];

    $stmt = $conn->prepare("INSERT INTO orders (order_date, total_amount, customer_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sdi", $order_date, $total_price, $customer_id);
    $stmt->execute();

    // ดึง order_id ล่าสุดที่เพิ่ง insert
    $new_order_id = $conn->insert_id;

    $stmt->close();

    // ส่งไปหน้า order_details ของ order นั้นเลย
    header("Location: order_details.php?order_id=" . $new_order_id);
    exit();
}

// แก้ไขข้อมูลถ้ามี
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['order_id']) && isset($_POST['order_date']) && isset($_POST['customer_id'])) {
    $order_id = (int)$_POST['order_id'];
    $order_date = $_POST['order_date'];
    $total_price = 0; // ตั้งเป็น 0 เพราะราคาจะคำนวณจาก order_details
    $customer_id = (int)$_POST['customer_id'];

    $stmt = $conn->prepare("UPDATE orders SET order_date = ?, total_amount = ?, customer_id = ? WHERE order_id = ?");
    $stmt->bind_param("sdii", $order_date, $total_price, $customer_id, $order_id);
    $stmt->execute();
    $stmt->close();

    // อัปเดตเสร็จแล้วส่งไปหน้า order_details ของ order ที่แก้ไข
    header("Location: order_details.php?order_id=" . $order_id);
    exit();
}

// ดึงข้อมูลคำสั่งซื้อ
$sql = "SELECT order_id, order_date, total_amount, customer_id FROM orders WHERE deleted = 0 ORDER BY order_id DESC";
$result = $conn->query($sql);

// ดึงข้อมูลลูกค้า สำหรับ dropdown เพิ่มและแก้ไข
$customers = $conn->query("SELECT customer_id AS id, full_name AS name FROM customers WHERE deleted = 0 ORDER BY full_name ASC");
$customers2 = $conn->query("SELECT customer_id AS id, full_name AS name FROM customers WHERE deleted = 0 ORDER BY full_name ASC");

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>รายการคำสั่งซื้อ</title>
<link rel="stylesheet" href="../css/orders.css"/>
 
</head>
<body>
 <div class="stock-container">
  <header>
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
    <h1>ข้อมูลสั่งซื้อ</h1>
    <a href="index.php" class="home-button">หน้าหลัก</a>
  </header>

  <div class="search-row">
    <input type="text" id="search-order" class="search-box" placeholder="🔍 ค้นหาคำสั่งซื้อ" />
    <button class="search-btn" onclick="searchOrder()">🔍 ค้นหา</button>
    <button class="search-btn" onclick="openModal()">➕ เพิ่มข้อมูล</button>
</div>


  <table>
    <thead>
      <tr>
        <th>รหัสคำสั่งซื้อ</th>
        <th>วันที่สั่งซื้อ</th>
        <th>รหัสลูกค้า</th>
        <th>ราคา</th>
        <th>จัดการ</th>
      </tr>
    </thead>
    <tbody id="orders-tbody">
  <?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row["order_id"] ?></td>
        <td><?= $row["order_date"] ?></td>
        <td><?= $row["customer_id"] ?></td>
        <td><?= number_format($row["total_amount"], 2) ?></td>
        <td>
   <button type="submit" class="delete-btn">ลบ</button>
  <button class="edit-btn" onclick="openEditModal('<?= $row['order_id'] ?>', '<?= $row['order_date'] ?>', '<?= $row['customer_id'] ?>')">แก้ไข</button>
  <a href="order_details.php?order_id=<?= $row['order_id'] ?>" class="detail-btn">🔍 ดูรายละเอียด</a>
  <form method="POST" action="orders.php" style="display:inline;" onsubmit="return confirm('ยืนยันการลบ?');">
    <input type="hidden" name="delete_id" value="<?= $row['order_id'] ?>">
   
  </form>
</td>
      </tr>
    <?php endwhile; ?>
  <?php else: ?>
    <tr><td colspan="6">ไม่มีข้อมูลคำสั่งซื้อ</td></tr>
  <?php endif; ?>
</tbody>


  <!-- Modal เพิ่มคำสั่งซื้อ -->
  <div id="add-modal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeAddModal()">&times;</span>
      <h3>เพิ่มคำสั่งซื้อ</h3>
      <form method="POST" action="orders.php">
        <label>วันที่สั่งซื้อ:</label>
        <input type="date" name="order_date" required />
        <label>เลือกลูกค้า:</label>
        <select name="customer_id" required>
          <option value="">-- เลืกลูกค้า --</option>
          <?php while($cust = $customers->fetch_assoc()): ?>
            <option value="<?= $cust['id'] ?>"><?= $cust['id'] . " - " . htmlspecialchars($cust['name']) ?></option>
          <?php endwhile; ?>
        </select>
        <button type="submit">บันทึก</button>
      </form>
    </div>
  </div>

 <!-- Modal แก้ไขคำสั่งซื้อ -->
<div id="edit-modal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditModal()">&times;</span>
    <h3>แก้ไขคำสั่งซื้อ</h3>
    <form method="POST" action="orders.php">
      <input type="hidden" id="edit-id" name="order_id" />
      <label>วันที่สั่งซื้อ:</label>
      <input type="date" id="edit-order-date" name="order_date" required />
      <label>เลือกลูกค้า:</label>
      <select id="edit-customer-id" name="customer_id" required>
        <option value="">-- เลือกลูกค้า --</option>
        <?php while($cust = $customers2->fetch_assoc()): ?>
          <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?></option>
        <?php endwhile; ?>
      </select>
      <button type="submit">แก้ไข</button>
    </form>
  </div>
</div>

<script src="../js/orders.js"></script>

</body>
</html>
