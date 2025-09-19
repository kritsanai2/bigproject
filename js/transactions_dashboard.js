function toggleSidebar(){
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('closed');
    const main = document.querySelector('.main');
    main.style.marginLeft = sidebar.classList.contains('closed') ? '0' : '220px';
}