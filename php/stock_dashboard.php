<?php
session_start();
// Make sure to have this file with your database connection details
require_once "db.php";


// Thai language helper functions
function thai_month($month) {
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[(int)$month] ?? '';
}

function thai_type($type) {
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก';
}

// Get filter parameters from URL
$type_filter = $_GET['type'] ?? '';
$month_filter = $_GET['month'] ?? 0;
$year_filter = $_GET['year'] ?? date('Y');
$page = $_GET['page'] ?? 'table'; // 'table' or 'graph'

// --- Data Fetching for Table ---
$sql = "SELECT s.stock_id, s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit
        FROM stock s
        JOIN products p ON s.product_id = p.product_id
        WHERE s.deleted = 0";

// Append filters to the query
if ($type_filter) {
    $sql .= " AND s.stock_type = '" . $conn->real_escape_string($type_filter) . "'";
}
if ($month_filter > 0) {
    $sql .= " AND MONTH(s.stock_date) = " . (int)$month_filter;
}
if ($year_filter) {
    $sql .= " AND YEAR(s.stock_date) = " . (int)$year_filter;
}

$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";
$result = $conn->query($sql);


// --- Data Fetching for Graph ---
$graph_data = [];
$graph_labels = [];
$graph_sql = "SELECT s.stock_type, SUM(s.quantity) AS total_qty FROM stock s WHERE s.deleted=0";

if ($type_filter) {
    $graph_sql .= " AND s.stock_type='" . $conn->real_escape_string($type_filter) . "'";
}
if ($month_filter > 0) {
    $graph_sql .= " AND MONTH(s.stock_date)=$month_filter";
}
if ($year_filter) {
    $graph_sql .= " AND YEAR(s.stock_date)=$year_filter";
}
$graph_sql .= " GROUP BY s.stock_type";

