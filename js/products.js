function openModal() {
  document.getElementById('product-modal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('product-modal').style.display = 'none';
}

function openEditModal(id, type, name, size, unit, price) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-type').value = type;
  document.getElementById('edit-name').value = name;
  document.getElementById('edit-size').value = size;
  document.getElementById('edit-unit').value = unit;
  document.getElementById('edit-price').value = price;
  document.getElementById('edit-modal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('edit-modal').style.display = 'none';
}

function searchProduct() {
    let keyword = document.getElementById("search-customer").value.toLowerCase();
    let rows = Array.from(document.querySelectorAll("tbody tr"));

    if (!keyword) {
        rows.forEach(row => row.style.display = "");
        return;
    }

    // คำนวณคะแนนความใกล้เคียง
    let scoredRows = rows.map(row => {
        let type = row.children[1].innerText.toLowerCase();
        let name = row.children[2].innerText.toLowerCase();
        let score = 0;

        let countType = (type.match(new RegExp(keyword, "g")) || []).length;
        let countName = (name.match(new RegExp(keyword, "g")) || []).length;
        if (countType + countName > 0) {
            score = countType + countName + keyword.length / Math.max(type.length, name.length);
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
document.getElementById("search-customer").addEventListener("input", searchProduct);