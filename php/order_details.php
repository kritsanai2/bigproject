<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) die("ไม่พบรหัสคำสั่งซื้อ");

$redirect_url = "order_details.php?order_id=" . $order_id;

// ====================== เพิ่มสินค้า ======================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_item'])){
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    $stmt_stock = $conn->prepare("
        SELECT COALESCE(SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END),0) -
               COALESCE(SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END),0) AS balance
        FROM stock WHERE product_id = ?
    ");
    $stmt_stock->bind_param("i", $product_id);
    $stmt_stock->execute();
    $stock = $stmt_stock->get_result()->fetch_assoc()['balance'];
    $stmt_stock->close();

    if($quantity > $stock){
        $_SESSION['notification'] = ['type' => 'error', 'message' => "สต็อกไม่เพียงพอ! คงเหลือเพียง $stock ชิ้น"];
        header("Location: $redirect_url");
        exit;
    }

    $stmt_price = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
    $stmt_price->bind_param("i", $product_id);
    $stmt_price->execute();
    $price = $stmt_price->get_result()->fetch_assoc()['price'];
    $stmt_price->close();

    $conn->begin_transaction();
    try {
        $stmt_insert = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("iiid", $order_id, $product_id, $quantity, $price);
        $stmt_insert->execute();
        $new_detail_id = $conn->insert_id;
        $stmt_insert->close();
        
        $order_date_stmt = $conn->prepare("SELECT order_date FROM orders WHERE order_id = ?");
        $order_date_stmt->bind_param("i", $order_id);
        $order_date_stmt->execute();
        $order_date = $order_date_stmt->get_result()->fetch_assoc()['order_date'];
        $order_date_stmt->close();

        $stmt_stock_remove = $conn->prepare("INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id) VALUES (?, 'remove', ?, ?, ?)");
        $stmt_stock_remove->bind_param("isii", $product_id, $order_date, $quantity, $order_id);
        $stmt_stock_remove->execute();
        $stmt_stock_remove->close();
        
        $stmt_trans = $conn->prepare("INSERT INTO transactions (transaction_type, amount, transaction_date, order_detail_id) VALUES ('income', ?, ?, ?)");
        $total_price = $price * $quantity;
        $stmt_trans->bind_param("dsi", $total_price, $order_date, $new_detail_id);
        $stmt_trans->execute();
        $stmt_trans->close();

        $stmt_order_update = $conn->prepare("UPDATE orders SET total_amount = total_amount + ? WHERE order_id = ?");
        $stmt_order_update->bind_param("di", $total_price, $order_id);
        $stmt_order_update->execute();
        $stmt_order_update->close();

        $conn->commit();
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'เพิ่มสินค้าเรียบร้อยแล้ว'];

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    
    header("Location: $redirect_url");
    exit;
}

// ====================== แก้ไขจำนวนสินค้า ======================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_detail_id'], $_POST['edit_quantity'])){
    $detail_id = (int)$_POST['edit_detail_id'];
    $new_qty = (int)$_POST['edit_quantity'];

    $stmt_old = $conn->prepare("SELECT od.product_id, od.quantity, od.price, o.order_date FROM order_details od JOIN orders o ON od.order_id = o.order_id WHERE od.order_detail_id = ?");
    $stmt_old->bind_param("i", $detail_id);
    $stmt_old->execute();
    $row = $stmt_old->get_result()->fetch_assoc();
    $product_id = $row['product_id'];
    $old_qty = $row['quantity'];
    $price = $row['price'];
    $order_date = $row['order_date'];
    $stmt_old->close();

    $stmt_stock_check = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END),0) - COALESCE(SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END),0) AS balance FROM stock WHERE product_id = ?");
    $stmt_stock_check->bind_param("i", $product_id);
    $stmt_stock_check->execute();
    $stock = $stmt_stock_check->get_result()->fetch_assoc()['balance'];
    $stmt_stock_check->close();
    
    $available = $stock + $old_qty;

    if($new_qty > $available){
        $_SESSION['notification'] = ['type' => 'error', 'message' => "สต็อกไม่เพียงพอ! สั่งได้สูงสุด $available ชิ้น"];
        header("Location: $redirect_url");
        exit;
    }

    $conn->begin_transaction();
    try {
        $price_diff = ($new_qty - $old_qty) * $price;

        $stmt1 = $conn->prepare("UPDATE order_details SET quantity = ? WHERE order_detail_id = ?");
        $stmt1->bind_param("ii", $new_qty, $detail_id);
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("UPDATE orders SET total_amount = total_amount + ? WHERE order_id = ?");
        $stmt2->bind_param("di", $price_diff, $order_id);
        $stmt2->execute();
        $stmt2->close();
        
        $stmt3 = $conn->prepare("UPDATE stock SET quantity = ? WHERE order_id = ? AND product_id = ? AND stock_type = 'remove' AND stock_date = ?");
        $stmt3->bind_param("iisi", $new_qty, $order_id, $product_id, $order_date);
        $stmt3->execute();
        $stmt3->close();
        
        $stmt4 = $conn->prepare("UPDATE transactions SET amount = ? WHERE order_detail_id = ?");
        $new_total_price = $new_qty * $price;
        $stmt4->bind_param("di", $new_total_price, $detail_id);
        $stmt4->execute();
        $stmt4->close();

        $conn->commit();
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'แก้ไขจำนวนสินค้าเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    
    header("Location: $redirect_url");
    exit;
}

