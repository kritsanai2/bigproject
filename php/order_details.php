<?php
session_start();
require_once "db.php"; 

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) die("ไม่พบรหัสคำสั่งซื้อ");

// ====================== เพิ่มสินค้า ======================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['product_id'], $_POST['quantity'])){
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    $stock = $conn->query("
        SELECT COALESCE(SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END),0) -
               COALESCE(SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END),0) AS balance
        FROM stock
        WHERE product_id=$product_id AND deleted=0
    ")->fetch_assoc()['balance'];

    if($quantity > $stock){
        echo "<script>alert('สต็อกไม่เพียงพอ! คงเหลือเพียง $stock'); history.back();</script>";
        exit;
    }

    $price = $conn->query("SELECT price FROM products WHERE product_id=$product_id AND deleted=0")->fetch_assoc()['price'];

    $stmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiid",$order_id,$product_id,$quantity,$price);
    $stmt->execute();

    $conn->query("INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id, deleted)
                  VALUES ($product_id, 'remove', NOW(), $quantity, $order_id, 0)");

    $conn->query("UPDATE orders SET total_amount = total_amount + ".($price*$quantity)." WHERE order_id=$order_id");
}

// ====================== แก้ไขจำนวนสินค้า ======================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_detail_id'], $_POST['edit_quantity'])){
    $detail_id = (int)$_POST['edit_detail_id'];
    $new_qty = (int)$_POST['edit_quantity'];

    $row = $conn->query("SELECT product_id, quantity, price FROM order_details WHERE order_detail_id=$detail_id")->fetch_assoc();
    $product_id = $row['product_id'];
    $old_qty = $row['quantity'];
    $price = $row['price'];

    $stock = $conn->query("
        SELECT COALESCE(SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END),0) -
               COALESCE(SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END),0) AS balance
        FROM stock
        WHERE product_id=$product_id AND deleted=0
    ")->fetch_assoc()['balance'];

    $available = $stock + $old_qty;

    if($new_qty > $available){
        echo "<script>alert('สต็อกไม่เพียงพอ! คงเหลือเพียง $available'); history.back();</script>";
        exit;
    }

    $conn->query("UPDATE order_details SET quantity=$new_qty WHERE order_detail_id=$detail_id");

    $conn->query("UPDATE orders SET total_amount = total_amount - ".($old_qty*$price)." + ".($new_qty*$price)." WHERE order_id=$order_id");

    $conn->query("UPDATE stock SET deleted=1 WHERE order_id=$order_id AND product_id=$product_id AND stock_type='remove' AND deleted=0 ORDER BY stock_id DESC LIMIT 1");
    $conn->query("INSERT INTO stock (product_id, stock_type, stock_date, quantity, order_id, deleted)
                  VALUES ($product_id, 'remove', NOW(), $new_qty, $order_id, 0)");
}

// ====================== ลบสินค้า ======================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_detail_id'])){
    $detail_id = (int)$_POST['delete_detail_id'];
    $row = $conn->query("SELECT product_id, quantity, price FROM order_details WHERE order_detail_id=$detail_id")->fetch_assoc();
    $product_id = $row['product_id'];
    $qty = $row['quantity'];
    $price = $row['price'];

    $conn->query("UPDATE order_details SET deleted=1 WHERE order_detail_id=$detail_id");
    $conn->query("UPDATE orders SET total_amount = total_amount - ".($qty*$price)." WHERE order_id=$order_id");

    $conn->query("UPDATE stock SET deleted=1 WHERE order_id=$order_id AND product_id=$product_id AND stock_type='remove' AND deleted=0 ORDER BY stock_id DESC LIMIT 1");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    header("Location: orders.php");
    exit;
}

