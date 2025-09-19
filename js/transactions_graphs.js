// Toggle sidebar
const toggleBtn = document.getElementById('toggleSidebar');
const sidebar = document.querySelector('.sidebar');
const main = document.querySelector('.main');

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('closed');
    main.classList.toggle('full');
});

// --- รายวัน ---
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: chartData.dayLabels,
        datasets: [
            { label: 'รายรับ', data: chartData.incomeDaily, backgroundColor: 'rgba(0,123,255,0.6)' },
            { label: 'รายจ่าย', data: chartData.expenseDaily, backgroundColor: 'rgba(255,99,132,0.6)' }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

// --- รายเดือน ---
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: chartData.monthLabels,
        datasets: [
            { label: 'รายรับ', data: chartData.incomeMonthly, backgroundColor: 'rgba(0,123,255,0.6)' },
            { label: 'รายจ่าย', data: chartData.expenseMonthly, backgroundColor: 'rgba(255,99,132,0.6)' }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

// --- รายปี ---
new Chart(document.getElementById('yearlyChart'), {
    type: 'line',
    data: {
        labels: chartData.yearLabels,
        datasets: [
            { label: 'รายรับ', data: chartData.incomeYear, borderColor: 'rgba(0,123,255,1)', fill: false, tension: 0.3 },
            { label: 'รายจ่าย', data: chartData.expenseYear, borderColor: 'rgba(255,99,132,1)', fill: false, tension: 0.3 }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});
