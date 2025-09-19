let currentCustomerId = null;

function openModal(){document.getElementById("customer-modal").style.display="flex";}
function closeModal(){document.getElementById("customer-modal").style.display="none";}
function openEditModal(id,name,phone,address){
document.getElementById("edit-id").value=id;
document.getElementById("edit-name").value=name;
document.getElementById("edit-phone").value=phone;
document.getElementById("edit-address").value=address;
document.getElementById("edit-modal").style.display="flex";}
function closeEditModal(){document.getElementById("edit-modal").style.display="none";}

function openOrdersModal(customerId,customerName){
currentCustomerId = customerId;
document.getElementById("orders-customer-name").innerText = "คำสั่งซื้อของ: " + customerName;
document.getElementById("orders-modal").style.display="flex";
loadOrders();
}
function closeOrdersModal(){document.getElementById("orders-modal").style.display="none";}

function loadOrders(){
    let month = document.getElementById("filter-month").value;
    let year = document.getElementById("filter-year").value;

    fetch(`get_orders.php?customer_id=${currentCustomerId}&month=${month}&year=${year}`)
    .then(res => res.json())
    .then(data => {
        let tbody = document.querySelector("#orders-table tbody");
        tbody.innerHTML = "";

        if(data.length === 0){
            tbody.innerHTML = "<tr><td colspan='6'>ไม่มีคำสั่งซื้อ</td></tr>";
            return;
        }

        data.forEach((order, index) => {
            let tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${order.order_id}</td>
                <td>${order.order_date}</td>
                <td>${order.product_name}</td>
                <td>${order.quantity}</td>
                <td>${parseFloat(order.price).toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
    });
}


// ฟังก์ชันค้นหา
function searchCustomer() {
    let keyword = document.getElementById("search-customer").value.toLowerCase();
    let rows = Array.from(document.querySelectorAll("tbody tr"));

    if (!keyword) {
        rows.forEach(row => row.style.display = "");
        return;
    }

    // คำนวณคะแนนความใกล้เคียงในทุกคอลัมน์ที่ต้องการค้นหา (ยกเว้นคอลัมน์จัดการ)
    let scoredRows = rows.map(row => {
        let score = 0;
        for (let i = 1; i < row.children.length - 1; i++) { // เริ่มที่ 1 เพื่อข้ามคอลัมน์ id
            let text = row.children[i].innerText.toLowerCase();
            if (text.includes(keyword)) {
                let count = (text.match(new RegExp(keyword, "g")) || []).length;
                score += count + keyword.length / text.length;
            }
        }
        return { row, score };
    });

    // ซ่อนทุกแถวก่อน
    rows.forEach(row => row.style.display = "none");

    // แสดงแถวที่มีคะแนน > 0
    scoredRows
        .filter(r => r.score > 0)
        .sort((a, b) => b.score - a.score)
        .forEach(r => r.row.style.display = "");
}

// เรียกฟังก์ชันทันทีเมื่อพิมพ์
document.getElementById("search-customer").addEventListener("input", searchCustomer);