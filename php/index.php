<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการโรงน้ำดื่มตากับยาย</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>

    <style>
        /* Global & Reset */
        :root {
            --primary-blue: #0A7EA4;
            --secondary-blue: #3A89C9;
            --light-blue: #D6EFFF;
            --text-dark: #1F2E3A;
            --text-light: #F0F4F8;
            --accent-orange: #FF7F50;
            --accent-yellow: #FFD700;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--light-blue), #A7D6F5);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 15px 30px;
            color: var(--text-dark);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-bottom: 2px solid var(--primary-blue);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        header .logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--text-dark);
            transition: transform 0.3s ease;
        }

        header .logo:hover {
            transform: scale(1.1) rotate(10deg);
        }

        header h1 {
            font-size: 2.2rem;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
            color: var(--primary-blue);
        }

        .logout-btn {
            text-decoration: none;
            color: var(--text-light);
            background: var(--accent-orange);
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #FF6347;
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
        }

        /* Navigation Menu */
        nav {
            background: var(--primary-blue);
            padding: 15px 0;
            border-bottom: 5px solid var(--secondary-blue);
        }

        nav ul {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        nav li {
            margin: 10px 15px;
        }

        nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-light);
            font-weight: 600;
            position: relative;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        nav a .menu-icon {
            font-size: 32px;
            transition: all 0.4s ease;
        }

        /* Hover Anime Effect */
        nav a:hover {
            transform: translateY(-15px) scale(1.2);
            color: var(--accent-yellow);
        }

        nav a:hover .menu-icon {
            transform: scale(1.5) rotate(15deg);
            filter: drop-shadow(0 0 8px var(--accent-yellow));
        }

        nav a span {
            margin-top: 5px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        /* Tooltip */
        nav a::after {
            content: attr(title);
            position: absolute;
            bottom: -35px;
            background: rgba(0,0,0,0.7);
            color: var(--text-light);
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 8px;
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: opacity 0.3s, transform 0.3s;
        }

        nav a:hover::after {
            opacity: 1;
            transform: translateY(0);
        }

        /* Main Section */
        main {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 50px 20px;
            animation: fadeInUp 1s ease-out;
        }

        .welcome {
            text-align: center;
            background: #fff;
            color: var(--text-dark);
            padding: 40px 30px;
            border-radius: 10px;
            border: 5px solid var(--text-dark);
            box-shadow: 10px 10px 0 var(--secondary-blue), 15px 15px 0 var(--accent-orange);
            margin-bottom: 50px;
            transition: all 0.3s ease;
        }

        .welcome:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 15px 15px 0 var(--secondary-blue), 20px 20px 0 var(--accent-orange);
        }

        .welcome h2 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.1);
        }

        .welcome p {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #555;
        }

        /* Main Image */
        main img {
            width: 100%;
            max-width: 800px;
            border-radius: 25px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            border: 5px solid #fff;
            transition: transform 0.5s ease, filter 0.5s ease, clip-path 0.5s ease;
            clip-path: polygon(0 20%, 100% 0, 100% 80%, 0% 100%);
        }

        main img:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
            clip-path: polygon(0 0, 100% 20%, 100% 100%, 0% 80%);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
        }

        /* Animation Keyframes */
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                padding: 15px;
                text-align: center;
            }
            header h1 {
                font-size: 1.8rem;
                margin: 10px 0;
            }
            .logout-btn {
                width: 100%;
                margin-top: 10px;
            }
            nav ul {
                flex-direction: column;
                align-items: center;
            }
            nav li {
                width: 100%;
                text-align: center;
                margin: 5px 0;
            }
            nav a {
                flex-direction: row;
                justify-content: center;
                gap: 10px;
                padding: 10px 0;
                width: 100%;
            }
            .welcome h2 {
                font-size: 1.8rem;
            }
            .welcome p {
                font-size: 1rem;
            }
            main img {
                max-width: 95%;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h1>ระบบจัดการโรงน้ำดื่มตากับยาย</h1>
        <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
    </header>
    <nav>
        <ul>
            <li><a href="dashboard.php" title="จัดการรายงาน"><i class="fas fa-chart-line menu-icon"></i> <span>จัดการรายงาน</span></a></li>
            <li><a href="customers.php" title="ข้อมูลลูกค้า"><i class="fas fa-user-friends menu-icon"></i> <span>ข้อมูลลูกค้า</span></a></li>
            <li><a href="products.php" title="ข้อมูลสินค้า"><i class="fas fa-box-open menu-icon"></i> <span>ข้อมูลสินค้า</span></a></li>
            <li><a href="stock.php" title="ข้อมูลสต็อกสินค้า"><i class="fas fa-warehouse menu-icon"></i> <span>ข้อมูลสต็อกสินค้า</span></a></li>
            <li><a href="transactions.php" title="ข้อมูลรายรับ-รายจ่าย"><i class="fas fa-money-bill-wave menu-icon"></i> <span>ข้อมูลรายรับ-รายจ่าย</span></a></li>
            <li><a href="employees.php" title="ข้อมูลพนักงาน"><i class="fas fa-user-tie menu-icon"></i> <span>ข้อมูลพนักงาน</span></a></li>
            <li><a href="orders.php" title="ข้อมูลคำสั่งซื้อ"><i class="fas fa-shopping-cart menu-icon"></i> <span>ข้อมูลคำสั่งซื้อ</span></a></li>
        </ul>
    </nav>
    <main>
        <section class="welcome">
            <h2>ยินดีต้อนรับ!</h2>
            <p>เข้าสู่ระบบจัดการสำหรับโรงน้ำดื่มตากับยาย จัดการข้อมูลลูกค้า คำสั่งซื้อ และอื่น ๆ ได้อย่างสะดวก</p>
        </section>
        <img src="../img/wt.jpg" alt="ภาพโรงน้ำดื่ม" />
    </main>
</body>
</html>