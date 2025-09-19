<?php
require_once "db.php";

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

if ($customer_id <= 0) {
    echo json_encode([]);
    exit();
}

$sql = "SELECT o.order_id, o.order_date, p.product_name, od.quantity, od.price
        FROM orders o
        JOIN order_details od ON o.order_id = od.order_id
        JOIN products p ON od.product_id = p.product_id
        WHERE o.customer_id = ?";

$params = [$customer_id];

if ($month > 0) {
    $sql .= " AND MONTH(o.order_date) = ?";
    $params[] = $month;
}
if ($year > 0) {
    $sql .= " AND YEAR(o.order_date) = ?";
    $params[] = $year;
}

$stmt = $conn->prepare($sql);

if(count($params) === 1){
    $stmt->bind_param("i", $params[0]);
} elseif(count($params) === 2){
    $stmt->bind_param("ii", $params[0], $params[1]);
} elseif(count($params) === 3){
    $stmt->bind_param("iii", $params[0], $params[1], $params[2]);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while($row = $result->fetch_assoc()){
    $orders[] = $row;
}

echo json_encode($orders);
