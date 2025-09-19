function selectStatus(empId, period, status, btn) {
    // อัปเดตค่าใน hidden input
    const hiddenInput = document.getElementById(`status-${empId}-${period}`);
    if(hiddenInput) hiddenInput.value = status;

    // ลบ selected จากปุ่มอื่นใน cell เดียวกัน
    const buttons = btn.parentElement.querySelectorAll('button.status-btn');
    buttons.forEach(b => b.classList.remove('selected'));

    // เพิ่ม selected ให้ปุ่มที่คลิก
    btn.classList.add('selected');

    // ดึงวันที่
    const attendDate = document.querySelector('input[name="attend_date"]').value;

    // AJAX ส่งไป update_status.php
    const formData = new FormData();
    formData.append('employee_id', empId);
    formData.append('period', period);
    formData.append('status', status);
    formData.append('attend_date', attendDate);

    fetch('update_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status !== 'success'){
            alert('อัปเดตไม่สำเร็จ: ' + (data.msg || 'เกิดข้อผิดพลาด'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
    });
}
