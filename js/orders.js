  // ลบข้อมูล
document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.addEventListener("click", function(e){
        if(!confirm("ยืนยันการลบ?")){
            e.preventDefault();
        }
    });
});

// เปิด/ปิด modal เพิ่ม/แก้ไข
function openModal() { document.getElementById("add-modal").style.display = "flex"; }
function closeAddModal() { document.getElementById("add-modal").style.display = "none"; }

function openEditModal(orderId, orderDate, customerId) {
    document.getElementById("edit-id").value = orderId;
    document.getElementById("edit-order-date").value = orderDate;
    document.getElementById("edit-customer-id").value = customerId;
    document.getElementById("edit-modal").style.display = "flex";
}
function closeEditModal() { document.getElementById("edit-modal").style.display = "none"; }

function searchOrder() {
    let keyword = document.getElementById("search-order").value.toLowerCase();
    let rows = Array.from(document.querySelectorAll("#orders-tbody tr"));

    if (!keyword) {
        rows.forEach(row => row.style.display = "");
        return;
    }

    // คำนวณคะแนนความใกล้เคียงในทุกคอลัมน์ที่ต้องการค้นหา (ยกเว้นคอลัมน์ id และจัดการ)
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
document.getElementById("search-order").addEventListener("input", searchOrder);
