// เปิด/ปิด Modal
function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }

function openEditModal(id, fullName, position, phone) {
    document.getElementById('editEmployeeId').value = id;
    document.getElementById('editFullName').value = fullName;
    document.getElementById('editPosition').value = position;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

// ปิด modal เมื่อกด ESC
window.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        closeAddModal();
        closeEditModal();
    }
});

// ปิด modal เมื่อคลิกนอก modal-content
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// Search Employee
function searchEmployee() {
    let keyword = document.getElementById("search-employee").value.toLowerCase();
    let rows = Array.from(document.querySelectorAll("#employeesTable tbody tr"));

    if (!keyword) {
        rows.forEach(row => row.style.display = "");
        return;
    }

    let scoredRows = rows.map(row => {
        let score = 0;
        for (let i = 0; i < row.children.length - 1; i++) {
            let text = row.children[i].innerText.toLowerCase();
            if (text.includes(keyword)) {
                let count = (text.match(new RegExp(keyword, "g")) || []).length;
                score += count + keyword.length / text.length;
            }
        }
        return { row, score };
    });

    rows.forEach(row => row.style.display = "none");

    scoredRows
        .filter(r => r.score > 0)
        .sort((a, b) => b.score - a.score)
        .forEach(r => r.row.style.display = "");
}

// เรียกฟังก์ชันทันทีเมื่อพิมพ์
document.getElementById("search-employee").addEventListener("input", searchEmployee);