$graph_result = $conn->query($graph_sql);
if ($graph_result) {
    while ($row = $graph_result->fetch_assoc()) {
        $graph_data[$row['stock_type']] = $row['total_qty'];
        $graph_labels[] = thai_type($row['stock_type']);
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสต็อก</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* --- General Styles & Typography --- */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f7f9;
            color: #2c3e50;
            line-height: 1.6;
            font-size: 16px;
            display: flex;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            color: #3498db;
            border-bottom: 3px solid #3498db;
            padding-bottom: 0.5rem;
        }

        /* --- Sidebar Section --- */
        .sidebar {
            width: 250px;
            background-color: #3498db;
            color: white;
            padding: 2rem 1.5rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1000;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            margin-bottom: 1.5rem;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            font-weight: 700;
            text-align: center;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            width: 100%;
            text-align: left;
            transition: background-color 0.2s ease, transform 0.2s ease;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.3);
            font-weight: 500;
        }

        .toggle-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: left 0.3s ease-in-out;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* --- Main Content Section --- */
        .main {
            margin-left: 250px;
            padding: 2rem;
            flex-grow: 1;
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 250px);
        }

        .main.full-width {
            margin-left: 0;
            width: 100%;
        }

        /* --- Filter & Actions Section --- */
        .filter {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
        }

        .filter label {
            font-weight: 500;
        }

        .filter select,
        .filter input {
            padding: 0.5rem;
            border: 1px solid #e0e6ea;
            border-radius: 8px;
            font-family: 'Sarabun', sans-serif;
            font-size: 1rem;
        }

        .filter button,
        #shareButton {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            color: white;
            font-size: 1rem;
        }

        .filter button {
            background-color: #3498db;
        }

        #shareButton {
            background-color: #9b59b6;
            margin-left: auto;
        }

        .filter button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        #shareButton:hover {
            background-color: #8e44ad;
            transform: translateY(-2px);
        }

        /* --- Table Section --- */
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        thead {
            background-color: #3498db;
            color: white;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e6ea;
        }

        th {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f1faff;
        }

        /* --- Chart Section --- */
        .chart-container {
            background: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* --- Modal Styles --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e6ea;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
            color: #3498db;
            font-size: 1.5rem;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 1.75rem;
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .close-button:hover {
            color: #e74c3c;
            transform: rotate(90deg);
        }

        .modal-body label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .modal-body select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .action-buttons button {
            flex-grow: 1;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: none;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.3s ease, transform 0.2s ease;
        }
        .action-buttons button:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        #downloadButton { background-color: #2ecc71; }
        #emailButton { background-color: #e67e22; }
        #messengerButton { background-color: #3498db; }

        .action-buttons button:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.7;
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


        /* --- Responsive Design --- */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
                width: 100%;
            }
            
            .main.sidebar-open {
                margin-left: 250px;
            }

            .toggle-btn {
                left: 1rem;
            }

            .filter form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter select, .filter button, #shareButton {
                width: 100%;
            }
            #shareButton {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>

    <button class="toggle-btn" id="toggle-sidebar-btn">☰</button>

    <div class="sidebar" id="sidebar">
        <img src="https://placehold.co/100x100/3498db/ffffff?text=Logo" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>ข้อมูลคลังสินค้า</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>กลับ</span></a>
        <a href="?page=table&type=&month=<?= $month_filter ?>&year=<?= $year_filter ?>" class="<?= ($page == 'table' && $type_filter == '') ? 'active' : '' ?>"><i class="fas fa-list"></i>&nbsp; <span>ทั้งหมด</span></a>
        <a href="?page=table&type=import&month=<?= $month_filter ?>&year=<?= $year_filter ?>" class="<?= ($page == 'table' && $type_filter == 'import') ? 'active' : '' ?>"><i class="fas fa-arrow-down"></i>&nbsp; <span>รับเข้า</span></a>
        <a href="?page=table&type=remove&month=<?= $month_filter ?>&year=<?= $year_filter ?>" class="<?= ($page == 'table' && $type_filter == 'remove') ? 'active' : '' ?>"><i class="fas fa-arrow-up"></i>&nbsp; <span>จ่ายออก</span></a>
        <a href="?page=graph&type=<?= $type_filter ?>&month=<?= $month_filter ?>&year=<?= $year_filter ?>" class="<?= ($page == 'graph') ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานกราฟ</span></a>
    </div>


    <div class="main" id="main-content">
        <h1><i class="fas fa-box"></i>&nbsp; รายงานคลังสินค้า</h1>

        <div class="filter">
            <form method="get">
                <input type="hidden" name="page" value="<?= $page ?>">
                <label for="type-filter">ประเภท:</label>
                <select name="type" id="type-filter">
                    <option value="" <?= ($type_filter == '') ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="import" <?= ($type_filter == 'import') ? 'selected' : '' ?>>รับเข้า</option>
                    <option value="remove" <?= ($type_filter == 'remove') ? 'selected' : '' ?>>จ่ายออก</option>
                </select>
                <label for="month-filter">เดือน:</label>
                <select name="month" id="month-filter">
                    <option value="0" <?= ($month_filter == 0) ? 'selected' : '' ?>>ทั้งหมด</option>
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= $m ?>" <?= ($month_filter == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                    <?php endfor; ?>
                </select>
                <label for="year-filter">ปี:</label>
                <select name="year" id="year-filter">
                    <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++) : ?>
                        <option value="<?= $y ?>" <?= ($year_filter == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
                <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; แชร์</button>
            </form>
        </div>


        <?php if ($page == 'table') : ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>วันที่</th>
                            <th>สินค้า</th>
                            <th>ประเภท</th>
                            <th>จำนวน</th>
                            <th>หน่วย</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0) :
                            $i = $result->num_rows;
                            while ($row = $result->fetch_assoc()) :
                        ?>
                                <tr>
                                    <td><?= $i-- ?></td>
                                    <td><?= htmlspecialchars($row['stock_date']) ?></td>
                                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td><?= thai_type($row['stock_type']) ?></td>
                                    <td><?= number_format($row['quantity'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['unit']) ?></td>
                                </tr>
                            <?php endwhile;
                        else : ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else : // if page == 'graph' ?>
            <div class="chart-container">
                <canvas id="stockChart"></canvas>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('stockChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($graph_labels) ?>,
                            datasets: [{
                                label: 'จำนวนรวม',
                                data: <?= json_encode(array_values($graph_data)) ?>,
                                backgroundColor: ['rgba(40, 167, 69, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                                borderColor: ['rgb(40, 167, 69)', 'rgb(220, 53, 69)'],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: 'สรุปยอดรับเข้า-จ่ายออก'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                });
            </script>
        <?php endif; ?>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>แชร์รายงาน</h3>
                <button class="close-button">&times;</button>
            </div>
            <div class="modal-body">
                <label for="fileSelect">เลือกรูปแบบไฟล์เพื่อส่งออก:</label>
                <select id="fileSelect">
                    <option value="">-- กรุณาเลือก --</option>
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                </select>
                <div class="action-buttons">
                    <button id="downloadButton" disabled>ดาวน์โหลด</button>
                    <button id="emailButton" disabled>อีเมล</button>
                    <button id="messengerButton" disabled>Messenger</button>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleBtn = document.getElementById('toggle-sidebar-btn');

            // --- Sidebar Toggle Functionality ---
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('full-width');
            });
            
            // For smaller screens, hide sidebar by default
            if (window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                mainContent.classList.add('full-width');
            }

            // --- Share Modal Functionality ---
            const shareButton = document.getElementById('shareButton');
            const modal = document.getElementById('shareModal');
            const closeModalButton = modal.querySelector('.close-button');
            const fileSelect = document.getElementById('fileSelect');
            const downloadBtn = document.getElementById('downloadButton');
            const emailBtn = document.getElementById('emailButton');
            const messengerBtn = document.getElementById('messengerButton');
            const actionButtons = [downloadBtn, emailBtn, messengerBtn];
            
            // Function to open the modal
            function openModal() {
                modal.style.display = 'flex';
            }

            // Function to close the modal
            function closeModal() {
                modal.style.display = 'none';
                fileSelect.value = ''; // Reset dropdown
                toggleActionButtons(true); // Disable buttons
            }

            // Open modal on share button click
            shareButton.addEventListener('click', openModal);

            // Close modal on close button click
            closeModalButton.addEventListener('click', closeModal);

            // Close modal on outside click
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            
            // Function to enable/disable action buttons
            function toggleActionButtons(disabled) {
                actionButtons.forEach(button => button.disabled = disabled);
            }

            // Event listener for the file selection dropdown
            fileSelect.addEventListener('change', function() {
                toggleActionButtons(this.value === '');
            });

            // --- Modal Action Button Clicks ---
         // --- Modal Action Button Clicks ---
const currentParams = new URLSearchParams(window.location.search);
const reportTitle = "รายงานสต็อกสินค้า";

// 1. Download Button
downloadBtn.addEventListener('click', function() {
    const fileType = fileSelect.value;
    if (!fileType) return;

    // เรียก stock_export.php พร้อมพารามิเตอร์ format
    const exportUrl = `stock_export.php?format=${fileType}&${currentParams.toString()}`;
    window.open(exportUrl, '_blank');
    closeModal();
});

// 2. Email Button
emailBtn.addEventListener('click', function() {
    const fileType = fileSelect.value;
    if (!fileType) return;

    const exportUrl = `${window.location.origin}/stock_export.php?format=${fileType}&${currentParams.toString()}`;
    const body = `สวัสดี,\n\nนี่คือลิงก์สำหรับรายงานสต็อกสินค้า:\n${exportUrl}\n\nขอบคุณ`;
    const mailtoLink = `mailto:?subject=${encodeURIComponent(reportTitle)}&body=${encodeURIComponent(body)}`;
    window.location.href = mailtoLink;
    closeModal();
});

// 3. Messenger Button
messengerBtn.addEventListener('click', function() {
    const fileType = fileSelect.value;
    if (!fileType) return;

    const exportUrl = encodeURIComponent(`${window.location.origin}/stock_export.php?format=${fileType}&${currentParams.toString()}`);
    // ใช้ลิงก์แชร์ Messenger ผ่านเว็บ
    const messengerLink = `https://www.facebook.com/dialog/send?link=${exportUrl}&app_id=YOUR_APP_ID&redirect_uri=${exportUrl}`;
    window.open(messengerLink, '_blank');
    closeModal();
});

        });
    </script>
</body>

</html>