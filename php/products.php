<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

// --- จัดการฟอร์ม ---

// ลบสินค้า (Soft Delete)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $conn->prepare("UPDATE products SET status = 0 WHERE product_id = ?");
    if (!$stmt) { die("Prepare failed: " . $conn->error); }
    
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'info', 'message' => 'ซ่อนข้อมูลสินค้าเรียบร้อย'];
    header("Location: products.php");
    exit();
}

// เพิ่มสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name']) && !isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("INSERT INTO products (product_type, product_name, product_size, unit, price, status) VALUES (?, ?, ?, ?, ?, 1)");
    if (!$stmt) { die("Prepare failed: " . $conn->error); }
    $stmt->bind_param("ssssd", $_POST['product_type'], $_POST['product_name'], $_POST['product_size'], $_POST['unit'], $_POST['price']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'เพิ่มสินค้าใหม่สำเร็จ'];
    header("Location: products.php");
    exit();
}

// แก้ไขสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("UPDATE products SET product_type=?, product_name=?, product_size=?, unit=?, price=? WHERE product_id=?");
    if (!$stmt) { die("Prepare failed: " . $conn->error); }
    $stmt->bind_param("ssssdi", $_POST['edit_type'], $_POST['edit_name'], $_POST['edit_size'], $_POST['edit_unit'], $_POST['edit_price'], $_POST['edit_id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลสินค้าเรียบร้อย'];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

        /* --- Global & Body Styles --- */
        :root {
            --primary-teal: #3498db;
            --dark-teal: #005f73;
            --light-teal-bg: #eaf6f6;
            --navy-blue: #001f3f;
            --gold-accent: #fca311;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --gray-border: #ced4da;
            --text-color: #495057;
            --danger: #e74c3c;
            --warning: #f39c12;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--light-teal-bg);
            margin: 0;
            padding: 20px;
            color: var(--text-color);
        }

        .container-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 30px 40px;
        }

        /* --- Header Section --- */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary-teal);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            width: 70px; height: 70px; border-radius: 50%;
            object-fit: cover; border: 3px solid var(--gold-accent);
        }
        header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem; color: var(--navy-blue);
            margin: 0; font-weight: 700;
        }
        .home-button {
            text-decoration: none; background-color: var(--primary-teal); color: var(--white);
            padding: 10px 25px; border-radius: 50px; font-weight: 500;
            transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0, 128, 128, 0.2);
            display: flex; align-items: center; gap: 8px;
        }
        .home-button:hover {
            background-color: var(--dark-teal); transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 95, 115, 0.3);
        }

        /* --- Search & Action Card --- */
        .content {
            background-color: var(--light-gray);
            padding: 25px; border-radius: 12px;
            margin-bottom: 30px;
        }
        .content h4 {
            font-size: 1.6rem; color: var(--dark-teal);
            margin-top: 0; margin-bottom: 20px;
        }
        .search-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .search-box {
            flex-grow: 1; padding: 12px 18px;
            border: 1px solid var(--gray-border); border-radius: 8px;
            font-size: 1rem; font-family: 'Sarabun', sans-serif;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .search-box:focus {
            border-color: var(--primary-teal); outline: none;
            box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.15);
        }
        
        /* --- Buttons in Search Row --- */
        .action-btn {
            padding: 12px 25px; border: none; border-radius: 8px;
            cursor: pointer; font-weight: 500; font-size: 1rem;
            transition: all 0.3s ease; display: inline-flex;
            align-items: center; gap: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .find-btn { background-color: var(--primary-teal); color: var(--white); }
        .find-btn:hover { background-color: var(--dark-teal); }
        .add-btn { background-color: #28a745; color: var(--white); }
        .add-btn:hover { background-color: #218838; }

        /* --- Table Styles --- */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background-color: var(--navy-blue); color: var(--white);
            padding: 15px; text-align: left; font-size: 1rem;
        }
        tbody td {
            padding: 15px; border-bottom: 1px solid #e0e0e0;
            color: #333;
        }
        tbody tr { transition: background-color 0.2s ease; }
        tbody tr:nth-child(even) { background-color: var(--light-gray); }
        tbody tr:hover { background-color: #d4eaf7; }
        th:first-child, td:first-child,
        th:nth-child(6), td:nth-child(6),
        th:last-child, td:last-child { text-align: center; }
        td:nth-child(6) { font-weight: 500; }
        
        .action-buttons { display: flex; gap: 8px; justify-content: center; }
        .action-buttons button {
            padding: 8px 12px; font-size: 0.9rem;
            color: white; border: none; border-radius: 4px; cursor: pointer;
            text-decoration: none; font-family: 'Sarabun', sans-serif; transition: all 0.2s;
        }
        .action-buttons button:hover { transform: translateY(-1px); }
        .edit-btn { background-color: var(--warning); color: #212529; }
        .edit-btn:hover { background-color: #e0a800; }
        .delete-btn { background-color: var(--danger); }
        .delete-btn:hover { background-color: #c82333; }
        
        /* --- MODAL STYLES --- */
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0, 31, 63, 0.6);
            backdrop-filter: blur(5px);
            justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: var(--white); margin: auto; padding: 30px 40px;
            border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 90%; max-width: 550px; position: relative;
            animation: fadeInScale 0.4s ease-out;
        }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        
        .close-btn {
            color: #aaa; position: absolute; top: 15px; right: 20px;
            font-size: 2rem; font-weight: bold; cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }
        .close-btn:hover { color: var(--danger); transform: rotate(90deg); }
        
        .modal h3 {
            font-size: 2rem; color: var(--dark-teal);
            text-align: center; margin-bottom: 25px;
        }
        
        .modal form { display: flex; flex-direction: column; gap: 5px; }
        .modal form label {
            display: block; margin-top: 10px; margin-bottom: 5px; font-weight: 500;
            color: var(--secondary-color);
        }
        .modal form input, .modal form select {
            width: 100%; padding: 12px;
            border-radius: 8px; border: 1px solid var(--gray-border);
            font-size: 1rem; font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }
        .modal form input:focus, .modal form select:focus {
            outline: none; border-color: var(--primary-teal);
            box-shadow: 0 0 8px rgba(0, 128, 128, 0.25);
        }
        .modal form button {
            width: 100%; padding: 12px; font-size: 1.1rem;
            margin-top: 20px; border: none; border-radius: 8px; cursor: pointer;
            color: white; font-weight: 500;
            background-color: var(--primary-teal);
            transition: background-color 0.3s, transform 0.2s;
        }
        .modal form button:hover { background-color: var(--dark-teal); transform: translateY(-2px); }
        
        #edit-modal form button { background-color: var(--warning); color: #212529; }
        #edit-modal form button:hover { background-color: #e0a800; }
    </style>
</head>
<body>
<div class="container-wrapper">
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h1><i class="fas fa-box-open"></i> จัดการข้อมูลสินค้า</h1>
        <a href="index.php" class="home-button"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
    </header>

    <div class="content">
        <h4><i class="fas fa-search"></i> ค้นหาและเพิ่มสินค้า</h4>
        <div class="search-row">
            <input type="text" id="search-input" class="search-box" placeholder="ค้นหาประเภท, ชื่อสินค้า..." onkeyup="searchTable()"/>
            <button class="action-btn find-btn" onclick="searchTable()"><i class="fas fa-search"></i> ค้นหา</button>
            <button class="action-btn add-btn" onclick="openModal('product-modal')"><i class="fas fa-plus-circle"></i> เพิ่มสินค้าใหม่</button>
        </div>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ประเภท</th>
                    <th>ชื่อสินค้า</th>
                    <th>ขนาด</th>
                    <th>หน่วย</th>
                    <th>ราคา (บาท)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody id="product-tbody">
            <?php
            $sql = "SELECT * FROM products WHERE status = 1 ORDER BY product_id DESC";
            $result = $conn->query($sql);
            if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_id']) ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($row['product_type']) ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['product_size']) ?></td>
                    <td><?= htmlspecialchars($row['unit']) ?></td>
                    <td><?= number_format($row['price'], 2) ?></td>
                    <td>
                        <div class="action-buttons">
                           <button class="edit-btn"
                                onclick="openEditModal(
                                    '<?= $row['product_id'] ?>',
                                    '<?= htmlspecialchars($row['product_type'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['product_name'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['product_size'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['unit'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['price'], ENT_QUOTES) ?>'
                                )">แก้ไข</button>
                            <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0;">
                                <input type="hidden" name="delete_id" value="<?= $row['product_id'] ?>">
                                <button type="submit" class="delete-btn">ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" style="padding: 2rem; text-align:center;">ไม่พบข้อมูลสินค้า</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="product-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('product-modal')">&times;</span>
        <h3><i class="fas fa-plus-circle"></i> เพิ่มสินค้าใหม่</h3>
        <form method="POST">
            <label for="product_type">ประเภทสินค้า</label>
            <input type="text" id="product_type" name="product_type" placeholder="เช่น น้ำดื่มถัง, น้ำดื่มแพ็ค" required>
            <label for="product_name">ชื่อสินค้า</label>
            <input type="text" id="product_name" name="product_name" placeholder="เช่น น้ำดื่มถัง, น้ำดื่มแพ็ค" required>
            <label for="product_size">ขนาดสินค้า</label>
            <input type="text" id="product_size" name="product_size" placeholder="เช่น 18.9 ลิตร, 600 มล.">
            <label for="unit">หน่วยนับ</label>
            <input type="text" id="unit" name="unit" placeholder="เช่น ถัง, แพ็ค" required>
            <label for="price">ราคา</label>
            <input type="number" step="0.01" id="price" name="price" placeholder="0.00" required>
            <button type="submit"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
        </form>
    </div>
</div>

<div id="edit-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('edit-modal')">&times;</span>
        <h3><i class="fas fa-edit"></i> แก้ไขข้อมูลสินค้า</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit-id">
            <label for="edit-type">ประเภทสินค้า</label>
            <input type="text" name="edit_type" id="edit-type" required>
            <label for="edit-name">ชื่อสินค้า</label>
            <input type="text" name="edit_name" id="edit-name" required>
            <label for="edit-size">ขนาดสินค้า</label>
            <input type="text" name="edit_size" id="edit-size">
            <label for="edit-unit">หน่วยนับ</label>
            <input type="text" name="edit_unit" id="edit-unit" required>
            <label for="edit-price">ราคา</label>
            <input type="number" step="0.01" name="edit_price" id="edit-price" required>
            <button type="submit"><i class="fas fa-sync-alt"></i> บันทึกการแก้ไข</button>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
    function openEditModal(id, type, name, size, unit, price) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-type').value = type;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-size').value = size;
        document.getElementById('edit-unit').value = unit;
        document.getElementById('edit-price').value = price;
        openModal('edit-modal');
    }
    function confirmDelete(event, form) {
        event.preventDefault(); 
        Swal.fire({
            title: 'ยืนยันการดำเนินการ',
            text: "สินค้าจะถูกซ่อนจากรายการ แต่ประวัติสต็อกจะยังคงอยู่",
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
    function searchTable() {
        const input = document.getElementById('search-input');
        const filter = input.value.toUpperCase();
        const tableBody = document.getElementById('product-tbody');
        const tr = tableBody.getElementsByTagName('tr');
        for (let i = 0; i < tr.length; i++) {
            let tdType = tr[i].getElementsByTagName('td')[1];
            let tdName = tr[i].getElementsByTagName('td')[2];
            if (tdType || tdName) {
                let txtValue = (tdType.textContent || tdType.innerText) + " " + (tdName.textContent || tdName.innerText);
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
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