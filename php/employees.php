<?php
session_start();
require_once "db.php";

// ================== เพิ่มข้อมูล ==================
if(isset($_POST['action']) && $_POST['action']=='add'){
    $stmt = $conn->prepare("INSERT INTO employees (employee_id, full_name, position, phone) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $_POST['employee_id_input'], $_POST['full_name'], $_POST['position'], $_POST['phone']);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']); exit();
}

// ================== แก้ไขข้อมูล ==================
if(isset($_POST['action']) && $_POST['action']=='edit'){
    $stmt = $conn->prepare("UPDATE employees SET full_name=?, position=?, phone=? WHERE employee_id=?");
    $stmt->bind_param("ssss", $_POST['full_name'], $_POST['position'], $_POST['phone'], $_POST['employee_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']); exit();
}

// ================== ลบแบบ soft delete ==================
if(isset($_GET['delete'])){
    $stmt = $conn->prepare("UPDATE employees SET deleted=1 WHERE employee_id=?");
    $stmt->bind_param("s", $_GET['delete']);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']); exit();
}

// ================== ดึงข้อมูล ==================
$result = $conn->query("SELECT * FROM employees WHERE deleted=0 ORDER BY employee_id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>รายชื่อพนักงาน</title>
<link rel="stylesheet" href="../css/employees.css"/>
</head>
<body>

<div class="stock-container">
<header>
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h1>ข้อมูลรายชื่อพนักงาน</h1>
  <a href="index.php" class="home-button">หน้าหลัก</a>
  <a href="attendances.php" class="home-button">เช็คชื่อ</a>
</header>

<div class="container">
  <div class="search-row">
      <input type="text" id="search-employee" class="search-box" placeholder="🔍 ค้นหาพนักงาน" />
      <button class="search-btn" onclick="searchEmployee()">ค้นหา</button>
      <button class="search-btn" onclick="openAddModal()">➕ เพิ่มข้อมูล</button>
  </div>

  <table id="employeesTable">
    <thead>
      <tr>
        <th>รหัสพนักงาน</th>
        <th>ชื่อเต็ม</th>
        <th>ตำแหน่ง</th>
        <th>โทรศัพท์</th>
        <th>การจัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()) : ?>
      <tr>
        <td><?=htmlspecialchars($row['employee_id'])?></td>
        <td><?=htmlspecialchars($row['full_name'])?></td>
        <td><?=htmlspecialchars($row['position'])?></td>
        <td><?=htmlspecialchars($row['phone'])?></td>
       <td>
  <button class="delete-btn" onclick="confirmDelete('<?= htmlspecialchars($row['employee_id']) ?>')">ลบ</button>
  <button class="edit-btn" 
    onclick="openEditModal(
      '<?= htmlspecialchars(addslashes($row['employee_id'])) ?>',
      '<?= htmlspecialchars(addslashes($row['full_name'])) ?>',
      '<?= htmlspecialchars(addslashes($row['position'])) ?>',
      '<?= htmlspecialchars(addslashes($row['phone'])) ?>'
    )"
  >แก้ไข</button>
</td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div id="addModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAddModal()">&times;</span>
    <h2>เพิ่มพนักงาน</h2>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="text" name="employee_id_input" placeholder="รหัสพนักงาน" required>
      <input type="text" name="full_name" placeholder="ชื่อเต็ม" required>
      <input type="text" name="position" placeholder="ตำแหน่ง" required>
      <input type="text" name="phone" placeholder="โทรศัพท์" required>
      <button type="submit">บันทึก</button>
    </form>
  </div>
</div>

<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditModal()">&times;</span>
    <h2>แก้ไขพนักงาน</h2>
    <form method="post">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="employee_id" id="editEmployeeId">
      <input type="text" name="full_name" id="editFullName" placeholder="ชื่อเต็ม" required>
      <input type="text" name="position" id="editPosition" placeholder="ตำแหน่ง" required>
      <input type="text" name="phone" id="editPhone" placeholder="โทรศัพท์" required>
      <button type="submit">บันทึก</button>
    </form>
  </div>
</div>

<script src="../js/employees.js"></script>

</body>
</html>
