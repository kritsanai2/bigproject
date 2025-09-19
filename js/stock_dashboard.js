function toggleSidebar(){
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('closed');

    const main = document.querySelector('.main');
    if(sidebar.classList.contains('closed')){
        main.style.marginLeft = '0';
    } else {
        main.style.marginLeft = '220px';
    }
}
