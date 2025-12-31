<?php
session_start();

// Paramètres de connexion à la base de données
$host = 'localhost';
$db   = 'projet';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adresse = $_POST['adresse'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);

        // 1. Chercher dans admin
        $stmt = $pdo->prepare('SELECT * FROM admin WHERE `adresse` = ?');
        $stmt->execute([$adresse]);
        $admin = $stmt->fetch();

        if ($admin && $password === $admin['mot de passe']) {
            $_SESSION['user'] = [
                'adresse' => $admin['adresse'],
                'type' => 'admin',
                'role' => 'administrateur',
                'nom' => $admin['nom complet'] // <-- ajoute cette ligne
            ];
            header('Location: dashboard.php');
            exit;
        }

        // 2. Chercher dans user
        $stmt = $pdo->prepare('SELECT * FROM user WHERE `adresse` = ?');
        $stmt->execute([$adresse]);
        $user = $stmt->fetch();

        if ($user && $password === $user['mot de passe']) {
            $_SESSION['user'] = [
                'adresse' => $user['adresse'],
                'type' => 'user',
                'nom' => $user['nom complet']
            ];
            header('Location: user.php'); // <-- ici, redirige vers user.php
            exit;
        }

        // Si rien trouvé
        header('Location: login.html?error=Adresse%20e-mail%20ou%20mot%20de%20passe%20incorrect.');
        exit;

    } catch (PDOException $e) {
        header('Location: login.html?error=Erreur%20de%20connexion%20à%20la%20base%20de%20données.');
        exit;
    }
} else {
    header('Location: login.html');
    exit;
}
?>
<!-- ...HTML... -->
<input type="email" value="<?php echo htmlspecialchars($_SESSION['user']['adresse']); ?>" readonly>
<!-- ...HTML... -->