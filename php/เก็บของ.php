<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. การแจ้งเตือน SweetAlert2 ---
    <?php
    if (isset($_SESSION['alert'])) {
        echo "Swal.fire({
            toast: true,
            position: 'top-end',
            icon: '{$_SESSION['alert']['type']}',
            title: '{$_SESSION['alert']['message']}',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });";
        unset($_SESSION['alert']);
    }
    ?>

    // --- 2. ส่วนควบคุม Sidebar ---
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            main.classList.toggle('full-width');
        });
    }
    if (window.matchMedia('(max-width: 768px)').matches) {
        sidebar.classList.add('hidden');
        main.classList.add('full-width');
    }

    // --- 3. ส่วนควบคุมปุ่ม Export ---
    const pdfButton = document.getElementById('pdfButton');
    const excelButton = document.getElementById('excelButton');
    
    function buildExportUrl(baseUrl) {
        const params = new URLSearchParams(window.location.search);
        return `${baseUrl}?${params.toString()}`;
    }

    if (pdfButton) {
        pdfButton.addEventListener('click', () => window.open(buildExportUrl('export_payroll_pdf.php'), '_blank'));
    }
    if (excelButton) {
        excelButton.addEventListener('click', () => window.location.href = buildExportUrl('export_payroll_excel.php'));
    }
    
    // --- 4. ส่วนควบคุม Modal การส่งอีเมล ---
    const emailModal = document.getElementById('emailModal');
    const emailModalButton = document.getElementById('emailModalButton');

    if (emailModal && emailModalButton) {
        const sendEmailButton = document.getElementById('sendEmailButton');
        const recipientEmailInput = document.getElementById('recipientEmail');
        const emailStatus = document.getElementById('emailStatus');
        const closeModalBtn = document.getElementById('closeModalBtn');

        const openModal = () => {
            emailModal.style.display = 'flex';
            recipientEmailInput.value = '';
            emailStatus.innerHTML = '';
            sendEmailButton.disabled = false;
            emailModal.querySelector('input[value="pdf"]').checked = true;
            emailModal.querySelector('input[value="excel"]').checked = false;
        };

        const closeModal = () => { emailModal.style.display = 'none'; };

        emailModalButton.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal); 
        emailModal.addEventListener('click', (event) => {
            if (event.target === emailModal) closeModal();
        });

        if (sendEmailButton) {
            sendEmailButton.addEventListener('click', async function() {
                const email = recipientEmailInput.value.trim();
                if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                    emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                    return;
                }
                
                const selectedFormats = Array.from(emailModal.querySelectorAll('input[name="file_format"]:checked')).map(cb => cb.value);
                if (selectedFormats.length === 0) {
                    emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>';
                    return;
                }
                
                this.disabled = true;
                emailStatus.innerHTML = '<span style="color: var(--primary-color);">กำลังสร้างไฟล์และส่ง... <i class="fas fa-spinner fa-spin"></i></span>';

                const formData = new FormData();
                formData.append('email', email);
                selectedFormats.forEach(format => formData.append('file_formats[]', format));
                
                const params = new URLSearchParams(window.location.search);
                for (const [key, value] of params) {
                    formData.append(key, value);
                }

                try {
                    const response = await fetch('send_payroll_email.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                        setTimeout(closeModal, 2500);
                    } else {
                        emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message}</span>`;
                        this.disabled = false;
                    }
                } catch (error) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาดในการเชื่อมต่อกับ Server</span>`;
                    this.disabled = false;
                }
            });
        }
    }
});
</script>