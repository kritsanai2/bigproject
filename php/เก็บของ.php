<div class="charts">
    <div class="chart-card">
        <div class="chart-header">
            <i class='bx bx-bar-chart-alt-2'></i>
            <h3>ยอดขายต่อเดือน</h3>
        </div>
        <div class="year-select">
            <form method="GET">
                เลือกปี:
                <select name="sales_year" onchange="this.form.submit()">
                    <?php for($y=2023; $y<=2035; $y++): ?>
                        <option value="<?= $y ?>" <?= $sales_year==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <input type="hidden" name="trx_year" value="<?= $trx_year ?>">
                <input type="hidden" name="stock_year" value="<?= $stock_year ?>">
            </form>
        </div>
        <canvas id="salesChart"></canvas>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <i class='bx bx-pie-chart-alt-2'></i>
            <h3>ธุรกรรม รายรับ/รายจ่าย</h3>
        </div>
        <div class="year-select">
            <form method="GET">
                เลือกปี:
                <select name="trx_year" onchange="this.form.submit()">
                    <?php for($y=2023; $y<=2080; $y++): ?>
                        <option value="<?= $y ?>" <?= $trx_year==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <input type="hidden" name="sales_year" value="<?= $sales_year ?>">
                <input type="hidden" name="stock_year" value="<?= $stock_year ?>">
            </form>
        </div>
        <canvas id="trxChart"></canvas>
    </div>

    <div class="chart-card full-width">
        <div class="chart-header">
            <i class='bx bx-trending-up'></i>
            <h3>สต๊อกเข้า/ออกตามเดือน</h3>
        </div>
        <div class="year-select">
            <form method="GET">
                เลือกปี:
                <select name="stock_year" onchange="this.form.submit()">
                    <?php for($y=2023; $y<=2080; $y++): ?>
                        <option value="<?= $y ?>" <?= $stock_year==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <input type="hidden" name="sales_year" value="<?= $sales_year ?>">
                <input type="hidden" name="trx_year" value="<?= $trx_year ?>">
            </form>
        </div>
        <canvas id="stockChart"></canvas>
    </div>
</div>

<script>
// ยอดขายต่อเดือน
const salesData = {
  labels: ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."],
  datasets: [{ label: "ยอดขาย (บาท)", data: [
    <?= $sales[1]??0 ?>, <?= $sales[2]??0 ?>, <?= $sales[3]??0 ?>,
    <?= $sales[4]??0 ?>, <?= $sales[5]??0 ?>, <?= $sales[6]??0 ?>,
    <?= $sales[7]??0 ?>, <?= $sales[8]??0 ?>, <?= $sales[9]??0 ?>,
    <?= $sales[10]??0 ?>, <?= $sales[11]??0 ?>, <?= $sales[12]??0 ?>
  ], backgroundColor: "#0099ff" }]
};
new Chart(document.getElementById('salesChart'), { type: 'bar', data: salesData });

// ธุรกรรม
const trxData = {
  labels: ["รายรับ","รายจ่าย"],
  datasets: [{ label: "จำนวนเงิน", data: [<?= $income ?>, <?= $expense ?>], backgroundColor: ["#00cc66","#ff3333"] }]
};
new Chart(document.getElementById('trxChart'), { type: 'doughnut', data: trxData });

// กราฟสต๊อกเข้า/ออก
const stockData = {
  labels: <?= json_encode($stock_months) ?>,
  datasets: [
    { label: "เข้า", data: <?= json_encode($stock_in) ?>, backgroundColor: "#00cc66" },
    { label: "ออก", data: <?= json_encode($stock_out) ?>, backgroundColor: "#ff3333" }
  ]
};
new Chart(document.getElementById('stockChart'), {
  type: 'bar',
  data: stockData,
  options: { responsive:true, scales:{y:{beginAtZero:true}}, plugins:{legend:{position:'top'}} }
});
</script>