// ====================== ดึงข้อมูลคำสั่งซื้อและรายการ ======================
$order = $conn->query("SELECT o.order_id, o.order_date, o.total_amount FROM orders o WHERE o.order_id=$order_id")->fetch_assoc();
$details_result = $conn->query("SELECT od.order_detail_id, p.product_name, p.product_type, od.quantity, od.price
                                 FROM order_details od JOIN products p ON od.product_id=p.product_id
                                 WHERE od.order_id=$order_id AND od.deleted=0");
$products = $conn->query("SELECT product_id, product_name, product_type, price FROM products WHERE deleted=0 ORDER BY product_name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดคำสั่งซื้อ #<?= $order_id ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f7f9;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 1100px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        h2 {
            color: #1a237e;
            font-size: 2.2em;
            font-weight: 700;
        }

        .order-summary {
            background-color: #e3f2fd;
            border-left: 5px solid #2196f3;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 1.1em;
        }

        .order-summary p {
            margin: 5px 0;
        }

        .order-summary strong {
            color: #1a237e;
        }

        .total-amount {
            font-weight: 700;
            color: #e91e63;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: #4caf50;
        }

        .btn-success {
            background-color: #2196f3;
        }

        .btn-secondary {
            background-color: #9e9e9e;
        }

        .btn-edit {
            background-color: #ff9800;
        }

        .btn-danger {
            background-color: #f44336;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        thead {
            background-color: #42a5f5;
            color: #fff;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            font-weight: 700;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f8f8;
        }

        tbody tr:hover {
            background-color: #eef7ff;
        }

        .no-item {
            text-align: center;
            font-style: italic;
            color: #777;
        }

        td button {
            margin-left: 5px;
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            padding-top: 60px;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-btn:hover, .close-btn:focus {
            color: #333;
            text-decoration: none;
        }

        .modal h3 {
            margin-top: 0;
            color: #1a237e;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input, .form-group select {
            width: calc(100% - 24px);
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        .modal-actions .btn {
            padding: 10px 18px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            h2 {
                font-size: 1.8em;
            }
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            th, td {
                padding: 12px 8px;
            }
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <h2>📝 รายละเอียดคำสั่งซื้อ #<?= $order_id ?></h2>
    </header>

    <div class="order-summary">
        <p><strong>หมายเลขคำสั่งซื้อ:</strong> <?= $order_id ?></p>
        <p><strong>วันที่:</strong> <?= $order['order_date'] ?></p>
        <p><strong>รวมราคา:</strong> <span class="total-amount"><?= number_format($order['total_amount'],2) ?> บาท</span></p>
    </div>

    <div class="action-buttons">
        <button type="button" class="btn btn-primary" onclick="openAddModal()">➕ เพิ่มสินค้า</button>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="save_order" value="1">
            <button type="submit" class="btn btn-success">💾 บันทึกคำสั่งซื้อ</button>
        </form>
        <a href="orders.php" class="btn btn-secondary">⬅ กลับไปหน้ารายการ</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>สินค้า</th>
                    <th>ประเภท</th>
                    <th>จำนวน</th>
                    <th>ราคา/หน่วย</th>
                    <th>ราคารวม</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; if($details_result->num_rows>0): while($row=$details_result->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['product_type']) ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= number_format($row['price'],2) ?></td>
                    <td><?= number_format($row['quantity']*$row['price'],2) ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?')">
                            <input type="hidden" name="delete_detail_id" value="<?= $row['order_detail_id'] ?>">
                            <button type="submit" class="btn btn-danger">ลบ</button>
                        </form>
                        <button type="button" class="btn btn-edit" onclick="openEditModal(<?= $row['order_detail_id'] ?>, <?= $row['quantity'] ?>)">แก้ไข</button>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="no-item">ไม่มีสินค้าในคำสั่งซื้อนี้</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">&times;</span>
            <h3>เพิ่มสินค้า</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="product_id">สินค้า:</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">-- เลือกสินค้า --</option>
                        <?php $products->data_seek(0); while($prod=$products->fetch_assoc()): ?>
                        <option value="<?= $prod['product_id'] ?>"><?= htmlspecialchars($prod['product_name']) ?> (<?= number_format($prod['price'],2) ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">จำนวน:</label>
                    <input type="number" name="quantity" id="quantity" min="1" required>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h3>แก้ไขจำนวน</h3>
            <form method="POST">
                <input type="hidden" name="edit_detail_id" id="edit_detail_id">
                <div class="form-group">
                    <label for="edit_quantity">จำนวน:</label>
                    <input type="number" name="edit_quantity" id="edit_quantity" min="1" required>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function openEditModal(detail_id, quantity) {
        document.getElementById('edit_detail_id').value = detail_id;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
</script>

</body>
</html>