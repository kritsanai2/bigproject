const employees = Array.from({ length: 10 }, (_, i) => ({
    id: (i + 1).toString().padStart(3, "0"),
    name: `พนักงาน ${i + 1}`
  }));
  
  /* ===== สถานะให้เลือก ===== */
  const statusOptions = [
    { value: "",        label: "-"        },
    { value: "present", label: "มา"       },
    { value: "late",    label: "สาย"      },
    { value: "leave",   label: "ลา"       },
    { value: "absent",  label: "ขาด"      }
  ];
  
  /* ===== สร้าง dropdown สถานะ 1 ชุด ===== */
  function createStatusSelect(nameAttr) {
    const sel = document.createElement("select");
    sel.name = nameAttr;
    statusOptions.forEach(opt => {
      const o = document.createElement("option");
      o.value = opt.value;
      o.textContent = opt.label;
      sel.appendChild(o);
    });
    return sel;
  }
  
  /* ===== สร้างหัวตารางตามจำนวนวัน ===== */
  function buildHeaderRow(totalDays) {
    const tr = document.createElement("tr");
    tr.innerHTML = `<th>รหัส</th><th>ชื่อ</th>`;
    for (let d = 1; d <= totalDays; d++) {
      const th = document.createElement("th");
      th.textContent = d;
      tr.appendChild(th);
    }
    return tr;
  }
  
  /* ===== สร้างแถวพนักงาน ===== */
  function buildEmployeeRow(emp, monthKey, totalDays) {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td>${emp.id}</td><td>${emp.name}</td>`;
    for (let d = 1; d <= totalDays; d++) {
      const td = document.createElement("td");
      const nameAttr = `emp${emp.id}_${monthKey}_${d}`;
      td.appendChild(createStatusSelect(nameAttr));
      tr.appendChild(td);
    }
    return tr;
  }
  
  /* ===== render ตารางเต็ม ===== */
  function renderTable(monthKey) {
    const table = document.getElementById("attendanceTable");
    table.innerHTML = "";              // เคลียร์ของเก่า
    const totalDays = 30;              // ตามโจทย์: 30 วันทุกเดือน
    /* สร้างหัว */
    table.appendChild(buildHeaderRow(totalDays));
    /* สร้าง 50 แถว */
    employees.forEach(emp =>
      table.appendChild(buildEmployeeRow(emp, monthKey, totalDays))
    );
  }
  
  /* ===== event: เปลี่ยนเดือน ===== */
  document.getElementById("monthSelect").addEventListener("change", e => {
    renderTable(e.target.value);       // v เช่น "2025-04"
  });
  
  /* ===== โหลดครั้งแรก ===== */
  renderTable(document.getElementById("monthSelect").value);
  
  /* ===== ปุ่มบันทึก (ตัวอย่าง) ===== */
  document.getElementById("saveBtn").addEventListener("click", () => {
    // เก็บข้อมูลฟอร์มทั้งหมด
    const formData = new FormData(document.querySelector("form"));
    // แสดงจำนวนช่องที่กรอกในตัวอย่าง (จริง ๆ ส่งไป back-end ก็ได้)
    alert(`บันทึกข้อมูลเรียบร้อย: ${formData.entries().next().done ? 0 : [...formData].length} ช่อง`);
  });
  