// ====================== ลบสินค้า ======================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_detail_id'])){
    $detail_id = (int)$_POST['delete_detail_id'];

    $stmt_old_del = $conn->prepare("SELECT od.product_id, od.quantity, od.price, o.order_date FROM order_details od JOIN orders o ON od.order_id=o.order_id WHERE od.order_detail_id = ?");
    $stmt_old_del->bind_param("i", $detail_id);
    $stmt_old_del->execute();
    $row = $stmt_old_del->get_result()->fetch_assoc();
    $product_id = $row['product_id'];
    $qty = $row['quantity'];
    $price = $row['price'];
    $order_date = $row['order_date'];
    $stmt_old_del->close();
    
    $total_price_removed = $qty * $price;

    $conn->begin_transaction();
    try {
        $stmt_del1 = $conn->prepare("DELETE FROM transactions WHERE order_detail_id = ?");
        $stmt_del1->bind_param("i", $detail_id);
        $stmt_del1->execute();
        $stmt_del1->close();
        
        $stmt_del2 = $conn->prepare("DELETE FROM order_details WHERE order_detail_id = ?");
        $stmt_del2->bind_param("i", $detail_id);
        $stmt_del2->execute();
        $stmt_del2->close();

        $stmt_del3 = $conn->prepare("UPDATE orders SET total_amount = total_amount - ? WHERE order_id = ?");
        $stmt_del3->bind_param("di", $total_price_removed, $order_id);
        $stmt_del3->execute();
        $stmt_del3->close();
        
        $stmt_del4 = $conn->prepare("DELETE FROM stock WHERE order_id = ? AND product_id = ? AND stock_type = 'remove' AND quantity = ? AND stock_date = ?");
        $stmt_del4->bind_param("iiis", $order_id, $product_id, $qty, $order_date);
        $stmt_del4->execute();
        $stmt_del4->close();
        
        $conn->commit();
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'ลบสินค้าออกจากรายการแล้ว'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }

    header("Location: $redirect_url");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    $_SESSION['notification'] = ['type' => 'success', 'message' => "บันทึกคำสั่งซื้อ #$order_id เรียบร้อย"];
    header("Location: orders.php");
    exit;
}

