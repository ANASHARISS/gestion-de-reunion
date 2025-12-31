<?php
session_start();

// Paramètres de connexion à la base de données
$host = 'localhost';
$db   = 'projet'; // <-- À remplacer par le nom de ta base
$user = 'root'; // Par défaut sous XAMPP
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$fullname || !$adresse || !$password || !$confirm) {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z]).{7,}$/', $password)) {
        $error = "Le mot de passe doit contenir au moins 7 caractères, une majuscule et une minuscule.";
    } else {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);

            // Vérifier si l'adresse existe déjà
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM user WHERE `adresse` = ?');
            $stmt->execute([$adresse]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Cette adresse e-mail est déjà utilisée.";
            } else {
                // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $plainPassword = $password; // Mot de passe en clair (non recommandé en production)
                $date = date('Y-m-d');
                $heure = date('H:i:s');
                $stmt = $pdo->prepare('INSERT INTO user (`nom complet`, `adresse`, `mot de passe`, `date inscription`, `heure inscritpion`) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$fullname, $adresse, $plainPassword, $date, $heure]);
                $success = "Inscription réussie ! Redirection...";
                header('Refresh:2; url=login.html'); // Redirige après 2 secondes
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription - MeetingPlanner</title>
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-title">Inscription</div>
        <form class="login-form" method="POST" action="register.php">
            <div class="input-group"><span class="input-emoji">👤</span><input type="text" name="fullname" placeholder="Nom complet" required></div>
            <div class="input-group"><span class="input-emoji">📧</span><input type="email" name="adresse" placeholder="Adresse e-mail" required></div>
            <div class="input-group"><span class="input-emoji">🔒</span><input type="password" name="password" placeholder="Mot de passe" required></div>
            <div class="input-group"><span class="input-emoji">🔒</span><input type="password" name="confirm" placeholder="Confirmer le mot de passe" required></div>
            <button type="submit">S'inscrire</button>
            <?php if ($error): ?>
                <div style="color: #d32f2f; margin-top: 10px;"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($success): ?>
                <div style="color: #388e3c; margin-top: 10px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
        </form>
        <div style="margin-top: 20px; text-align: center;">
            <a href="login.php" style="
                display: inline-block;
                padding: 10px 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-weight: 500;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" 
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.3)'">
                Déjà inscrit ? Se connecter
            </a>
        </div>
    </div>
</body>
</html>