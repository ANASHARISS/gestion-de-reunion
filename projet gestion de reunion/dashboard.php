<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['type'] !== 'admin') {
    header('Location: login.html');
    exit;
}
$nom = isset($_SESSION['user']['nom']) ? htmlspecialchars($_SESSION['user']['nom']) : '';
$email = isset($_SESSION['user']['adresse']) ? htmlspecialchars($_SESSION['user']['adresse']) : '';

// Connexion à la base
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

$pwd_message = '';
$pwd_success = false;

if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $pwd_message = "Veuillez remplir tous les champs.";
    } elseif ($new !== $confirm) {
        $pwd_message = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z]).{7,}$/', $new)) {
        $pwd_message = "Le nouveau mot de passe doit contenir au moins 7 caractères, une majuscule et une minuscule.";
    } else {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $stmt = $pdo->prepare('SELECT `mot de passe`, `nom complet` FROM admin WHERE `adresse` = ?');
            $stmt->execute([$_SESSION['user']['adresse']]);
            $row = $stmt->fetch();
            if (!$row || $row['mot de passe'] !== $current) {
                $pwd_message = "L'ancien mot de passe est incorrect.";
            } else {
                $stmt = $pdo->prepare('UPDATE admin SET `mot de passe` = ? WHERE `adresse` = ?');
                $stmt->execute([$new, $_SESSION['user']['adresse']]);
                $pwd_message = "Mot de passe changé avec succès.";
                $pwd_success = true;
            }
            if ($row && isset($row['nom complet'])) {
                $_SESSION['user']['nom'] = $row['nom complet'];
            }
        } catch (PDOException $e) {
            $pwd_message = "Erreur lors du changement : " . $e->getMessage();
        }
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $stmt = $pdo->query('SELECT COUNT(*) FROM user');
    $nb_users = $stmt->fetchColumn();
} catch (PDOException $e) {
    $nb_users = 0;
}

if (isset($_POST['delete_user'])) {
    $adresse = $_POST['delete_user'];
    $stmt = $pdo->prepare('DELETE FROM user WHERE `adresse` = ?');
    $stmt->execute([$adresse]);
    header('Location: dashboard.php#tableau-admin');
    exit;
}

// 2. Annulation de réunion (remplace suppression) :
if (isset($_POST['delete_meeting'])) {
    $id = $_POST['delete_meeting'];
    $stmt = $pdo->prepare('UPDATE reunion SET statut = ? WHERE id = ?');
    $stmt->execute(['annulée', $id]);
    header('Location: dashboard.php#tableau-admin');
    exit;
}

