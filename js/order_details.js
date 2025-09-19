function openAddModal(){document.getElementById('addModal').style.display='flex';}
function closeAddModal(){document.getElementById('addModal').style.display='none';}
function openEditModal(id,qty){
    document.getElementById('edit_detail_id').value=id;
    document.getElementById('edit_quantity').value=qty;
    document.getElementById('editModal').style.display='flex';
}
function closeEditModal(){document.getElementById('editModal').style.display='none';}
window.onclick=function(e){
    if(e.target==document.getElementById('addModal')) closeAddModal();
    if(e.target==document.getElementById('editModal')) closeEditModal();
}