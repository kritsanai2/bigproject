function openAddModal(){ document.getElementById('add-modal').style.display='flex'; }
function closeAddModal(){ document.getElementById('add-modal').style.display='none'; }
function openEditModal(id,amount,date,expenseType){
    document.getElementById('edit-id').value=id;
    document.getElementById('edit-amount').value=amount;
    document.getElementById('edit-date').value=date;
    document.getElementById('edit-expense-type').value=expenseType;
    document.getElementById('edit-modal').style.display='flex';
}
function closeEditModal(){ document.getElementById('edit-modal').style.display='none'; }

function searchTransaction(){
    var input=document.getElementById('search-transaction').value.toLowerCase();
    var rows=document.querySelectorAll('table tbody tr');
    rows.forEach(row=>{
        var cells=row.getElementsByTagName('td'); var match=false;
        for(var j=0;j<cells.length-1;j++){
            if(cells[j].textContent.toLowerCase().indexOf(input)>-1){ match=true; break; }
        }
        row.style.display=match?'':'none';
    });
}
function toggleSidebar() {
  document.querySelector(".sidebar").classList.toggle("closed");
}
