document.addEventListener('DOMContentLoaded', () => {
    // =========================
    // Sidebar Toggle
    // =========================
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');
    const toggleBtn = document.querySelector('.toggle-btn');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
        content.classList.toggle('full-width');
    });

    // =========================
    // Modal Open/Close
    // =========================
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');

    window.openAddModal = function() {
        editModal.style.display = 'none';
        addModal.style.display = 'block';
    }

    window.closeAddModal = function() {
        addModal.style.display = 'none';
    }

    window.openEditModal = function(stock_id, product_id, stock_type, stock_date, quantity, order_id) {
        addModal.style.display = 'none';
        document.getElementById('edit_stock_id').value = stock_id;
        document.getElementById('edit_product_id').value = product_id;
        document.getElementById('edit_stock_date').value = stock_date && stock_date !== '0000-00-00' ? stock_date : '';
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_order_id').value = order_id ?? '';
        document.getElementById('edit_stock_type').value = stock_type;
        editModal.style.display = 'block';
    }

    window.closeEditModal = function() {
        editModal.style.display = 'none';
    }

    // ปิด Modal เมื่อคลิกนอก modal
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', e => {
            if(e.target === modal){
                modal.style.display = 'none';
            }
        });
    });

    // =========================
    // Search stock
    // =========================
    const searchInput = document.getElementById("search-stock");
    searchInput.addEventListener("input", () => {
        const keyword = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll("table tbody tr");

        rows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            row.style.display = rowText.includes(keyword) ? "" : "none";
        });
    });
});