// ====================== ดึงข้อมูล ======================
$order = $conn->query("SELECT o.order_id, o.order_date, o.total_amount, c.full_name FROM orders o JOIN customers c ON o.customer_id=c.customer_id WHERE o.order_id=$order_id")->fetch_assoc();
$details_result = $conn->query("SELECT od.order_detail_id, p.product_name, p.product_type, od.quantity, od.price FROM order_details od JOIN products p ON od.product_id=p.product_id WHERE od.order_id=$order_id");
$products = $conn->query("SELECT product_id, product_name, product_type, price FROM products WHERE status = 1 ORDER BY product_name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดคำสั่งซื้อ #<?= $order_id ?></title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 30px 40px;
        }

        .header-main {
            display: flex;
            justify-content: center;
            align-items: center;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header-main h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem; color: var(--navy-blue);
            margin: 0;
            display: flex; align-items: center; gap: 1rem;
        }
        
        .order-summary {
            background: linear-gradient(135deg, #e3f2fd, #f1f8e9);
            border-left: 6px solid var(--primary-color);
            padding: 20px 25px;
            margin-bottom: 30px;
            border-radius: 12px;
            font-size: 1.1em;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .order-summary p { margin: 5px 0; }
        .order-summary strong { color: var(--navy-blue); }
        .total-amount { font-weight: 700; color: var(--danger); font-size: 1.2em; }
        
        .action-buttons { display: flex; gap: 1rem; margin-bottom: 30px; flex-wrap: wrap; }
        .btn {
            padding: 12px 25px; border: none; border-radius: 50px;
            font-size: 1rem; font-weight: 500; color: #fff;
            cursor: pointer; transition: all 0.3s ease;
            text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .btn-add { background-color: var(--success); }
        .btn-add:hover { background-color: #27ae60; }
        .btn-save { background-color: var(--primary-color); }
        .btn-save:hover { background-color: #2980b9; }
        .btn-back { background-color: var(--secondary-color); }
        .btn-back:hover { background-color: #34495e; }
        
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
        .no-item { text-align: center; font-style: italic; color: #777; padding: 2rem; }
        
        /* === CSS สำหรับปุ่มในตาราง === */
        td .btn-edit, td .btn-danger {
            padding: 8px 12px;
            font-size: 0.9rem;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.2s;
        }
        td .btn-edit:hover, td .btn-danger:hover {
            transform: translateY(-1px);
        }
        td .btn-edit {
            background-color: var(--warning);
            color: #212529;
        }
        td .btn-danger {
            background-color: var(--danger);
        }
        td .btn-danger:hover {
            background-color: #c0392b;
        }
        td .btn-edit:hover {
            background-color: #e0a800;
        }
        /* === จบส่วนปุ่มในตาราง === */

        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 31, 63, 0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
        .modal-content { background-color: var(--white); margin: auto; padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 500px; position: relative; animation: fadeInScale 0.4s ease-out; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .close-btn { color: #aaa; position: absolute; top: 15px; right: 20px; font-size: 2rem; font-weight: bold; cursor: pointer; transition: color 0.2s, transform 0.2s; }
        .close-btn:hover { color: var(--danger); transform: rotate(90deg); }
        .modal h3 { font-size: 2rem; color: var(--navy-blue); text-align: center; margin-bottom: 25px; }
        .modal .form-group { margin-bottom: 20px; }
        .modal label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--secondary-color); }
        .modal input, .modal select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; font-family: 'Sarabun', sans-serif; transition: all 0.3s; }
        .modal input:focus, .modal select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 8px rgba(52, 152, 219, 0.25); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
        
        /* === CSS ที่แก้ไขสำหรับปุ่มบันทึกใน Modal แก้ไข === */
        #editModal .modal-actions .btn-edit {
            background-color: var(--white); /* สีขาว */
            color: var(--primary-color);   /* ตัวอักษรสีฟ้า */
            border: 2px solid var(--primary-color); /* มีขอบสีฟ้า */
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); /* มีเงาเล็กน้อย */
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 50px;
        }
        #editModal .modal-actions .btn-edit:hover {
            background-color: var(--primary-color); /* เมื่อ hover พื้นหลังเป็นสีฟ้า */
            color: var(--white); /* ตัวอักษรสีขาว */
            transform: translateY(-3px); 
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        /* === จบส่วนที่แก้ไข === */

        .modal-actions .btn-back {
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 50px;
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['notification'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['notification']['type'] ?>',
        title: '<?= $_SESSION['notification']['message'] ?>',
        showConfirmButton: false,
        timer: 2000,
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
</script>
<?php unset($_SESSION['notification']); endif; ?>

<div class="container">
    <header class="header-main">
        <h2><i class="fas fa-receipt"></i> รายละเอียดคำสั่งซื้อ #<?= $order_id ?></h2>
    </header>

    <div class="order-summary">
        <p><strong>หมายเลขคำสั่งซื้อ:</strong> <?= $order_id ?></p>
        <p><strong>ลูกค้า:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
        <p><strong>วันที่:</strong> <?= date('d/m/Y', strtotime($order['order_date'])) ?></p>
        <p><strong>รวมราคาทั้งสิ้น:</strong> <span class="total-amount"><?= number_format($order['total_amount'], 2) ?> บาท</span></p>
    </div>

    <div class="action-buttons">
        <button type="button" class="btn btn-add" onclick="openModal('addModal')"><i class="fas fa-plus-circle"></i> เพิ่มสินค้า</button>
        <form method="POST" style="display:inline-block;">
            <input type="hidden" name="save_order" value="1">
            <button type="submit" class="btn btn-save"><i class="fas fa-check-circle"></i> บันทึกและปิด</button>
        </form>
        <a href="orders.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> กลับหน้ารายการ</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ลำดับ</th> <th>สินค้า</th> <th>ประเภท</th> <th>จำนวน</th> <th>ราคา/หน่วย</th> <th>ราคารวม</th> <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; if($details_result && $details_result->num_rows > 0): while($row=$details_result->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center;"><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td style="text-align:center;"><?= htmlspecialchars($row['product_type']) ?></td>
                    <td style="text-align:center; font-weight:bold;"><?= $row['quantity'] ?></td>
                    <td style="text-align:right;"><?= number_format($row['price'], 2) ?></td>
                    <td style="text-align:right; font-weight:bold;"><?= number_format($row['quantity']*$row['price'], 2) ?></td>
                    <td>
                        <button type="button" class="btn-edit" onclick="openEditModal(<?= $row['order_detail_id'] ?>, <?= $row['quantity'] ?>)">แก้ไข</button>
                        <form method="POST" style="display:inline;" onsubmit="confirmDelete(event, this)">
                            <input type="hidden" name="delete_detail_id" value="<?= $row['order_detail_id'] ?>">
                            <button type="submit" class="btn-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" class="no-item">ยังไม่มีสินค้าในคำสั่งซื้อนี้</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('addModal')">&times;</span>
        <h3><i class="fas fa-plus-circle"></i> เพิ่มสินค้าในรายการ</h3>
        <form method="POST">
            <input type="hidden" name="add_item" value="1">
            <div class="form-group">
                <label for="product_id">สินค้า:</label>
                <select name="product_id" id="product_id" required>
                    <option value="">-- เลือกสินค้า --</option>
                    <?php if ($products) { $products->data_seek(0); while($prod=$products->fetch_assoc()): ?>
                    <option value="<?= $prod['product_id'] ?>"><?= htmlspecialchars($prod['product_name']) ?> (<?= number_format($prod['price'],2) ?> บาท)</option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">จำนวน:</label>
                <input type="number" name="quantity" id="quantity" min="1" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-add"><i class="fas fa-save"></i> บันทึก</button>
                <button type="button" class="btn btn-back" onclick="closeModal('addModal')">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
        <h3><i class="fas fa-edit"></i> แก้ไขจำนวนสินค้า</h3>
        <form method="POST">
            <input type="hidden" name="edit_detail_id" id="edit_detail_id">
            <div class="form-group">
                <label for="edit_quantity">จำนวน:</label>
                <input type="number" name="edit_quantity" id="edit_quantity" min="1" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn btn-edit"><i class="fas fa-sync-alt"></i> บันทึก</button>
                <button type="button" class="btn btn-back" onclick="closeModal('editModal')">ยกเลิก</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
    
    function openEditModal(detail_id, quantity) {
        document.getElementById('edit_detail_id').value = detail_id;
        document.getElementById('edit_quantity').value = quantity;
        openModal('editModal');
    }

    function confirmDelete(event, form) {
        event.preventDefault(); 
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: "คุณแน่ใจหรือไม่ว่าต้องการลบสินค้ารายการนี้?",
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

    window.onclick = function(event) {
        if(event.target.classList.contains('modal')){
            closeModal(event.target.id);
        }
    }
</script>

</body>
</html>