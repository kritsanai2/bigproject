<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

// ลบสินค้า (Soft Delete)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    $stmt = $conn->prepare("UPDATE products SET deleted = 1 WHERE product_id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: products.php");
        exit();
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการลบสินค้า');</script>";
    }
}

// เพิ่มสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name']) && !isset($_POST['edit_id']) && !isset($_POST['delete_id'])) {
    $stmt = $conn->prepare("INSERT INTO products (product_type, product_name, product_size, unit, price, deleted) VALUES (?, ?, ?, ?, ?, 0)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ssssd", $_POST['product_type'], $_POST['product_name'], $_POST['product_size'], $_POST['unit'], $_POST['price']);
    $stmt->execute();
    $stmt->close();
    header("Location: products.php");
    exit();
}

// แก้ไขสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("UPDATE products SET product_type=?, product_name=?, product_size=?, unit=?, price=? WHERE product_id=?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ssssdi", $_POST['edit_type'], $_POST['edit_name'], $_POST['edit_size'], $_POST['edit_unit'], $_POST['edit_price'], $_POST['edit_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: products.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ข้อมูลสินค้า</title>
<link rel="stylesheet" href="../css/products.css"/>

</head>
<body>
  <div class="stock-container">
  <header>
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
    <h1>ข้อมูลสินค้า</h1>
    <a href="index.php" class="home-button">🏠 หน้าหลัก </a>
</header>

  <div class="container">
    <h4>ค้นหาข้อมูลสินค้า</h4>
    <div class="search-row">
    <input type="text" id="search-customer" class="search-box" placeholder="🔍 ค้นหา" />
    <button class="search-btn" onclick="searchCustomer()">  🔍  ค้นหา</button>
    <button class="search-btn" onclick="openModal()">  ➕  เพิ่มข้อมูลลูกค้า </button>
  </div>
    <table>
      <thead>
        <tr>
          <th>รหัส</th>
          <th>ประเภท</th>
          <th>ชื่อสินค้า</th>
          <th>ขนาด</th>
          <th>หน่วย</th>
          <th>ราคา</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sql = "SELECT * FROM products WHERE deleted = 0";
        $result = $conn->query($sql);
        if ($result->num_rows > 0):
          while ($row = $result->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($row['product_id']) ?></td>
          <td><?= htmlspecialchars($row['product_type']) ?></td>
          <td><?= htmlspecialchars($row['product_name']) ?></td>
          <td><?= htmlspecialchars($row['product_size']) ?></td>
          <td><?= htmlspecialchars($row['unit']) ?></td>
          <td><?= htmlspecialchars($row['price']) ?></td>
          <td style="display:flex; gap:5px;">
            <form method="POST" onsubmit="return confirm('แน่ใจว่าต้องการลบสินค้านี้?')">
              <input type="hidden" name="delete_id" value="<?= $row['product_id'] ?>">
              <button type="submit" class="delete-btn">ลบ</button>
            </form>
            <button class="edit-btn"
              onclick="openEditModal(
                '<?= $row['product_id'] ?>',
                '<?= htmlspecialchars($row['product_type']) ?>',
                '<?= htmlspecialchars($row['product_name']) ?>',
                '<?= htmlspecialchars($row['product_size']) ?>',
                '<?= htmlspecialchars($row['unit']) ?>',
                '<?= htmlspecialchars($row['price']) ?>'
              )">แก้ไข</button>
          </td>
        </tr>
        <?php
          endwhile;
        else:
        ?>
        <tr><td colspan="7">ไม่มีข้อมูลสินค้า</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal เพิ่ม -->
  <div id="product-modal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal()">&times;</span>
      <h3>เพิ่มสินค้า</h3>
      <form method="POST">
        <input type="text" name="product_type" placeholder="ประเภทสินค้า" required><br>
        <input type="text" name="product_name" placeholder="ชื่อสินค้า" required><br>
        <input type="text" name="product_size" placeholder="ขนาดสินค้า" required><br>
        <input type="text" name="unit" placeholder="หน่วยนับ" required><br>
        <input type="number" step="0.01" name="price" placeholder="ราคา" required><br><br>
        <button type="submit">บันทึก</button>
      </form>
    </div>
  </div>

  <!-- Modal แก้ไข -->
  <div id="edit-modal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeEditModal()">&times;</span>
      <h3>แก้ไขสินค้า</h3>
      <form method="POST">
        <input type="hidden" name="edit_id" id="edit-id">
        <input type="text" name="edit_type" id="edit-type" placeholder="ประเภทสินค้า" required><br>
        <input type="text" name="edit_name" id="edit-name" placeholder="ชื่อสินค้า" required><br>
        <input type="text" name="edit_size" id="edit-size" placeholder="ขนาดสินค้า" required><br>
        <input type="text" name="edit_unit" id="edit-unit" placeholder="หน่วยนับ" required><br>
        <input type="number" step="0.01" name="edit_price" id="edit-price" placeholder="ราคา" required><br><br>
        <button type="submit">บันทึกการแก้ไข</button>
      </form>
    </div>
  </div>

<script src="../js/products.js"></script>
</body>
</html>

<?php
$conn->close();
?>
