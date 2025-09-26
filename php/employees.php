<?php
session_start();
require_once "db.php";

// ================== เพิ่มข้อมูล ==================
if(isset($_POST['action']) && $_POST['action']=='add'){
    $stmt = $conn->prepare("INSERT INTO employees (employee_id, full_name, position, phone, status) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("ssss", $_POST['employee_id_input'], $_POST['full_name'], $_POST['position'], $_POST['phone']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'เพิ่มข้อมูลพนักงานสำเร็จ'];
    header("Location: ".$_SERVER['PHP_SELF']); exit();
}

// ================== แก้ไขข้อมูล ==================
if(isset($_POST['action']) && $_POST['action']=='edit'){
    $stmt = $conn->prepare("UPDATE employees SET full_name=?, position=?, phone=? WHERE employee_id=?");
    $stmt->bind_param("ssss", $_POST['full_name'], $_POST['position'], $_POST['phone'], $_POST['employee_id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลพนักงานเรียบร้อย'];
    header("Location: ".$_SERVER['PHP_SELF']); exit();
}

// ================== ลบแบบ soft delete ==================
if(isset($_POST['action']) && $_POST['action']=='delete'){
    $stmt = $conn->prepare("UPDATE employees SET status=0 WHERE employee_id=?");
    $stmt->bind_param("s", $_POST['employee_id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'info', 'message' => 'ย้ายพนักงานไปสถานะไม่ใช้งานแล้ว'];
    header("Location: ".$_SERVER['PHP_SELF']); exit();
}

// ================== ดึงข้อมูล ==================
$result = $conn->query("SELECT * FROM employees WHERE status=1 ORDER BY employee_id ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>ข้อมูลพนักงาน</title>
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
        padding: 20px;
    }

    .container-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        background: var(--white);
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        padding: 30px 40px;
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 20px;
        margin-bottom: 30px;
        gap: 1rem;
    }
    .logo {
        width: 70px; height: 70px; border-radius: 50%;
        object-fit: cover; border: 3px solid var(--gold-accent);
    }
    header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem; color: var(--navy-blue);
        margin: 0; font-weight: 700;
        display: flex; align-items: center; gap: 1rem;
    }
    .header-buttons {
        display: flex;
        gap: 1rem;
    }
    .home-button {
        text-decoration: none; background-color: var(--primary-color); color: var(--white);
        padding: 10px 25px; border-radius: 50px; font-weight: 500;
        transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(52, 152, 219, 0.2);
        display: flex; align-items: center; gap: 8px;
    }
    .home-button:hover {
        background-color: #2980b9; transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
    }
    .container {
        background-color: var(--light-gray);
        padding: 25px; border-radius: 12px;
        margin-bottom: 30px;
    }
    .search-row { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
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
    .add-btn { background-color: var(--success); }
    .add-btn:hover { background-color: #27ae60; }
    
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
    
    .action-buttons { display: flex; gap: 0.5rem; justify-content: flex-start; }
    .action-buttons button, .action-buttons a {
        padding: 8px 12px; font-size: 0.9rem;
        color: white; border: none; border-radius: 4px; cursor: pointer;
        text-decoration: none; font-family: 'Sarabun', sans-serif; transition: all 0.2s;
    }
    .action-buttons button:hover, .action-buttons a:hover { transform: translateY(-1px); }
    .edit-btn { background-color: var(--warning); color: #212529; }
    .edit-btn:hover { background-color: #e0a800; }
    .delete-btn { background-color: var(--danger); }
    .delete-btn:hover { background-color: #c0392b; }

    .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 31, 63, 0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
    .modal-content { background-color: var(--white); margin: auto; padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 550px; position: relative; animation: fadeInScale 0.4s ease-out; }
    @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    .close-btn { color: #aaa; position: absolute; top: 15px; right: 20px; font-size: 2rem; font-weight: bold; cursor: pointer; transition: color 0.2s, transform 0.2s; }
    .close-btn:hover { color: var(--danger); transform: rotate(90deg); }
    .modal h2 { font-size: 2rem; color: var(--navy-blue); text-align: center; margin-bottom: 25px; border:none; }
    .modal form { display: flex; flex-direction: column; gap: 5px; }
    .modal form label { display: block; margin-top: 10px; margin-bottom: 5px; font-weight: 500; color: var(--secondary-color); }
    .modal form input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; font-family: 'Sarabun', sans-serif; transition: all 0.3s; }
    .modal form input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 8px rgba(52, 152, 219, 0.25); }
    .modal form button { width: 100%; padding: 12px; font-size: 1.1rem; margin-top: 20px; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: 500; transition: background-color 0.3s, transform 0.2s; }
    
    #addModal button { background-color: var(--success); }
    #addModal button:hover { background-color: #27ae60; }
    #editModal button { background-color: var(--warning); color:#212529; }
    #editModal button:hover { background-color: #e67e22; }
</style>
</head>
<body>

<div class="container-wrapper">
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h1><i class="fas fa-id-card-alt"></i> ข้อมูลพนักงาน</h1>
        <div class="header-buttons">
            <a href="index.php" class="home-button"><i class="fas fa-home"></i> หน้าหลัก</a>
            <a href="attendances.php" class="home-button"><i class="fas fa-user-check"></i> เช็คชื่อ</a>
        </div>
    </header>

    <div class="container">
        <div class="search-row">
            <input type="text" id="search-employee" class="search-box" placeholder="ค้นหาด้วยรหัส, ชื่อ, ตำแหน่ง..." onkeyup="searchEmployee()"/>
            <button class="action-btn find-btn" onclick="searchEmployee()"><i class="fas fa-search"></i> ค้นหา</button>
            <button class="action-btn add-btn" onclick="openAddModal()"><i class="fas fa-user-plus"></i> เพิ่มข้อมูล</button>
        </div>
    </div>
    
    <div class="table-wrapper">
        <table id="employeesTable">
            <thead>
                <tr>
                    <th>รหัสพนักงาน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ตำแหน่ง</th>
                    <th>โทรศัพท์</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td style="text-align:center;"><?=htmlspecialchars($row['employee_id'])?></td>
                    <td><?=htmlspecialchars($row['full_name'])?></td>
                    <td><?=htmlspecialchars($row['position'])?></td>
                    <td style="text-align:center;"><?=htmlspecialchars($row['phone'])?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="edit-btn" 
                                onclick="openEditModal(
                                    '<?= htmlspecialchars($row['employee_id'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['position'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['phone'], ENT_QUOTES) ?>'
                                )">แก้ไข</button>
                            <form method="POST" onsubmit="confirmDelete(event, this)" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['employee_id']) ?>">
                                <button type="submit" class="delete-btn">ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" style="text-align: center; padding:2rem;">ไม่มีข้อมูลพนักงาน</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('addModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> เพิ่มพนักงานใหม่</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <label for="employee_id_input">รหัสพนักงาน</label>
            <input type="text" id="employee_id_input" name="employee_id_input" placeholder="เช่น MP-0004" required>
            
            <label for="full_name">ชื่อ-นามสกุล</label>
            <input type="text" id="full_name" name="full_name" required>
            
            <label for="position">ตำแหน่ง</label>
            <input type="text" id="position" name="position" required>
            
            <label for="phone">เบอร์โทรศัพท์</label>
            <input type="text" id="phone" name="phone">
            
            <button type="submit"><i class="fas fa-save"></i> บันทึก</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
        <h2><i class="fas fa-user-edit"></i> แก้ไขข้อมูลพนักงาน</h2>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="employee_id" id="editEmployeeId">
            
            <label for="editFullName">ชื่อ-นามสกุล:</label>
            <input type="text" name="full_name" id="editFullName" required>
            
            <label for="editPosition">ตำแหน่ง:</label>
            <input type="text" name="position" id="editPosition" required>
            
            <label for="editPhone">เบอร์โทรศัพท์:</label>
            <input type="text" name="phone" id="editPhone">
            
            <button type="submit"><i class="fas fa-sync-alt"></i> บันทึกการแก้ไข</button>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

    function openAddModal() { openModal('addModal'); }
    
    function openEditModal(id, fullName, position, phone) {
        document.getElementById('editEmployeeId').value = id;
        document.getElementById('editFullName').value = fullName;
        document.getElementById('editPosition').value = position;
        document.getElementById('editPhone').value = phone;
        openModal('editModal');
    }

    function confirmDelete(event, form) {
        event.preventDefault(); 
        Swal.fire({
            title: 'ยืนยันการดำเนินการ',
            text: "พนักงานจะถูกย้ายไปสถานะ 'ไม่ใช้งาน' และจะไม่แสดงในหน้านี้",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    function searchEmployee() {
        const input = document.getElementById('search-employee');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('employeesTable');
        const tr = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        for (let i = 0; i < tr.length; i++) {
            let rowText = tr[i].textContent || tr[i].innerText;
            if (rowText.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
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