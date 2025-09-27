<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

// --- จัดการการลบข้อมูล ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    
    $conn->begin_transaction();
    try {
        // เพิ่มการลบ transactions ที่เกี่ยวข้อง
        $subquery = "SELECT order_detail_id FROM order_details WHERE order_id = ?";
        $stmt_trans = $conn->prepare("DELETE FROM transactions WHERE order_detail_id IN ($subquery)");
        $stmt_trans->bind_param("i", $delete_id);
        $stmt_trans->execute();
        $stmt_trans->close();

        // ลบ stock
        $stmt_stock = $conn->prepare("DELETE FROM stock WHERE order_id = ?");
        $stmt_stock->bind_param("i", $delete_id);
        $stmt_stock->execute();
        $stmt_stock->close();

        // ลบ order_details
        $stmt_details = $conn->prepare("DELETE FROM order_details WHERE order_id = ?");
        $stmt_details->bind_param("i", $delete_id);
        $stmt_details->execute();
        $stmt_details->close();

        // ลบ order หลัก
        $stmt_order = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt_order->bind_param("i", $delete_id);
        $stmt_order->execute();
        $stmt_order->close();

        $conn->commit();
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'ลบคำสั่งซื้อและรายการที่เกี่ยวข้องทั้งหมดเรียบร้อยแล้ว'];

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล'];
    }

    header("Location: orders.php");
    exit();
}

// --- จัดการการเพิ่มข้อมูล ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_order'])) {
    $order_date = $_POST['order_date'];
    $total_price = 0; // เริ่มต้นที่ 0 เสมอ
    $customer_id = (int)$_POST['customer_id'];

    $stmt = $conn->prepare("INSERT INTO orders (order_date, total_amount, customer_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sdi", $order_date, $total_price, $customer_id);
    $stmt->execute();
    
    $new_order_id = $conn->insert_id;
    $stmt->close();

    header("Location: order_details.php?order_id=" . $new_order_id);
    exit();
}

// --- จัดการการแก้ไขข้อมูล ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_order'])) {
    $order_id = (int)$_POST['order_id'];
    $order_date = $_POST['order_date'];
    $customer_id = (int)$_POST['customer_id'];

    $stmt = $conn->prepare("UPDATE orders SET order_date = ?, customer_id = ? WHERE order_id = ?");
    $stmt->bind_param("sii", $order_date, $customer_id, $order_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลคำสั่งซื้อเรียบร้อย'];
    header("Location: orders.php");
    exit();
}

// --- ดึงข้อมูลหลัก ---
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.customer_id, c.full_name 
        FROM orders o 
        JOIN customers c ON o.customer_id = c.customer_id 
        ORDER BY o.order_id DESC";
$result = $conn->query($sql);

