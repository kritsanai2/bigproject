<style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --light-teal-bg: #eaf6f6;
        --navy-blue: #001f3f;
        --gold-accent: #fca311;
        --white: #ffffff;
        --light-gray: #f8f9fa;
        --gray-border: #ced4da;
        --text-color: #495057;
        --success: #2ecc71;
        --danger: #e74c3c;
        --warning: #f39c12;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        color: var(--text-color);
        padding: 20px;
    }

    .container-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        background: var(--white);
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        padding: 30px 40px;
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 20px;
        margin-bottom: 30px;
        gap: 1rem;
    }
    .logo {
        width: 70px; height: 70px; border-radius: 50%;
        object-fit: cover; border: 3px solid var(--gold-accent);
    }
    header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem; color: var(--navy-blue);
        margin: 0; font-weight: 700;
        display: flex; align-items: center; gap: 1rem;
    }
    .home-button {
        text-decoration: none; background-color: var(--primary-color); color: var(--white);
        padding: 10px 25px; border-radius: 50px; font-weight: 500;
        transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(52, 152, 219, 0.2);
        display: flex; align-items: center; gap: 8px;
    }
    .home-button:hover {
        background-color: #2980b9; transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
    }
    
    .container {
        background-color: var(--white);
        padding: 25px; border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .form-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background-color: var(--light-gray);
        border-radius: 12px;
    }
    .form-controls label { font-weight: 500; }
    .form-controls input[type="month"] {
        padding: 10px; border: 1px solid var(--gray-border);
        border-radius: 8px; font-size: 1rem; font-family: 'Sarabun', sans-serif;
    }
    .form-controls button {
        padding: 10px 25px; border: none; border-radius: 8px;
        font-size: 1rem; font-weight: 500; cursor: pointer; color: white;
        display: inline-flex; align-items: center; gap: 8px;
        transition: all 0.2s;
    }
    .form-controls button[name="calculate"] { background-color: var(--primary-color); }
    .form-controls button[name="calculate"]:hover { background-color: #2980b9; transform: translateY(-2px); }
    
    .save-button-container { text-align: center; margin-top: 2rem; }
    .save-button-container button {
        background-color: var(--success); color: white;
        padding: 12px 30px; font-size: 1.1rem; border-radius: 8px;
        border: none; cursor: pointer; font-weight: 500;
        display: inline-flex; align-items: center; gap: 8px;
        transition: all 0.2s;
    }
    .save-button-container button:hover { background-color: #27ae60; transform: translateY(-2px); }

    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        background-color: var(--navy-blue); color: var(--white);
        padding: 15px; text-align: center; font-size: 0.9rem;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    tbody td {
        padding: 15px; border-bottom: 1px solid #e0e0e0; color: #333; text-align: center;
    }
    tbody td:nth-child(3) { text-align: left; } /* Align name to left */
    tbody td:last-child { font-weight: bold; color: var(--primary-color); }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
</style>