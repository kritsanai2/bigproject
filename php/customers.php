<?php
session_start();
require_once "db.php";
require_once __DIR__ . '/includes/auth.php';

// --- API Endpoint สำหรับดึงข้อมูลคำสั่งซื้อ (สำหรับ Modal) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_orders') {
    header('Content-Type: application/json');
    $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

    $sql = "SELECT 
                o.order_id, o.order_date, p.product_name, od.quantity, od.price
            FROM orders o
            JOIN order_details od ON o.order_id = od.order_id
            JOIN products p ON od.product_id = p.product_id
            WHERE o.customer_id = ?";
    
    $params = ["i", $customer_id];
    
    if ($month > 0) {
        $sql .= " AND MONTH(o.order_date) = ?";
        $params[0] .= "i";
        $params[] = $month;
    }
    if ($year > 0) {
        $sql .= " AND YEAR(o.order_date) = ?";
        $params[0] .= "i";
        $params[] = $year;
    }
    $sql .= " ORDER BY o.order_date DESC, o.order_id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode($orders);
    exit();
}

// --- จัดการฟอร์ม ---

// เพิ่มลูกค้า
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['customer_name'])) {
    $stmt = $conn->prepare("INSERT INTO customers (full_name, phone, address, status) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $_POST['customer_name'], $_POST['customer_phone'], $_POST['customer_address']);
    $stmt->execute();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'เพิ่มข้อมูลลูกค้าสำเร็จ'];
    header("Location: customers.php"); exit();
}

// ลบลูกค้า (ย้ายไปสถานะไม่ใช้งาน)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $stmt = $conn->prepare("UPDATE customers SET status = 0 WHERE customer_id = ?");
    $stmt->bind_param("i", $_POST['delete_id']);
    if($stmt->execute()){
        $_SESSION['alert'] = ['type' => 'info', 'message' => 'ย้ายลูกค้าไปสถานะไม่ใช้งานแล้ว'];
    }
    header("Location: customers.php"); exit();
}
// แก้ไขลูกค้า
if (isset($_POST['edit_id'])) {
    $stmt = $conn->prepare("UPDATE customers SET full_name=?, phone=?, address=? WHERE customer_id=?");
    $stmt->bind_param("sssi", $_POST['edit_name'], $_POST['edit_phone'], $_POST['edit_address'], $_POST['edit_id']);
    $stmt->execute();
     $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลเรียบร้อย'];
    header("Location: customers.php"); exit();
}