// ดึงข้อมูลลูกค้าสำหรับ dropdown
$customers = $conn->query("SELECT customer_id AS id, full_name AS name FROM customers WHERE status = 1 ORDER BY full_name ASC");

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>รายการคำสั่งซื้อ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
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
            --info: #17a2b8;
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
            display: inline-flex; align-items: center; gap: 5px;
        }
        .action-buttons button:hover, .action-buttons a:hover { transform: translateY(-1px); }
        .edit-btn { background-color: var(--warning); color: #212529; }
        .edit-btn:hover { background-color: #e0a800; }
        .delete-btn { background-color: var(--danger); }
        .delete-btn:hover { background-color: #c0392b; }
        .detail-btn { background-color: var(--info); }
        .detail-btn:hover { background-color: #138496; }

        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 31, 63, 0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
        .modal-content { background-color: var(--white); margin: auto; padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 550px; position: relative; animation: fadeInScale 0.4s ease-out; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .close-btn { color: #aaa; position: absolute; top: 15px; right: 20px; font-size: 2rem; font-weight: bold; cursor: pointer; transition: color 0.2s, transform 0.2s; }
        .close-btn:hover { color: var(--danger); transform: rotate(90deg); }
        .modal h3 { font-size: 2rem; color: var(--navy-blue); text-align: center; margin-bottom: 25px; }
        .modal form { display: flex; flex-direction: column; gap: 5px; }
        .modal form label { display: block; margin-top: 10px; margin-bottom: 5px; font-weight: 500; color: var(--secondary-color); }
        .modal form input, .modal form select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; font-family: 'Sarabun', sans-serif; transition: all 0.3s; }
        .modal form input:focus, .modal form select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 8px rgba(52, 152, 219, 0.25); }
        .modal form button { width: 100%; padding: 12px; font-size: 1.1rem; margin-top: 20px; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: 500; transition: background-color 0.3s, transform 0.2s; }
        
        #add-modal button { background-color: var(--success); }
        #add-modal button:hover { background-color: #27ae60; }
        #edit-modal button { background-color: var(--warning); color:#212529; }
        #edit-modal button:hover { background-color: #e67e22; }
    </style>
</head>
<body>

<div class="container-wrapper">
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h1><i class="fas fa-receipt"></i> รายการคำสั่งซื้อ</h1>
        <a href="index.php" class="home-button"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
    </header>

    <div class="container">
        <div class="search-row">
            <input type="text" id="search-order" class="search-box" placeholder="ค้นหาด้วยชื่อลูกค้า, รหัสสั่งซื้อ..." onkeyup="searchOrder()"/>
            <button class="action-btn find-btn" onclick="searchOrder()"><i class="fas fa-search"></i> ค้นหา</button>
            <button class="action-btn add-btn" onclick="openModal('add-modal')"><i class="fas fa-plus-circle"></i> เพิ่มคำสั่งซื้อ</button>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>รหัสสั่งซื้อ</th>
                    <th>วันที่สั่งซื้อ</th>
                    <th>ชื่อลูกค้า</th>
                    <th>ราคารวม (บาท)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody id="orders-tbody">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center;"><?= htmlspecialchars($row["order_id"]) ?></td>
                    <td style="text-align:center;"><?= date('d/m/', strtotime($row["order_date"])) . (date('Y', strtotime($row["order_date"])) + 543) ?></td>
                    <td><?= htmlspecialchars($row["full_name"]) ?></td>
                    <td style="text-align:right; font-weight:bold;"><?= number_format($row["total_amount"], 2) ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="order_details.php?order_id=<?= $row['order_id'] ?>" class="detail-btn"><i class="fas fa-search-plus"></i> ดูรายละเอียด</a>
                            <button class="edit-btn" onclick="openEditModal('<?= $row['order_id'] ?>', '<?= $row['order_date'] ?>', '<?= $row['customer_id'] ?>')"><i class="fas fa-edit"></i> แก้ไข</button>
                            <form method="POST" action="orders.php" style="display:inline;" onsubmit="confirmDelete(event, this)">
                                <input type="hidden" name="delete_id" value="<?= $row['order_id'] ?>">
                                <button type="submit" class="delete-btn"><i class="fas fa-trash"></i> ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding:2rem;">ไม่มีข้อมูลคำสั่งซื้อ</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="add-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('add-modal')">&times;</span>
        <h3><i class="fas fa-plus-circle"></i> สร้างคำสั่งซื้อใหม่</h3>
        <form method="POST" action="orders.php">
            <input type="hidden" name="add_order" value="1">
            <label for="order_date">วันที่สั่งซื้อ:</label>
            <input type="date" id="order_date" name="order_date" value="<?= date('Y-m-d') ?>" required />
            
            <label for="customer_id">เลือกลูกค้า:</label>
            <select id="customer_id" name="customer_id" required>
                <option value="">-- เลือกลูกค้า --</option>
                <?php if($customers && $customers->num_rows > 0) { $customers->data_seek(0); while($cust = $customers->fetch_assoc()): ?>
                    <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?></option>
                <?php endwhile; } ?>
            </select>
            
            <button type="submit"><i class="fas fa-arrow-right"></i> บันทึกและเพิ่มรายการสินค้า</button>
        </form>
    </div>
</div>

<div id="edit-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('edit-modal')">&times;</span>
        <h3><i class="fas fa-edit"></i> แก้ไขข้อมูลคำสั่งซื้อ</h3>
        <form method="POST" action="orders.php">
            <input type="hidden" name="edit_order" value="1">
            <input type="hidden" id="edit-id" name="order_id" />
            
            <label for="edit-order-date">วันที่สั่งซื้อ:</label>
            <input type="date" id="edit-order-date" name="order_date" required />
            
            <label for="edit-customer-id">เลือกลูกค้า:</label>
            <select id="edit-customer-id" name="customer_id" required>
                <option value="">-- เลือกลูกค้า --</option>
                <?php if($customers && $customers->num_rows > 0) { $customers->data_seek(0); while($cust = $customers->fetch_assoc()): ?>
                    <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?></option>
                <?php endwhile; } ?>
            </select>
            
            <button type="submit"><i class="fas fa-sync-alt"></i> บันทึกการแก้ไข</button>
        </form>
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
<?php unset($_SESSION['alert']); endif; ?>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    function openEditModal(orderId, orderDate, customerId) {
        document.getElementById('edit-id').value = orderId;
        document.getElementById('edit-order-date').value = orderDate;
        document.getElementById('edit-customer-id').value = customerId;
        openModal('edit-modal');
    }

    function confirmDelete(event, form) {
        event.preventDefault(); 
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: "คำสั่งซื้อและรายการที่เกี่ยวข้องทั้งหมด (สต็อก, รายรับ) จะถูกลบอย่างถาวร!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    function searchOrder() {
        const input = document.getElementById('search-order');
        const filter = input.value.toUpperCase();
        const tableBody = document.getElementById('orders-tbody');
        const tr = tableBody.getElementsByTagName('tr');

        for (let i = 0; i < tr.length; i++) {
            let tdId = tr[i].getElementsByTagName('td')[0];
            let tdName = tr[i].getElementsByTagName('td')[2];
            if (tdId || tdName) {
                let txtValue = (tdId.textContent || tdId.innerText) + " " + (tdName.textContent || tdName.innerText);
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }
    }
    
    window.onclick = function(event) {
        if(event.target.classList.contains('modal')){
            closeModal(event.target.id);
        }
    }
</script>

</body>
</html>