// 1. Création de réunion :
if (isset($_POST['create_meeting'])) {
    $titre = $_POST['titre'] ?? '';
    $date = $_POST['date'] ?? '';
    $heure = $_POST['heure'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    if ($titre && $date && $heure && $type) {
        $stmt = $pdo->prepare('INSERT INTO reunion (titre, date, heure, type, statut, description) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$titre, $date, $heure, $type, 'active', $description]);
        header('Location: dashboard.php#tableau-admin');
        exit;
    }
}

if (isset($_POST['add_user'])) {
    $nom = trim($_POST['new_nom'] ?? '');
    $email = trim($_POST['new_email'] ?? '');
    $role = trim($_POST['new_role'] ?? '');
    $password = $_POST['new_password'] ?? '';
    if ($nom && $email && $role && $password) {
        $stmt = $pdo->prepare('INSERT INTO user (`nom complet`, `adresse`, `role`, `mot de passe`) VALUES (?, ?, ?, ?)');
        $stmt->execute([$nom, $email, $role, $password]);
        header('Location: dashboard.php#centre-admin');
        exit;
    }
}

// 6. Statistiques :
$stat_total_reunions = $pdo->query('SELECT COUNT(*) FROM reunion')->fetchColumn();
$stat_reunions_actives = $pdo->query("SELECT COUNT(*) FROM reunion WHERE statut = 'active'")->fetchColumn();
$stat_reunions_annulees = $pdo->query("SELECT COUNT(*) FROM reunion WHERE statut = 'annulée'")->fetchColumn();
$stat_taux_annulation = $stat_total_reunions > 0 ? round(($stat_reunions_annulees / $stat_total_reunions) * 100) : 0;

// Total utilisateurs
$stat_total_utilisateurs = $pdo->query('SELECT COUNT(*) FROM user')->fetchColumn();
?>
<?php
// Statistiques avancées pour les graphiques
$stmt = $pdo->query('SELECT date, type, titre, statut FROM reunion ORDER BY date DESC, heure DESC');
$reunions_stats = $stmt->fetchAll();

// Réunions par mois

// FAUX GRAPHIQUES : données factices
$reunions_par_mois = [
    '2025-06' => 2,
    '2025-07' => 1,
    '2025-08' => 2,
    '2025-09' => 4
];

// Types de réunion
$types_reunion = [
    'En ligne' => 3,
    'Présentiel' => 2,
    'Mixte' => 4
];

// Réunions par jour de la semaine
$jours_reunion = [
    'Lundi' => 3,
    'Mardi' => 2,
    'Mercredi' => 1,
    'Jeudi' => 3,
    'Vendredi' => 3,
    'Samedi' => 3,
    'Dimanche' => 4
];

// Top 5 réunions récentes
$stmt = $pdo->query('SELECT titre, type, date, statut FROM reunion ORDER BY date DESC, heure DESC LIMIT 5');
$reunions_recentes = $stmt->fetchAll();
?>
<?php
// Gestion des messages SMS (version admin)
$sms_message = '';
if (isset($_POST['send_sms'])) {
    $msg = trim($_POST['sms_message'] ?? '');
    if ($msg) {
        $stmt = $pdo->prepare('INSERT INTO sms (expediteur, message) VALUES (?, ?)');
        $stmt->execute([$email, $msg]);
        $sms_message = 'Message envoyé !';
    } else {
        $sms_message = 'Veuillez écrire un message.';
    }
}
$stmt = $pdo->query('SELECT * FROM sms ORDER BY date_envoi DESC LIMIT 30');
$sms_list = $stmt->fetchAll();
// Suppression d'un message SMS
if (isset($_POST['delete_sms'])) {
    $sms_id = intval($_POST['delete_sms']);
    $stmt = $pdo->prepare('DELETE FROM sms WHERE id = ?');
    $stmt->execute([$sms_id]);
    header('Location: dashboard.php#sms');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetingPlanner - Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Statut réunion : utile, conservé */
        .statut-active { color: #22c55e !important; font-weight: bold; }
        .statut-annulee { color: #ef4444 !important; font-weight: bold; }
        /* Suppression de tout autre CSS redondant ou inutile :
           - Les marges, paddings, backgrounds, box-shadows, border-radius, etc. sont déjà gérés dans dashboard.css ou inline.
           - Les styles des containers graphiques, titres, cards, etc. sont gérés ailleurs.
           - Les styles d'icônes, boutons, etc. sont gérés par FontAwesome ou dashboard.css. */
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">MeetingPlanner</span>
                <button class="btn-create" id="open-create-modal"><i class="fa fa-plus"></i> Créer</button>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#" class="active" data-section="accueil"><i class="fa fa-home"></i> Accueil</a></li>
                <li><a href="#" data-section="planification"><i class="fa fa-calendar-check"></i> Planification</a></li>
                <li><a href="#" data-section="reunions"><i class="fa fa-handshake"></i> Réunions</a></li>
                <li><a href="#" data-section="centre-admin"><i class="fa fa-user-shield"></i> Centre admin</a></li>
                <li><a href="#" data-section="tableau-admin"><i class="fa fa-users"></i> Tableau de bord admin</a></li>
                <li><a href="#" data-section="statistiques"><i class="fa fa-chart-bar"></i> Statistiques</a></li>
                    <li><a href="#" data-section="sms"><i class="fa fa-comments"></i> SMS</a></li>
                <li><a href="#" data-section="aide"><i class="fa fa-question-circle"></i> Aide</a></li>
                <li class="deconnexion-item">
                <form method="post" action="logout.php" style="display:inline;">
                    <button type="submit" style="background:none; border:none; color:inherit; font:inherit; cursor:pointer;">
                    <i class="fa fa-sign-out-alt"></i> Déconnexion
                    </button>
                </form>
                </li>
            </ul>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <span class="main-title">Application de planification des réunions</span>
                <div class="main-profile">
                    <span class="profile-icon" style="background:linear-gradient(135deg,#dc2626,#f87171); width:60px; height:60px; display:flex; align-items:center; justify-content:center; border-radius:50%; box-shadow:0 4px 16px rgba(220,38,38,0.13); font-size:2.3em; border:3px solid #fff; margin-right:10px;">
                        <i class="fa fa-user-shield" style="color:#fff; font-size:1.2em;"></i>
                    </span>
                    <span class="settings-icon" id="go-settings" style="margin-left:18px; cursor:pointer; font-size:1.3em; color:#dc2626;" title="Paramètres"><i class="fa fa-cog"></i></span>
                </div>
            </header>
                <!-- SMS -->
                <section id="sms" class="section">
                    <div class="welcome-card">
                        <h2><i class="fa fa-comments"></i> Messagerie SMS</h2>
                        <form method="post" action="dashboard.php#sms" style="margin-bottom:18px; display:flex; gap:12px;">
                            <input type="text" name="sms_message" placeholder="Votre message..." style="flex:1; padding:8px 12px; border-radius:8px; border:1.5px solid #dc2626;" required>
                            <button type="submit" name="send_sms" class="btn-create" style="padding:8px 24px;"><i class="fa fa-paper-plane"></i> Envoyer</button>
                        </form>
                        <?php if ($sms_message) { ?>
                            <div style="margin-bottom:12px; color:#dc2626; font-weight:bold;"> <?= htmlspecialchars($sms_message) ?> </div>
                        <?php } ?>
                        <div style="max-height:320px; overflow-y:auto; background:#f1f5f9; border-radius:10px; padding:18px;">
                                <?php if (empty($sms_list)) { ?>
                                    <div style="color:#64748b;">Aucun message.</div>
                                <?php } else { foreach ($sms_list as $sms) { ?>
                                    <div style="margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #e0e7ef; display:flex; align-items:center; justify-content:space-between;">
                                        <div style="flex:1;">
                                            <div style="font-weight:bold; color:#dc2626;"><i class="fa fa-user"></i> <?= htmlspecialchars($sms['expediteur']) ?></div>
                                            <div style="margin:6px 0 2px 0; color:#222;"> <?= nl2br(htmlspecialchars($sms['message'])) ?> </div>
                                            <div style="font-size:0.9em; color:#64748b;"> <?= date('d/m/Y H:i', strtotime($sms['date_envoi'])) ?> </div>
                                        </div>
                                        <form method="post" action="dashboard.php#sms" style="margin-left:12px;">
                                            <input type="hidden" name="delete_sms" value="<?= $sms['id'] ?>">
                                            <button type="submit" style="background:none; border:none; color:#ef4444; font-size:1.2em; cursor:pointer;" title="Supprimer" onclick="return confirm('Supprimer ce message ?');"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </div>
                                <?php }} ?>
                        </div>
                    </div>
                </section>
            <!-- Accueil -->
            <section id="accueil" class="section active">
                <div class="welcome-card">
                    <h1>Bienvenue sur MeetingPlanner !</h1>
                    <p>Planifiez, organisez et suivez toutes vos réunions professionnelles en un seul endroit.</p>
                    <div style="margin-top:32px;">
                        <button class="btn-create" id="open-create-modal-accueil"><i class="fa fa-plus"></i> Planifier une réunion</button>
                        <button class="btn-copy" style="margin-left:18px;">Voir le planning</button>
                    </div>
                    <div style="display:flex; gap:24px; margin-top:32px;">
                        <div class="stats-card" style="flex:1;">
                            <div style="font-size:1.2em; color:#dc2626; margin-bottom:8px;"><i class="fa fa-calendar-check"></i></div>
                            <div style="font-size:1.1em; font-weight:bold; color:#dc2626;">Réunions créées</div>
                            <div style="font-size:1.8em; font-weight:bold; color:#b91c1c;">0</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Ce mois</div>
                        </div>
                        <div class="stats-card" style="flex:1;">
                            <div style="font-size:1.2em; color:#dc2626; margin-bottom:8px;"><i class="fa fa-clock"></i></div>
                            <div style="font-size:1.1em; font-weight:bold; color:#dc2626;">À venir</div>
                            <div style="font-size:1.8em; font-weight:bold; color:#b91c1c;">0</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Cette semaine</div>
                        </div>
                        <div class="stats-card" style="flex:1;">
                            <div style="font-size:1.2em; color:#dc2626; margin-bottom:8px;"><i class="fa fa-users"></i></div>
                            <div style="font-size:1.1em; font-weight:bold; color:#dc2626;">Participants</div>
                            <div style="font-size:1.8em; font-weight:bold; color:#b91c1c;">0</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Total</div>
                        </div>
                    </div>
                    <div class="stats-card" style="margin-top:24px;">
                        <div style="font-size:1.1em; font-weight:bold; color:#dc2626; margin-bottom:12px;"><i class="fa fa-history"></i> Dernière activité</div>
                        <div style="color:#64748b; line-height:1.5;">
                            <div>Aucune activité récente</div>
                        </div>
                    </div>
                    <div style="display:flex; gap:16px; margin-top:24px;">
                        <button class="btn-copy" style="flex:1;"><i class="fa fa-calendar"></i> Voir le calendrier</button>
                        <button class="btn-copy" style="flex:1;"><i class="fa fa-history"></i> Historique</button>
                        <button class="btn-copy" style="flex:1;"><i class="fa fa-chart-bar"></i> Statistiques</button>
                    </div>
                </div>
            </section>
            <!-- Planification -->
            <section id="planification" class="section">
                <div class="planif-header">
                    <h2>Planification <span class="info-icon" title="Gérez vos réunions à venir"><i class="fa fa-question-circle"></i></span></h2>
                    <input type="text" class="search-bar" placeholder="Rechercher une réunion à venir">
                </div>
                <div style="margin-bottom:24px;">
                    <button class="btn-create" id="open-create-modal-planif"><i class="fa fa-plus"></i> Créer une réunion</button>
                </div>
                <div class="meeting-list">
                    <!-- Les réunions créées s'afficheront ici dynamiquement -->
                </div>
            </section>
            <!-- Réunions -->
            <section id="reunions" class="section">
                <div class="welcome-card">
                    <h2>Historique des réunions</h2>
                    <table style="width:100%; border-collapse:collapse; margin-top:18px;">
                        <thead>
                            <tr style="background:#fff1f2; color:#dc2626;">
                                <th style="padding:10px 8px; text-align:left;">Titre</th>
                                <th style="padding:10px 8px; text-align:left;">Date</th>
                                <th style="padding:10px 8px; text-align:left;">Statut</th>
                                <th style="padding:10px 8px; text-align:left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // 3. Sélectionne le champ statut dans tous les SELECT
                            $stmtMeetings = $pdo->query('SELECT titre, date, heure, type, description, statut FROM reunion ORDER BY date DESC, heure DESC');
                            $reunions = $stmtMeetings->fetchAll();
                            if (count($reunions) === 0) {
                                echo '<tr><td colspan="4" style="padding:12px 10px; color:#64748b;">Aucune réunion à afficher</td></tr>';
                            } else {
                                foreach ($reunions as $r) {
                                    echo '<tr style="border-bottom:1px solid #f3f4f6;">';
                                    echo '<td style="padding:12px 10px;">'.htmlspecialchars($r["titre"]).'</td>';
                                    echo '<td style="padding:12px 10px;">'.htmlspecialchars($r["date"]).' '.htmlspecialchars($r["heure"]).'</td>';
                                    // 4. Affichage du statut dans les tableaux
                                    if (isset($r["statut"]) && $r["statut"] === 'annulée') {
                                        echo '<td style="padding:12px 10px;"><span class="statut-annulee">Annulée</span></td>';
                                    } else {
                                        echo '<td style="padding:12px 10px;"><span class="statut-active">Active</span></td>';
                                    }
                                    echo '<td style="padding:12px 10px;">'
                                        . '<a href="#" class="meeting-link" onclick=\'showMeetingDetails(' . json_encode([
                                            "titre" => $r["titre"],
                                            "date" => $r["date"],
                                            "heure" => $r["heure"],
                                            "type" => $r["type"],
                                            "description" => $r["description"]
                                        ]) . '); return false;\'>Voir les détails</a>'
                                        . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <!-- Centre admin -->
            <section id="centre-admin" class="section">
                <div class="welcome-card">
                    <h2>Gestion des utilisateurs</h2>
                    <table style="width:100%; border-collapse:collapse; margin-top:18px;">
                        <thead>
                            <tr style="background:#fff1f2; color:#dc2626;">
                                <th style="padding:10px 8px; text-align:left;">Nom</th>
                                <th style="padding:10px 8px; text-align:left;">Email</th>
                                <th style="padding:10px 8px; text-align:left;">mot de passe</th>
                                <th style="padding:10px 8px; text-align:left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $pdo->query('SELECT `nom complet`, `adresse`,`mot de passe` FROM user');
                                $rows = $stmt->fetchAll(); // Récupère toutes les lignes d'un coup pour le debug
                                echo '';

                                // Maintenant, bouclez sur $rows pour afficher
                                foreach ($rows as $u) {
                                    echo '<tr style="border-bottom:1px solid #f3f4f6;">';
                                    echo '<td style="padding:12px 10px;">'.htmlspecialchars($u['nom complet']).'</td>';
                                    echo '<td style="padding:12px 10px;">'.htmlspecialchars($u['adresse']).'</td>';
                                    echo '<td style="padding:12px 10px;">'.htmlspecialchars($u['mot de passe']).'</td>';
                                    echo '<td style="padding:12px 10px;">
                                        <form method="post" style="display:inline;" onsubmit="return confirm(\'Supprimer ce compte ?\');">
                                            <input type="hidden" name="delete_user" value="'.htmlspecialchars($u['adresse']).'">
                                            <button type="submit" style="color:#fff; background:#dc2626; border:none; border-radius:6px; padding:7px 16px; font-size:1em; cursor:pointer;"><i class="fa fa-trash"></i> Supprimer</button>
                                        </form>
                                    </td>';
                                    echo '</tr>';
                                }
                            } catch (PDOException $e) {
                                echo '';
                            }
                            ?>
                        </tbody>
                    </table>
                    <div style="margin-top:24px;">
                        <button class="btn-create" id="btn-ajouter-user"><i class="fa fa-user-plus"></i> Ajouter un utilisateur</button>
                    </div>
                    <!-- Formulaire d'ajout d'utilisateur -->
                    <div id="form-ajout-user-container" style="display:none; margin-top:24px;">
                    <form method="post" action="dashboard.php#centre-admin" id="form-ajout-user" style="display:flex; gap:12px; flex-wrap:wrap; background:#fff; border-radius:10px; padding:18px; box-shadow:0 2px 8px #f8717122;">
                        <input type="text" name="new_nom" id="user-nom" placeholder="Nom complet" required style="flex:1; padding:8px 12px; border-radius:8px; border:1.5px solid #ef4444; background:#fffafa;">
                        <input type="email" name="new_email" id="user-email" placeholder="Email" required style="flex:1; padding:8px 12px; border-radius:8px; border:1.5px solid #ef4444; background:#fffafa;">
                        <select name="new_role" id="user-role" required style="flex:1; padding:8px 12px; border-radius:8px; border:1.5px solid #ef4444; background:#fffafa;">
                            <option value="">Rôle</option>
                            <option value="Utilisateur">Utilisateur</option>
                            <option value="Admin">Admin</option>
                        </select>
                        <input type="password" name="new_password" id="user-password" placeholder="Mot de passe" required style="flex:1; padding:8px 12px; border-radius:8px; border:1.5px solid #ef4444; background:#fffafa;">
                        <button type="submit" name="add_user" class="btn-create" style="background:#ef4444; color:#fff; border:none; border-radius:8px; padding:8px 24px; font-weight:600;"><i class="fa fa-user-plus"></i> Ajouter</button>
                    </form>
                </div>
                </div>
            </section>
            <!-- Tableau admin -->
            <section id="tableau-admin" class="section">
                <div class="welcome-card">
                    <h2>Tableau de bord admin</h2>
                    <div style="display:flex; gap:32px; margin-top:32px;">
                        <div style="background:#fff1f2; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #f8717122;">
                            <div style="font-size:2em; color:#dc2626;"><i class="fa fa-users"></i></div>
                            <div style="font-size:1.3em; font-weight:bold; cursor:pointer;" id="show-users-table"><?php echo $nb_users; ?></div>
                            <div>Utilisateurs inscrits</div>
                        </div>
                        <div style="background:#fff1f2; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #f8717122;">
                            <div style="font-size:2em; color:#dc2626;"><i class="fa fa-calendar-check"></i></div>
                            <div style="font-size:1.3em; font-weight:bold;">
                                <?php
                                $stmt = $pdo->query('SELECT COUNT(*) FROM reunion');
                                echo $stmt->fetchColumn();
                                ?>
                            </div>
                            <div>Réunions créées</div>
                        </div>
                        <div style="background:#fff1f2; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #f8717122;">
                            <div style="font-size:2em; color:#dc2626;"><i class="fa fa-chart-bar"></i></div>
                            <div style="font-size:1.3em; font-weight:bold;">0%</div>
                            <div>Taux de participation</div>
                        </div>
                    </div>
                    <div style="margin-top:24px; display:flex; gap:16px;">
                        <button id="btn-show-users" class="btn-create" style="flex:1;"><i class="fa fa-users"></i> Voir les utilisateurs</button>
                        <button id="btn-show-meetings" class="btn-create" style="flex:1;"><i class="fa fa-calendar-check"></i> Voir les réunions</button>
                    </div>
                    <!-- Tableau utilisateurs -->
                    <div id="users-table-accordion" style="display:none; background:#fff; border-radius:14px; box-shadow:0 2px 8px #f8717122; padding:32px; margin:32px 0 0 0;">
                        <h3 style="color:#dc2626; font-size:1.5em; margin-bottom:18px;"><i class="fa fa-users"></i> Comptes utilisateurs</h3>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; font-size:1.15em;">
                                <thead>
                                    <tr style="background:#fff1f2; color:#dc2626;">
                                        <th style="padding:14px 10px; text-align:left;">Nom</th>
                                        <th style="padding:14px 10px; text-align:left;">Email</th>
                                        <th style="padding:14px 10px; text-align:left;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->query('SELECT `nom complet`, `adresse` FROM user');
                                    while ($u = $stmt->fetch()) {
                                        echo '<tr style="border-bottom:1px solid #f3f4f6;">';
                                        echo '<td style="padding:12px 10px;">'.htmlspecialchars($u['nom complet']).'</td>';
                                        echo '<td style="padding:12px 10px;">'.htmlspecialchars($u['adresse']).'</td>';
                                        echo '<td style="padding:12px 10px;">
                                            <form method="post" style="display:inline;" onsubmit="return confirm(\'Supprimer ce compte ?\');">
                                                <input type="hidden" name="delete_user" value="'.htmlspecialchars($u['adresse']).'">
                                                <button type="submit" style="color:#fff; background:#dc2626; border:none; border-radius:6px; padding:7px 16px; font-size:1em; cursor:pointer;"><i class="fa fa-trash"></i> Supprimer</button>
                                            </form>
                                        </td>';
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tableau réunions -->
                    <div id="meetings-table-accordion" style="display:none; background:#fff; border-radius:14px; box-shadow:0 2px 8px #f8717122; padding:32px; margin:32px 0 0 0;">
                        <h3 style="color:#dc2626; font-size:1.5em; margin-bottom:18px;"><i class="fa fa-calendar-check"></i> Réunions</h3>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; font-size:1.15em;">
                                <thead>
                                    <tr style="background:#fff1f2; color:#dc2626;">
                                        <th style="padding:14px 10px; text-align:left;">Titre</th>
                                        <th style="padding:14px 10px; text-align:left;">Date</th>
                                        <th style="padding:14px 10px; text-align:left;">Type</th>
                                        <th style="padding:14px 10px; text-align:left;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-meetings-tbody">
                                    <?php
                                    // 3. Sélectionne le champ statut dans tous les SELECT
                                    $stmt = $pdo->query('SELECT id, titre, date, heure, type, description, statut FROM reunion ORDER BY date DESC, heure DESC');
                                        $reunions = $stmt->fetchAll();
                                        $reunions_actives = array_filter($reunions, function($r) { return $r['statut'] !== 'annulée'; });
                                        if (count($reunions_actives) === 0) {
                                            echo '<tr><td colspan="4" style="padding:12px 10px; color:#64748b;">Aucune réunion à afficher</td></tr>';
                                        } else {
                                            foreach ($reunions_actives as $r) {
                                                echo '<tr style="border-bottom:1px solid #f3f4f6;">';
                                                echo '<td style="padding:12px 10px;">'.htmlspecialchars($r["titre"]).'</td>';
                                                echo '<td style="padding:12px 10px;">'.htmlspecialchars($r["date"]).' '.htmlspecialchars($r["heure"]).'</td>';
                                                echo '<td style="padding:12px 10px;">Active</td>';
                                                echo '<td style="padding:12px 10px;">'
                                                    . '<form method="post" style="display:inline;" onsubmit="return confirm(\'Supprimer cette réunion ?\');">'
                                                    . '<input type="hidden" name="delete_meeting" value="'.htmlspecialchars($r["id"]).'">'
                                                    . '<button type="submit" style="color:#fff; background:#dc2626; border:none; border-radius:6px; padding:7px 16px; font-size:1em; cursor:pointer;"><i class="fa fa-trash"></i> Supprimer</button>'
                                                    . '</form>'
                                                    . '<button type="button" class="btn-copy" onclick=\'showMeetingDetails(' . json_encode([
                                                        "titre" => $r["titre"],
                                                        "date" => $r["date"],
                                                        "heure" => $r["heure"],
                                                        "type" => $r["type"],
                                                        "description" => $r["description"]
                                                    ]) . ')\'><i class="fa fa-eye"></i> Voir</button>'
                                                    . '</td>';
                                                echo '</tr>';
                                            }
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <section id="statistiques" class="section">
                <div class="welcome-card">
                    <h2>Statistiques</h2>
                    
                    <!-- Statistiques générales -->
                    <div style="display:flex; gap:24px; margin-top:24px; flex-wrap:wrap;">
                        <div style="background:#fffbe6; border-radius:14px; padding:20px 24px; box-shadow:0 2px 8px #fbbf2422; flex:1; min-width:200px;">
                            <div style="font-size:1.1em; color:#64748b; margin-bottom:8px;">Total réunions</div>
                            <div style="font-size:2em; font-weight:bold; color:#f59e42;" id="stat-total-reunions">0</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Toutes réunions confondues</div>
                            <script>
                            function renderStatistiques() {
                                document.getElementById('stat-total-reunions').textContent = "<?php echo $stat_total_reunions; ?>";
                                document.getElementById('stat-reunions-actives').textContent = "<?php echo $stat_reunions_actives; ?>";
                                document.getElementById('stat-taux-annulation').textContent = "<?php echo $stat_taux_annulation; ?>%";
                                document.getElementById('stat-total-utilisateurs').textContent = "<?php echo $stat_total_utilisateurs; ?>";

                                // Helper pour barres rouges et labels
                                function createBar(val, labelText) {
                                    var barWrapper = document.createElement('div');
                                    barWrapper.style.display = 'flex';
                                    barWrapper.style.flexDirection = 'column';
                                    barWrapper.style.alignItems = 'center';
                                    barWrapper.style.marginRight = '12px';
                                    // Barre
                                    var bar = document.createElement('div');
                                    bar.style.height = (val > 0 ? (val*18+20) : 20) + 'px';
                                    bar.style.width = '32px';
                                    bar.style.background = '#ef4444';
                                    bar.style.borderRadius = '6px 6px 0 0';
                                    bar.style.display = 'flex';
                                    bar.style.alignItems = 'flex-end';
                                    bar.style.justifyContent = 'center';
                                    bar.style.position = 'relative';
                                    bar.title = labelText;
                                    barWrapper.appendChild(bar);
                                    // Valeur
                                    var value = document.createElement('div');
                                    value.textContent = val;
                                    value.style.fontSize = '1em';
                                    value.style.color = '#ef4444';
                                    value.style.marginTop = '4px';
                                    barWrapper.appendChild(value);
                                    // Label
                                    var label = document.createElement('div');
                                    label.textContent = labelText;
                                    label.style.fontSize = '0.9em';
                                    label.style.color = '#b91c1c';
                                    label.style.marginTop = '2px';
                                    barWrapper.appendChild(label);
                                    return barWrapper;
                                }

                                // Réunions par mois
                                var reunionsParMois = <?php echo json_encode($reunions_par_mois); ?>;


                                var graphMois = document.getElementById('graphique-mois');
                                graphMois.innerHTML = '';
                                // Ajoute une marge au titre parent
                                if (graphMois.previousElementSibling && graphMois.previousElementSibling.tagName.match(/H2|DIV|H3|SPAN/)) {
                                    graphMois.previousElementSibling.style.marginBottom = '18px';
                                }
                                graphMois.style.marginTop = '0';
                                Object.keys(reunionsParMois).forEach(function(mois) {
                                    var val = reunionsParMois[mois];
                                    graphMois.appendChild(createBar(val, mois));
                                });

                                // Types de réunion
                                var typesReunion = <?php echo json_encode($types_reunion); ?>;


                                var graphTypes = document.getElementById('graphique-types');
                                graphTypes.innerHTML = '';
                                if (graphTypes.previousElementSibling && graphTypes.previousElementSibling.tagName.match(/H2|DIV|H3|SPAN/)) {
                                    graphTypes.previousElementSibling.style.marginBottom = '18px';
                                }
                                graphTypes.style.marginTop = '0';
                                Object.keys(typesReunion).forEach(function(type) {
                                    var val = typesReunion[type];
                                    graphTypes.appendChild(createBar(val, type));
                                });

                                // Réunions par jour de la semaine
                                var joursReunion = <?php echo json_encode($jours_reunion); ?>;


                                var graphJours = document.getElementById('graphique-jours');
                                graphJours.innerHTML = '';
                                if (graphJours.previousElementSibling && graphJours.previousElementSibling.tagName.match(/H2|DIV|H3|SPAN/)) {
                                    graphJours.previousElementSibling.style.marginBottom = '18px';
                                }
                                graphJours.style.marginTop = '0';
                                Object.keys(joursReunion).forEach(function(jour) {
                                    var val = joursReunion[jour];
                                    graphJours.appendChild(createBar(val, jour));
                                });

                                // Réunions récentes
                                var reunionsRecentes = <?php echo json_encode($reunions_recentes); ?>;
                                var listeRecentes = document.getElementById('liste-recentes');
                                listeRecentes.innerHTML = '';
                                if (reunionsRecentes.length === 0) {
                                    listeRecentes.innerHTML = '<div style="color:#64748b; font-style:italic;">Aucune réunion récente</div>';
                                } else {
                                    reunionsRecentes.forEach(function(r) {
                                        var div = document.createElement('div');
                                        div.style.marginBottom = '12px';
                                        div.style.padding = '10px 0';
                                        div.style.borderBottom = '1px solid #e0e7ef';
                                        div.innerHTML = '<span style="font-weight:bold;color:#dc2626;">'+r.titre+'</span> <span style="color:#64748b;">('+r.type+')</span> <span style="color:#222;">'+r.date+'</span> <span style="color:'+(r.statut==='annulée'?'#ef4444':'#22c55e')+';font-weight:bold;">'+(r.statut==='annulée'?'Annulée':'Active')+'</span>';
                                        listeRecentes.appendChild(div);
                                    });
                                }
                            }
                            document.addEventListener('DOMContentLoaded', renderStatistiques);
                            </script>
                        </div>
                        <div style="background:#fffbe6; border-radius:14px; padding:20px 24px; box-shadow:0 2px 8px #fbbf2422; flex:1; min-width:200px;">
                            <div style="font-size:1.1em; color:#64748b; margin-bottom:8px;">Réunions actives</div>
                            <div style="font-size:2em; font-weight:bold; color:#22c55e;" id="stat-reunions-actives">0</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">En cours de planification</div>
                        </div>
                        <div style="background:#fffbe6; border-radius:14px; padding:20px 24px; box-shadow:0 2px 8px #fbbf2422; flex:1; min-width:200px;">
                            <div style="font-size:1.1em; color:#64748b; margin-bottom:8px;">Taux d'annulation</div>
                            <div style="font-size:2em; font-weight:bold; color:#ef4444;" id="stat-taux-annulation">0%</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Réunions annulées</div>
                        </div>
                        <div style="background:#fffbe6; border-radius:14px; padding:20px 24px; box-shadow:0 2px 8px #fbbf2422; flex:1; min-width:200px;">
                            <div style="font-size:1.1em; color:#64748b; margin-bottom:8px;">Utilisateurs</div>
                            <div style="font-size:2em; font-weight:bold; color:#fbbf24;" id="stat-total-utilisateurs">0</div>
                            <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Inscrits au système</div>
                        </div>
                    </div>

                    <!-- Graphiques -->
                    <div style="display:flex; gap:32px; margin-top:32px; flex-wrap:wrap;">
                        <div style="background:#fffbe6; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #fbbf2422; flex:1; min-width:300px;">
                            <div style="font-size:1.1em; color:#64748b; margin-bottom:16px;">Réunions par mois</div>
                            <div id="graphique-mois" style="height:120px; display:flex; align-items:end; gap:8px; margin-top:16px;"></div>
                        </div>
                        <div style="background:#fffbe6; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #fbbf2422; flex:1; min-width:300px;">
                            <div style="font-size:1.1em; color:#64748b; margin-bottom:16px;">Types de réunions</div>
                            <div id="graphique-types" style="height:120px; display:flex; align-items:end; gap:8px; margin-top:16px;"></div>
                        </div>
                    </div>

                    <!-- Répartition par jour de la semaine -->
                    <div style="background:#fffbe6; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #fbbf2422; margin-top:24px;">
                        <div style="font-size:1.1em; color:#64748b; margin-bottom:16px;">Répartition par jour de la semaine</div>
                        <div id="graphique-jours" style="height:100px; display:flex; align-items:end; gap:12px; margin-top:16px;"></div>
                    </div>

                    <!-- Top 5 des réunions les plus récentes -->
                    <div style="background:#fffbe6; border-radius:14px; padding:24px 32px; box-shadow:0 2px 8px #fbbf2422; margin-top:24px;">
                        <div style="font-size:1.1em; color:#64748b; margin-bottom:16px;">Réunions récentes</div>
                        <div id="liste-recentes" style="margin-top:16px;">
                            <?php if (empty($reunions_recentes)) { ?>
                                <div style="color:#64748b; font-style:italic;">Aucune réunion récente</div>
                            <?php } else { ?>
                                <table style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#fffbe6; color:#dc2626;">
                                            <th style="padding:8px 12px; text-align:left;">Titre</th>
                                            <th style="padding:8px 12px; text-align:left;">Type</th>
                                            <th style="padding:8px 12px; text-align:left;">Date</th>
                                            <th style="padding:8px 12px; text-align:left;">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reunions_recentes as $r) { ?>
                                            <tr>
                                                <td style="padding:8px 12px;"> <?= htmlspecialchars($r['titre']) ?> </td>
                                                <td style="padding:8px 12px;"> <?= htmlspecialchars($r['type']) ?> </td>
                                                <td style="padding:8px 12px;"> <?= htmlspecialchars($r['date']) ?> </td>
                                                <td style="padding:8px 12px;">
                                                    <?php if ($r['statut'] === 'active') { ?>
                                                        <span class="statut-active">Active</span>
                                                    <?php } else { ?>
                                                        <span class="statut-annulee">Annulée</span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Aide -->
            <section id="aide" class="section">
                <div class="aide-container">
                    <div class="aide-header">
                        <h2><i class="fa fa-question-circle"></i> Centre d'aide</h2>
                        <p class="aide-subtitle">Trouvez rapidement les réponses à vos questions</p>
                    </div>
                    
                    <div class="aide-search">
                        <div class="search-box">
                            <i class="fa fa-search"></i>
                            <input type="text" placeholder="Rechercher dans l'aide..." id="aide-search-input">
                        </div>
                    </div>
                    
                    <div class="aide-categories">
                        <div class="category-card" data-target="#faq-planification">
                            <div class="category-icon">
                                <i class="fa fa-calendar-plus"></i>
                            </div>
                            <h3>Planification</h3>
                            <p>Apprenez à créer et gérer vos réunions</p>
                        </div>
                        <div class="category-card" data-target="#faq-participants">
                            <div class="category-icon">
                                <i class="fa fa-users"></i>
                            </div>
                            <h3>Participants</h3>
                            <p>Gérez les invitations et les participants</p>
                        </div>
                        <div class="category-card" data-target="#faq-parametres">
                            <div class="category-icon">
                                <i class="fa fa-cog"></i>
                            </div>
                            <h3>Paramètres</h3>
                            <p>Personnalisez votre expérience</p>
                        </div>
                        <div class="category-card" data-target="#faq-securite">
                            <div class="category-icon">
                                <i class="fa fa-shield-alt"></i>
                            </div>
                            <h3>Sécurité</h3>
                            <p>Protégez vos données et votre compte</p>
                        </div>
                    </div>
                    
                    <div class="faq-section">
                        <h3><i class="fa fa-lightbulb"></i> Questions fréquentes</h3>
                        <div class="faq-list">
                            <div class="faq-item" id="faq-planification" data-categorie="planification">
                                <div class="faq-question">
                                    <span>Comment planifier une réunion ?</span>
                                    <i class="fa fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Pour planifier une réunion, suivez ces étapes simples :</p>
                                    <div class="faq-steps">
                                        <div class="step">
                                            <span class="step-number">1</span>
                                            <span>Cliquez sur le bouton <strong>"Créer"</strong> dans la barre latérale ou sur <strong>"Planifier une réunion"</strong> depuis l'accueil</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">2</span>
                                            <span>Remplissez le formulaire avec : <strong>titre</strong>, <strong>date</strong>, <strong>heure</strong>, <strong>type</strong> (En ligne/Présentiel/Mixte) et <strong>description</strong></span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">3</span>
                                            <span>Cliquez sur <strong>"Créer"</strong> pour finaliser la planification</span>
                                        </div>
                                    </div>
                                    <p><strong>Note :</strong> Votre réunion sera automatiquement visible dans la section "Planification" et apparaîtra dans les statistiques.</p>
                                </div>
                            </div>
                            
                            <div class="faq-item" id="faq-participants" data-categorie="participants">
                                <div class="faq-question">
                                    <span>Comment modifier une réunion existante ?</span>
                                    <i class="fa fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Pour modifier une réunion existante :</p>
                                    <div class="faq-steps">
                                        <div class="step">
                                            <span class="step-number">1</span>
                                            <span>Allez dans la section <strong>"Planification"</strong> depuis le menu latéral</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">2</span>
                                            <span>Trouvez la réunion que vous souhaitez modifier dans la liste</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">3</span>
                                            <span>Cliquez sur <strong>"Voir"</strong> pour accéder aux détails</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">4</span>
                                            <span>Cliquez sur <strong>"Modifier"</strong> pour éditer les informations</span>
                                        </div>
                                    </div>
                                    <p><strong>Informations modifiables :</strong> titre, date, heure, type de réunion, description</p>
                                    <p><strong>Note :</strong> Les modifications sont sauvegardées automatiquement et visibles immédiatement.</p>
                                </div>
                            </div>
                            
                            <div class="faq-item" id="faq-parametres" data-categorie="parametres">
                                <div class="faq-question">
                                    <span>Comment annuler une réunion ?</span>
                                    <i class="fa fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Pour annuler une réunion :</p>
                                    <div class="faq-steps">
                                        <div class="step">
                                            <span class="step-number">1</span>
                                            <span>Accédez à la section <strong>"Planification"</strong></span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">2</span>
                                            <span>Localisez la réunion que vous souhaitez annuler</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">3</span>
                                            <span>Cliquez sur le bouton <strong>"Annuler"</strong> à côté de la réunion</span>
                                        </div>
                                    </div>
                                    <p><strong>Ce qui se passe après l'annulation :</strong></p>
                                    <ul style="margin: 16px 0; padding-left: 20px; color: var(--text-secondary);">
                                        <li>La réunion disparaît de la liste "Planification"</li>
                                        <li>Elle apparaît dans l'<strong>"Historique"</strong> avec le statut "Annulée"</li>
                                        <li>Les statistiques sont mises à jour automatiquement</li>
                                        <li>Le taux d'annulation est recalculé</li>
                                    </ul>
                                    <p><strong>Note :</strong> L'annulation est définitive mais la réunion reste visible dans l'historique pour traçabilité.</p>
                                </div>
                            </div>
                            
                            <div class="faq-item" id="faq-securite" data-categorie="securite">
                                <div class="faq-question">
                                    <span>Comment voir mes réunions passées ?</span>
                                    <i class="fa fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Pour consulter l'historique de vos réunions :</p>
                                    <div class="faq-steps">
                                        <div class="step">
                                            <span class="step-number">1</span>
                                            <span>Cliquez sur <strong>"Réunions"</strong> dans le menu latéral</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">2</span>
                                            <span>Vous verrez un tableau avec toutes vos réunions</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">3</span>
                                            <span>Cliquez sur <strong>"Voir les détails"</strong> pour plus d'informations</span>
                                        </div>
                                    </div>
                                    <p><strong>Informations disponibles dans l'historique :</strong></p>
                                    <ul style="margin: 16px 0; padding-left: 20px; color: var(--text-secondary);">
                                        <li><strong>Titre</strong> de la réunion</li>
                                        <li><strong>Date et heure</strong> programmées</li>
                                        <li><strong>Statut</strong> : Active (verte) ou Annulée (rouge)</li>
                                        <li><strong>Actions</strong> pour voir les détails complets</li>
                                    </ul>
                                    <p><strong>Note :</strong> L'historique inclut toutes les réunions créées, qu'elles soient actives ou annulées, triées par date de création.</p>
                                </div>
                            </div>
                            
                            <div class="faq-item">
                                <div class="faq-question">
                                    <span>Comment contacter le support ?</span>
                                    <i class="fa fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Notre équipe support est disponible pour vous aider avec toute question ou problème :</p>
                                    <div class="contact-info">
                                        <div class="contact-item">
                                            <i class="fa fa-envelope"></i>
                                            <a href="mailto:support@meetingplanner.com">support@meetingplanner.com</a>
                                        </div>
                                        <div class="contact-item">
                                            <i class="fa fa-phone"></i>
                                            <span>+33 1 23 45 67 89</span>
                                        </div>
                                        <div class="contact-item">
                                            <i class="fa fa-clock"></i>
                                            <span>Lun-Ven 9h-18h (UTC+1)</span>
                                        </div>
                                    </div>
                                    <p><strong>Méthodes de contact :</strong></p>
                                    <ul style="margin: 16px 0; padding-left: 20px; color: var(--text-secondary);">
                                        <li><strong>Email :</strong> Réponse sous 24h en jours ouvrables</li>
                                        <li><strong>Téléphone :</strong> Support direct pendant les horaires d'ouverture</li>
                                        <li><strong>Chat :</strong> Cliquez sur le bouton "Contacter le support" ci-dessous</li>
                                    </ul>
                                    <p><strong>Types d'assistance :</strong> Problèmes techniques, questions sur les fonctionnalités, demandes de formation, suggestions d'amélioration</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="aide-footer">
                        <div class="support-card">
                            <div class="support-icon">
                                <i class="fa fa-headset"></i>
                            </div>
                            <div class="support-content">
                                <h4>Besoin d'aide supplémentaire ?</h4>
                                <p>Notre équipe support est là pour vous aider</p>
                                <button class="btn-support">
                                    <i class="fa fa-comments"></i>
                                    Contacter le support
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Paramètres -->
            <section id="parametres" class="section">
                <div class="welcome-card">
                    <h2>Paramètres</h2>
                    <div style="display:flex; gap:32px; margin-top:24px;">
                        <div style="flex:1;">
                            <h3 style="color:#dc2626; margin-bottom:16px;"><i class="fa fa-user"></i> Profil utilisateur</h3>
                            <div class="param-section">
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Nom complet</label><br>
                                    <input type="text" value="<?php echo $nom; ?>" readonly style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f87171; margin-top:4px;">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Email</label><br>
                                    <input type="email" value="<?php echo $email; ?>" readonly style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f87171; margin-top:4px;">
                                </div>
                                <div style="margin-bottom:16px;">
                                    <label style="font-weight:600; color:#374151;">Fuseau horaire</label><br>
                                    <select style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f87171; margin-top:4px;">
                                        <option>Europe/Paris (UTC+1)</option>
                                        <option>UTC</option>
                                        <option>America/New_York</option>
                                    </select>
                                </div>
                                <button class="btn-create" style="font-size:0.9em;"><i class="fa fa-save"></i> Sauvegarder</button>
                            </div>
                        </div>
                        <div style="flex:1;">
                            <h3 style="color:#dc2626; margin-bottom:16px;"><i class="fa fa-bell"></i> Notifications</h3>
                            <div class="param-section">
                                <div style="margin-bottom:12px;">
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" checked style="width:16px; height:16px;">
                                        <span style="font-weight:600; color:#374151;">Notifications par email</span>
                                    </label>
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" checked style="width:16px; height:16px;">
                                        <span style="font-weight:600; color:#374151;">Rappels avant réunion</span>
                                    </label>
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" style="width:16px; height:16px;">
                                        <span style="font-weight:600; color:#374151;">Notifications push</span>
                                    </label>
                                </div>
                                <div style="margin-bottom:16px;">
                                    <label style="font-weight:600; color:#374151;">Rappel (minutes avant)</label><br>
                                    <select style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f87171; margin-top:4px;">
                                        <option>15 minutes</option>
                                        <option>30 minutes</option>
                                        <option>1 heure</option>
                                    </select>
                                </div>
                                <button class="btn-create" style="font-size:0.9em;"><i class="fa fa-save"></i> Sauvegarder</button>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap:32px;">
                        <div style="flex:1;">
                            <h3 style="color:#dc2626; margin-bottom:16px;"><i class="fa fa-palette"></i> Apparence</h3>
                            <div class="param-section">
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Thème</label><br>
                                    <select style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f87171; margin-top:4px;">
                                        <option>Clair</option>
                                        <option>Sombre</option>
                                        <option>Auto</option>
                                    </select>
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Langue</label><br>
                                    <select id="language-select" style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f87171; margin-top:4px;">
                                        <option value="fr">Français</option>
                                        <option value="en">English</option>
                                        <option value="es">Español</option>
                                    </select>
                                </div>
                                <button class="btn-create" style="font-size:0.9em;"><i class="fa fa-save"></i> Sauvegarder</button>
                            </div>
                        </div>
                        <div style="flex:1;">
                            <h3 style="color:#dc2626; margin-bottom:16px;"><i class="fa fa-shield-alt"></i> Sécurité</h3>
                            <div class="param-section">
                                <form method="post" action="dashboard.php#parametres">
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600; color:#374151;">Mot de passe actuel</label><br>
                                        <input type="password" name="current_password" required style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f8717121; margin-top:4px;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600; color:#374151;">Nouveau mot de passe</label><br>
                                        <input type="password" name="new_password" required style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f8717121; margin-top:4px;">
                                    </div>
                                    <div style="margin-bottom:16px;">
                                        <label style="font-weight:600; color:#374151;">Confirmer le mot de passe</label><br>
                                        <input type="password" name="confirm_password" required style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #f8717121; margin-top:4px;">
                                    </div>
                                    <button class="btn-create" style="font-size:0.9em;" type="submit" name="change_password">Changer le mot de passe</button>
                                    <?php if ($pwd_message) { ?>
                                        <div style="margin-top:10px; color:<?= $pwd_success ? '#388e3c' : '#d32f2f' ?>;"><?= htmlspecialchars($pwd_message) ?></div>
                                    <?php } ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Modal de création de réunion -->
            <div id="modal-create" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:1000; align-items:center; justify-content:center;">
                <div style="background:#fff; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,0.13); padding:36px 32px; min-width:320px; max-width:90vw; margin:auto;">
                    <h2 style="color:#dc2626; margin-top:0;">Créer une réunion</h2>
                    <form id="form-create-meeting" method="post" action="dashboard.php#tableau-admin">
                        <div style="margin-bottom:14px;">
                            <label for="titre">Titre de la réunion</label><br>
                            <input type="text" id="titre" name="titre" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #f87171;">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label for="date">Date</label><br>
                            <input type="date" id="date" name="date" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #f87171;">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label for="heure">Heure</label><br>
                            <input type="time" id="heure" name="heure" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #f87171;">
                        </div>
                        <div style="margin-bottom:18px;">
                            <label for="type">Type de réunion</label><br>
                            <select id="type" name="type" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #f87171;">
                                <option value="">Sélectionner</option>
                                <option value="En ligne">En ligne</option>
                                <option value="Présentiel">Présentiel</option>
                                <option value="Mixte">Mixte</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px;">
                            <label for="description">Description</label><br>
                            <textarea id="description" name="description" rows="3" style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #f87171; resize:vertical;" placeholder="Description de la réunion..."></textarea>
                        </div>
                        <div style="text-align:right;">
                            <button type="button" id="close-modal-create" class="btn-annuler">Annuler</button>
                            <button type="submit" name="create_meeting" class="btn-create"><i class="fa fa-check"></i> Créer</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Modal d'affichage des détails de réunion -->
            <div id="modal-details" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:2000; align-items:center; justify-content:center;">
                <div id="modal-details-content" style="background:#fff; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,0.13); padding:36px 32px; min-width:320px; max-width:90vw; margin:auto; position:relative;">
                    <button id="close-modal-details" style="position:absolute; top:12px; right:18px; background:none; border:none; font-size:1.5em; color:#dc2626; cursor:pointer;">&times;</button>
                    <h2 style="color:#dc2626; margin-top:0;">Détails de la réunion</h2>
                    <div id="modal-details-body">
                        <!-- Les infos de la réunion seront injectées ici -->
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="dashboard.js"></script>
        <script>
        document.getElementById('btn-show-users').onclick = function() {
            document.getElementById('users-table-accordion').style.display = 'block';
            document.getElementById('meetings-table-accordion').style.display = 'none';
            document.getElementById('users-table-accordion').scrollIntoView({behavior:'smooth', block:'start'});
        };
        document.getElementById('btn-show-meetings').onclick = function() {
            document.getElementById('users-table-accordion').style.display = 'none';
            document.getElementById('meetings-table-accordion').style.display = 'block';
            document.getElementById('meetings-table-accordion').scrollIntoView({behavior:'smooth', block:'start'});
        };

        // Active la section selon l'ancre de l'URL au chargement
        window.addEventListener('DOMContentLoaded', function() {
            var hash = window.location.hash.replace('#', '');
            if (hash) {
                document.querySelectorAll('.section').forEach(function(sec) { sec.classList.remove('active'); });
                var section = document.getElementById(hash);
                if (section) {
                    section.classList.add('active');
                    document.querySelectorAll('.sidebar-menu a').forEach(function(a) { a.classList.remove('active'); });
                    var link = document.querySelector('.sidebar-menu a[data-section="' + hash + '"]');
                    if (link) link.classList.add('active');
                }
            }
        });
        </script>
</body>
</html>