// ดึงข้อมูลลูกค้าที่ใช้งานอยู่
$sql = "SELECT customer_id, full_name, phone, address FROM customers WHERE status=1 ORDER BY customer_id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>ข้อมูลลูกค้า</title>
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
            --secondary-color: #2c3e50; /* Added for consistency */
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #9b59b6;
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
        
        /* --- Buttons --- */
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
        th:first-child, td:first-child { text-align: center; }
        
        .delete-btn, .edit-btn, .view-btn {
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .delete-btn { background-color: var(--danger); }
        .edit-btn { background-color: var(--warning); color: var(--secondary-color); }
        .view-btn { background-color: var(--info); }
        .delete-btn:hover { background-color: #c0392b; transform: translateY(-1px); }
        .edit-btn:hover { background-color: #e0a800; transform: translateY(-1px); }
        .view-btn:hover { background-color: #8e44ad; transform: translateY(-1px); }
        
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
        
        .modal-lg .modal-content { max-width: 900px; }
        
        .close-btn {
            color: #aaa; position: absolute; top: 15px; right: 20px;
            font-size: 2rem; font-weight: bold; cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }
        .close-btn:hover { color: #dc3545; transform: rotate(90deg); }
        
        .modal h3 {
            font-size: 2rem; color: var(--dark-teal);
            text-align: center; margin-bottom: 25px;
        }
        
        .modal form label {
            display: block; margin-bottom: 8px; font-weight: 500;
            color: var(--secondary-color);
        }
        .modal form input, .modal form textarea, .modal form select {
            width: 100%; padding: 12px; margin-bottom: 20px;
            border-radius: 8px; border: 1px solid var(--gray-border);
            font-size: 1rem; font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }
        .modal form input:focus, .modal form textarea:focus, .modal form select:focus {
            outline: none; border-color: var(--primary-teal);
            box-shadow: 0 0 8px rgba(0, 128, 128, 0.25);
        }
        .modal form button {
            width: 100%; padding: 12px; font-size: 1.1rem;
            border: none; border-radius: 8px; cursor: pointer;
            color: white; font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
        }
        .modal form button:hover { transform: translateY(-2px); }
        
        /* Modal Button Specifics */
        #customer-modal form button { background-color: var(--primary-teal); }
        #customer-modal form button:hover { background-color: var(--dark-teal); }
        
        /* **แก้ไขแล้ว: เพิ่มสีตัวอักษรให้ปุ่มแก้ไข** */
        #edit-modal form button { 
            background-color: var(--warning); 
            color: var(--secondary-color); /* << เพิ่มบรรทัดนี้ */
        }
        #edit-modal form button:hover { background-color: #e0a800; }
        
        #orders-modal .filter-controls {
            display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;
            background-color: var(--light-gray); padding: 1rem;
            border-radius: 8px; margin-bottom: 1.5rem;
        }
        #orders-modal .filter-controls label { margin-bottom: 0; }
        #orders-modal .filter-controls select, #orders-modal .filter-controls button {
            padding: 0.6rem; border: 1px solid var(--gray-border);
            border-radius: 8px; font-size: 0.9rem;
        }
        #orders-modal .filter-controls button {
            background-color: var(--primary-teal); color: white; cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container-wrapper">
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h1><i class="fas fa-users-cog"></i> จัดการข้อมูลลูกค้า</h1>
        <a href="index.php" class="home-button"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
    </header>

    <div class="content">
        <h4><i class="fas fa-search"></i> ค้นหาและเพิ่มลูกค้า</h4>
        <div class="search-row">
            <input type="text" id="search-customer" class="search-box" placeholder="ค้นหาด้วยชื่อ, เบอร์โทร, หรือที่อยู่..." onkeyup="searchCustomer()"/>
            <button class="action-btn find-btn" onclick="searchCustomer()"><i class="fas fa-search"></i> ค้นหา</button>
            <button class="action-btn add-btn" onclick="openModal('customer-modal')"><i class="fas fa-user-plus"></i> เพิ่มลูกค้าใหม่</button>
        </div>
    </div>
    
    <div class="table-wrapper">
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
            <tbody id="customer-tbody">
            <?php if ($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['customer_id'] ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($row['address']) ?></td>
                    <td style="display:flex; flex-wrap: wrap; gap:5px; justify-content: center;">
                        <form method="POST" onsubmit="confirmDelete(event, this)">
                            <input type="hidden" name="delete_id" value="<?= $row['customer_id'] ?>">
                            <button type="submit" class="delete-btn">ลบ</button>
                        </form>
                        <button class="edit-btn" onclick="openEditModal(<?= $row['customer_id'] ?>,'<?= htmlspecialchars($row['full_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($row['phone'],ENT_QUOTES) ?>','<?= htmlspecialchars($row['address'],ENT_QUOTES) ?>')">แก้ไข</button>
                        <button class="view-btn" onclick="openOrdersModal(<?= $row['customer_id'] ?>,'<?= htmlspecialchars($row['full_name'],ENT_QUOTES) ?>')">ดูคำสั่งซื้อ</button>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" style="padding: 2rem; text-align:center;">ไม่พบข้อมูลลูกค้า</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="customer-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('customer-modal')">&times;</span>
        <h3><i class="fas fa-user-plus"></i> เพิ่มข้อมูลลูกค้าใหม่</h3>
        <form method="POST">
            <label>ชื่อ-นามสกุล:</label>
            <input type="text" name="customer_name" required>
            <label>เบอร์โทรศัพท์:</label>
            <input type="tel" name="customer_phone">
            <label>ที่อยู่:</label>
            <textarea name="customer_address" rows="3"></textarea>
            <button type="submit"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
        </form>
    </div>
</div>

<div id="edit-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('edit-modal')">&times;</span>
        <h3><i class="fas fa-user-edit"></i> แก้ไขข้อมูลลูกค้า</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit-id">
            <label>ชื่อ-นามสกุล:</label> <input type="text" name="edit_name" id="edit-name" required>
            <label>เบอร์โทรศัพท์:</label> <input type="tel" name="edit_phone" id="edit-phone">
            <label>ที่อยู่:</label> <textarea name="edit_address" id="edit-address" rows="3"></textarea>
            <button type="submit"><i class="fas fa-sync-alt"></i> บันทึกการแก้ไข</button>
        </form>
    </div>
</div>

<div id="orders-modal" class="modal modal-lg">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('orders-modal')">&times;</span>
        <h3 id="orders-customer-name">ประวัติคำสั่งซื้อ</h3>
        <div class="filter-controls">
            <input type="hidden" id="current-customer-id">
            <label>เดือน:</label>
<select id="filter-month">
    <option value="0">ทั้งหมด</option>
    <?php 
    $thai_months = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"]; 
    for($m=1; $m<=12; $m++): 
    ?>
    <option value="<?= $m ?>"><?= $thai_months[$m-1] ?></option>
    <?php endfor; ?>
</select>

<label>ปี (พ.ศ.):</label>
<label>ปี (พ.ศ.):</label>
<select id="filter-year">
    <option value="0">ทั้งหมด</option>
    <?php 
    // วนลูปตั้งแต่ปี ค.ศ. 2022 ถึง 2035
    for($y = 2022; $y <= 2035; $y++): 
    ?>
    <option value="<?= $y ?>"><?= $y + 543 ?></option>
    <?php endfor; ?>
</select>
    <button onclick="loadOrders()"><i class="fas fa-filter"></i> กรองข้อมูล</button>
        </div>
        <div class="table-wrapper"><table id="orders-table"><thead><tr>
            <th>รหัสสั่งซื้อ</th>
            <th>วันที่</th>
            <th>สินค้า</th>
            <th>จำนวน</th>
            <th>ราคา/หน่วย</th>
            <th>ราคารวม</th></tr>
        </thead><tbody>

        </tbody></table>
    </div>
    </div>
</div>

<?php if(isset($_SESSION['alert'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['message'] ?>',
        showConfirmButton: false,
        timer: 1800,
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
</script>
<?php 
    unset($_SESSION['alert']);
    endif; 
?>

<script>
function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
function openEditModal(id, name, phone, address) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-phone').value = phone;
    document.getElementById('edit-address').value = address;
    openModal('edit-modal');
}
function openOrdersModal(customerId, customerName) {
    document.getElementById('current-customer-id').value = customerId;
    document.getElementById('orders-customer-name').innerHTML = `<i class="fas fa-user-circle"></i> ประวัติของ: ${customerName}`;
    loadOrders();
    openModal('orders-modal');
}
function confirmDelete(event, form) {
    event.preventDefault(); 
    Swal.fire({
        title: 'ยืนยันการดำเนินการ',
        text: 'ลูกค้าจะถูกย้ายไปอยู่ในสถานะ "ไม่ใช้งาน"',
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
function searchCustomer() {
    const input = document.getElementById('search-customer');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('customer-tbody');
    const tr = table.getElementsByTagName('tr');
    for (let i = 0; i < tr.length; i++) {
        let tdName = tr[i].getElementsByTagName("td")[1];
        let tdPhone = tr[i].getElementsByTagName("td")[2];
        let tdAddress = tr[i].getElementsByTagName("td")[3];
        if (tdName || tdPhone || tdAddress) {
            const txtValue = (tdName.textContent || tdName.innerText) + 
                             (tdPhone.textContent || tdPhone.innerText) + 
                             (tdAddress.textContent || tdAddress.innerText);

            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}
function loadOrders() {
    const customerId = document.getElementById('current-customer-id').value;
    const month = document.getElementById('filter-month').value;
    const year = document.getElementById('filter-year').value;
    const tableBody = document.querySelector("#orders-table tbody");
    const url = `customers.php?action=get_orders&customer_id=${customerId}&month=${month}&year=${year}`;
    tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">กำลังโหลดข้อมูล...</td></tr>';
    fetch(url)
        .then(response => response.json())
        .then(data => {
            tableBody.innerHTML = '';
            if (data.length > 0) {
                data.forEach(order => {
                    const orderDate = new Date(order.order_date);
                    const formattedDate = `${orderDate.getDate()}/${orderDate.getMonth() + 1}/${orderDate.getFullYear() + 543}`;
                    const row = `<tr>
                        <td style="text-align:center;">${order.order_id}</td>
                        <td>${formattedDate}</td>
                        <td>${order.product_name}</td>
                        <td style="text-align:center;">${order.quantity}</td>
                        <td style="text-align:right;">${parseFloat(order.price).toFixed(2)}</td>
                        <td style="text-align:right;">${(order.quantity * order.price).toFixed(2)}</td>
                    </tr>`;
                    tableBody.innerHTML += row;
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">ไม่พบข้อมูลคำสั่งซื้อ</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tableBody.innerHTML = '<tr><td colspan="6" style="color:red; text-align:center;">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
        });
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = "none";
    }
}
</script>
</body>
</html>