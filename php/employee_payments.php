<?php
require_once "db.php";  // เรียกไฟล์เชื่อมต่อฐานข้อมูล

$full_rate = 300;
$half_rate = 150;
$late_rate = 300;

$calculated_data = [];
$selected_month = isset($_POST['month']) ? date('Y-m', strtotime($_POST['month'])) : date('Y-m');

if(isset($_POST['calculate']) || isset($_POST['save'])){
    $sql = "
    SELECT 
        e.employee_id,
        e.full_name,
        SUM(CASE WHEN a.morning='present' AND a.afternoon='present' THEN 1 ELSE 0 END) AS full_days,
        SUM(CASE WHEN (a.morning='present' AND a.afternoon IN ('late','absent')) OR (a.morning IN ('late','absent') AND a.afternoon='present') THEN 1 ELSE 0 END) AS half_days,
        SUM(CASE WHEN a.morning='late' OR a.afternoon='late' THEN 1 ELSE 0 END) AS late_days,
        SUM(CASE WHEN a.morning='leave' OR a.afternoon='leave' THEN 1 ELSE 0 END) AS leave_days,
        SUM(CASE WHEN a.morning='absent' AND a.afternoon='absent' THEN 1 ELSE 0 END) AS absent_days
    FROM employees e
    LEFT JOIN attendances a 
        ON e.employee_id = a.employee_id
        AND DATE_FORMAT(a.attend_date,'%Y-%m') = '{$selected_month}'
    WHERE e.deleted = 0
    GROUP BY e.employee_id
    ORDER BY e.employee_id
    ";

    $result = $conn->query($sql);
    if($result){
        while($row = $result->fetch_assoc()){
            $work_days = $row['full_days'] + $row['half_days'] + $row['late_days'] + $row['leave_days'] + $row['absent_days'];
            $amount = $row['full_days']*$full_rate + $row['half_days']*$half_rate + $row['late_days']*$late_rate;

            $calculated_data[$row['employee_id']] = [
                'full_name' => $row['full_name'],
                'full' => (int)$row['full_days'],
                'half' => (int)$row['half_days'],
                'late' => (int)$row['late_days'],
                'leave' => (int)$row['leave_days'],
                'absent' => (int)$row['absent_days'],
                'work_days' => (int)$work_days,
                'amount' => (int)$amount
            ];
        }
    }
}

if(isset($_POST['save']) && !empty($calculated_data)){
    $pay_month = date('Y-m-01', strtotime($selected_month));

    foreach($calculated_data as $employee_id => $data){
        $check = $conn->query("SELECT 1 FROM employee_payments WHERE employee_id='$employee_id' AND pay_month='$pay_month'");
        if ($check && $check->num_rows > 0){
            $sql = "UPDATE employee_payments SET
                        work_days={$data['work_days']},
                        daily_rate=$full_rate,
                        full_days={$data['full']},
                        half_days={$data['half']},
                        late_days={$data['late']},
                        leave_days={$data['leave']},
                        absent_days={$data['absent']}
                    WHERE employee_id='$employee_id' AND pay_month='$pay_month'";
        } else {
            $sql = "INSERT INTO employee_payments
                        (employee_id, pay_month, work_days, daily_rate, full_days, half_days, late_days, leave_days, absent_days)
                    VALUES
                        ('$employee_id', '$pay_month', {$data['work_days']}, $full_rate,
                         {$data['full']}, {$data['half']}, {$data['late']}, {$data['leave']}, {$data['absent']})";
        }

        if(!$conn->query($sql)){
            echo "<div style='color:red;'>Error: ".$conn->error."</div>";
        }
    }
    echo "<div style='color:green; text-align:center;'>บันทึกข้อมูลเรียบร้อย</div>";
}

?>


<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Employee Payments</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');

body {
    font-family: 'Sarabun', sans-serif;
    background: #e6f7f1;
    margin: 0;
    padding: 40px 20px;
    color: #0a3d62;
}

h2 {
    text-align: center;
    color: #074f57;
}

form {
    margin-bottom: 20px;
    text-align: center;
}

input[type="month"] {
    padding: 6px 10px;
    font-size: 1rem;
    border-radius: 5px;
    border: 1px solid #74b9ff;
    margin-right: 10px;
}

button {
    padding: 8px 18px;
    border: none;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

button[name="calculate"] {
    background-color: #00cec9;
    color: white;
}

button[name="calculate"]:hover {
    background-color: #019ca1;
}

button[name="save"] {
    background-color: #55efc4;
    color: #074f57;
}

button[name="save"]:hover {
    background-color: #00b894;
    color: white;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}

th {
    background-color: #00b894;
    color: white;
    padding: 12px;
    text-align: center;
}

td {
    background-color: #dff9fb;
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #c8d6e5;
}

tr:hover td {
    background-color: #74b9ff;
    color: #fff;
    transform: translateY(-2px);
    transition: 0.2s;
}

input[type="hidden"] {
    display: none;
}
.home-button {
    text-decoration: none;
    background-color: #008080; /* Classic teal button */
    color: #fff;
    padding: 12px 25px;
    border-radius: 30px;
    font-weight: 600;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.home-button:hover {
    background-color: #005f73; /* Darker teal on hover */
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}
</style>
</head>
<body>

<h2>คำนวณเงินเดือนและบันทึก</h2>

<a href="attendances.php" class="home-button"> กลับ</a>

<form method="POST">
    เลือกเดือน: <input type="month" name="month" value="<?= $selected_month ?>">
    <button type="submit" name="calculate">คำนวณเงินเดือน</button>
</form>

<?php if(!empty($calculated_data)): ?>
<form method="POST">
    <input type="hidden" name="month" value="<?= $selected_month ?>">
    <table>
        <tr>
            <th>ลำดับ</th>
            <th>รหัสพนักงาน</th>
            <th>ชื่อ-สกุล</th>
            <th>วันเต็ม</th>
            <th>ครึ่งวัน</th>
            <th>วันสาย</th>
            <th>วันลา</th>
            <th>วันขาด</th>
            <th>จำนวนวันทำงาน</th>
            <th>เงินเดือน (บาท)</th>
        </tr>
        <?php $i=1; foreach($calculated_data as $id=>$d): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= $id ?></td>
            <td><?= $d['full_name'] ?></td>
            <td><?= $d['full'] ?></td>
            <td><?= $d['half'] ?></td>
            <td><?= $d['late'] ?></td>
            <td><?= $d['leave'] ?></td>
            <td><?= $d['absent'] ?></td>
            <td><?= $d['work_days'] ?></td>
            <td><?= $d['amount'] ?></td>

        </tr>
        <input type="hidden" name="data[<?= $id ?>][full]" value="<?= $d['full'] ?>">
        <input type="hidden" name="data[<?= $id ?>][half]" value="<?= $d['half'] ?>">
        <input type="hidden" name="data[<?= $id ?>][late]" value="<?= $d['late'] ?>">
        <input type="hidden" name="data[<?= $id ?>][leave]" value="<?= $d['leave'] ?>">
        <input type="hidden" name="data[<?= $id ?>][absent]" value="<?= $d['absent'] ?>">
        <input type="hidden" name="data[<?= $id ?>][work_days]" value="<?= $d['work_days'] ?>">
        <?php endforeach; ?>
    </table>
    <div style="text-align:center; margin-top:20px;">
        <button type="submit" name="save">บันทึกลงฐานข้อมูล</button>
    </div>
</form>
<?php endif; ?>

</body>
</html>