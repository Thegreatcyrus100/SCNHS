<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Result</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="admin/logo.png" alt="School Logo" class="left-logo">
        </div>
        <span>Santa Cruz National High School SHS - Registration Result</span>
        <div class="logo-container">
            <img src="admin/LOGO STA. CRUZ.png" alt="Sta. Cruz Logo" class="logo">
        </div>
        <nav>
            <ul class="nav-links">
                <li><a class="active" href="home.html">
                    <i class="fas fa-home"></i> Home
                </a></li>
            </ul>
        </nav>
    </header>
    <main>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
