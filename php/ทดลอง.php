<?php
$conn = new mysqli("localhost", "root", "", "bigproject");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบสต็อกสินค้า</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
body { display:flex; min-height:100vh; background:#e8f8f5; }

/* Sidebar */
.sidebar {
    width:70px; 
    background:linear-gradient(180deg,#3498db,#1abc9c); 
    color:#ecf0f1; 
    display:flex; 
    flex-direction:column;
    padding:20px 10px; 
    transition:all 0.3s; 
    overflow:hidden;
    box-shadow:2px 0 8px rgba(0,0,0,0.1);
}
.sidebar h1,
.sidebar h2,
.sidebar a span {
    opacity:0;
    transition:opacity 0.3s;
    white-space:nowrap;
}

/* เมื่อ hover sidebar จะขยาย + เด้งตัวหนังสือออกมา */
.sidebar:hover {
    width:250px;
}
.sidebar:hover h1,
.sidebar:hover h2,
.sidebar:hover a span {
    opacity:1;
}

/* เมนู */
.sidebar a {
    display:flex; 
    align-items:center; 
    text-decoration:none; 
    color:#ecf0f1; 
    padding:15px; 
    margin:8px 0; 
    border-radius:15px; 
    transition: all 0.3s;
    font-size:20px;
    overflow:hidden;
}
.sidebar a i {
    min-width:30px;
    text-align:center;
    margin-right:10px;
}
.sidebar a:hover, .sidebar a.active { 
    background-color:rgba(255,255,255,0.2); 
    transform:scale(1.05); 
}

/* Content */
.content { flex:1; padding:30px; background:#e8f8f5; overflow-y:auto; }

/* Table */
table { width:100%; border-collapse:collapse; margin-top:20px; background:#fff; border-radius:8px; overflow:hidden; }
th, td { border:1px solid #ccc; padding:10px; text-align:center; }
th { background:#1abc9c; color:#fff; }
tr:nth-child(even) { background:#f9f9f9; }

/* Responsive */
@media(max-width:768px){
    body{flex-direction:column;}
    .sidebar{width:100%; flex-direction:row; justify-content:center;}
    .sidebar:hover{width:100%;}
    .sidebar h1, .sidebar h2, .sidebar a span {opacity:1;}
    .sidebar a{flex:1; justify-content:center;}
}
</style>
</head>
<body>

<div class="sidebar">
    <h2>รายการสต็อกสินค้า</h2>

    <!-- ทั้งหมด -->
    <a href=""><i class="fas fa-list"></i><span> ทั้งหมด</span></a>

    <!-- นำเข้า -->
    <a href=""><i class="fas fa-arrow-down"></i><span> นำเข้า</span></a>

    <!-- นำออก -->
    <a href=""><i class="fas fa-arrow-up"></i><span> นำออก</span></a>
</div>


</body>
</html>
