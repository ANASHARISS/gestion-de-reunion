<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['adresse'])) {
    header('Location: login.html');
    exit;
}

$email = htmlspecialchars($_SESSION['user']['adresse']);
$nom = isset($_SESSION['user']['nom']) ? htmlspecialchars($_SESSION['user']['nom']) : '';

$pwd_message = '';
$pwd_success = false;

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
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Création de réunion
if (isset($_POST['create_meeting'])) {
    $titre = $_POST['titre'] ?? '';
    $date = $_POST['date'] ?? '';
    $heure = $_POST['heure'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    if ($titre && $date && $heure && $type) {
        $stmt = $pdo->prepare('INSERT INTO reunion (titre, date, heure, type, description, adresse) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$titre, $date, $heure, $type, $description, $_SESSION['user']['adresse']]);
        header('Location: user.php');
        exit;
    }
}

// Récupérer les réunions à venir
$stmt = $pdo->prepare('SELECT * FROM reunion WHERE adresse = ? AND date >= CURDATE() ORDER BY date, heure');
$stmt->execute([$_SESSION['user']['adresse']]);
$reunions_avenir = $stmt->fetchAll();

// Récupérer l'historique des réunions
$stmt = $pdo->prepare('SELECT * FROM reunion WHERE adresse = ? AND date < CURDATE() ORDER BY date DESC, heure DESC');
$stmt->execute([$_SESSION['user']['adresse']]);
$reunions_hist = $stmt->fetchAll();

// Statistiques
$stmt = $pdo->prepare('SELECT COUNT(*) FROM reunion WHERE adresse = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())');
$stmt->execute([$_SESSION['user']['adresse']]);
$nb_reunions_mois = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reunion WHERE adresse = ? AND date >= CURDATE() AND WEEK(date, 1) = WEEK(CURDATE(), 1)');
$stmt->execute([$_SESSION['user']['adresse']]);
$nb_reunions_semaine = $stmt->fetchColumn();



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
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            // Vérifier l'ancien mot de passe
            $stmt = $pdo->prepare('SELECT `mot de passe` FROM user WHERE `adresse` = ?');
            $stmt->execute([$_SESSION['user']['adresse']]);
            $row = $stmt->fetch();
            if (!$row || $row['mot de passe'] !== $current) {
                $pwd_message = "L'ancien mot de passe est incorrect.";
            } else {
                // Mettre à jour le mot de passe
                $stmt = $pdo->prepare('UPDATE user SET `mot de passe` = ? WHERE `adresse` = ?');
                $stmt->execute([$new, $_SESSION['user']['adresse']]);
                $pwd_message = "Mot de passe changé avec succès.";
                $pwd_success = true;
            }
        } catch (PDOException $e) {
            $pwd_message = "Erreur lors du changement : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetingPlanner - Dashboard</title>
    <link rel="stylesheet" href="user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
                <li><a href="#" data-section="sms"><i class="fa fa-comments"></i> SMS</a></li>
                <li><a href="#" data-section="aide"><i class="fa fa-question-circle"></i> Aide</a></li>
                <li class="deconnexion-item"><a href="#" id="deconnexion-btn"><i class="fa fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>
<?php
// Gestion des messages SMS (version simplifiée, la table sms doit déjà exister)
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
?>
        <main class="main-content">
            <header class="main-header">
                <span class="main-title">Application de planification des réunions</span>
                <div class="main-profile">
                    <span class="profile-icon"><i class="fa fa-user"></i></span>
                    <span class="settings-icon" id="go-settings" style="margin-left:18px; cursor:pointer; font-size:1.3em; color:#2563eb;" title="Paramètres"><i class="fa fa-cog"></i></span>
                </div>
            </header>
            <!-- SMS -->
            <section id="sms" class="section">
                <div class="welcome-card">
                    <h2><i class="fa fa-comments"></i> Messagerie SMS</h2>
                    <form method="post" action="user.php#sms" style="margin-bottom:18px; display:flex; gap:12px;">
                        <input type="text" name="sms_message" placeholder="Votre message..." style="flex:1; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb;" required>
                        <button type="submit" name="send_sms" class="btn-create" style="padding:8px 24px;"><i class="fa fa-paper-plane"></i> Envoyer</button>
                    </form>
                    <?php if ($sms_message) { ?>
                        <div style="margin-bottom:12px; color:#2563eb; font-weight:bold;"> <?= htmlspecialchars($sms_message) ?> </div>
                    <?php } ?>
                    <div style="max-height:320px; overflow-y:auto; background:#f1f5f9; border-radius:10px; padding:18px;">
                        <?php if (empty($sms_list)) { ?>
                            <div style="color:#64748b;">Aucun message.</div>
                        <?php } else { foreach ($sms_list as $sms) { ?>
                            <div style="margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #e0e7ef;">
                                <div style="font-weight:bold; color:#2563eb;"><i class="fa fa-user"></i> <?= htmlspecialchars($sms['expediteur']) ?></div>
                                <div style="margin:6px 0 2px 0; color:#222;"> <?= nl2br(htmlspecialchars($sms['message'])) ?> </div>
                                <div style="font-size:0.9em; color:#64748b;"> <?= date('d/m/Y H:i', strtotime($sms['date_envoi'])) ?> </div>
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
                            <div style="font-size:1.2em; color:#024aef; margin-bottom:8px;"><i class="fa fa-calendar-check"></i></div>
                            <div style="font-size:1.1em; font-weight:bold; color:#0029f4;">Réunions créées</div>
                        <div style="font-size:1.8em; font-weight:bold; color:#006eff;">
                            <?php echo $nb_reunions_mois; ?>
                        </div>
                        <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Ce mois</div>
                        </div>
                        <div class="stats-card" style="flex:1;">
                            <div style="font-size:1.2em; color:#024aef; margin-bottom:8px;"><i class="fa fa-clock"></i></div>
                            <div style="font-size:1.1em; font-weight:bold; color:#0029f4;">À venir</div>
                        <div style="font-size:1.8em; font-weight:bold; color:#006eff;">
                            <?php echo $nb_reunions_semaine; ?>
                        </div>
                        <div style="font-size:0.9em; color:#64748b; margin-top:4px;">Cette semaine</div>
                        </div>
                        <!-- Statistique participants retirée car colonne inexistante -->
                    </div>
                    <div class="stats-card" style="margin-top:24px;">
                        <div style="font-size:1.1em; font-weight:bold; color:#f59e42; margin-bottom:12px;"><i class="fa fa-history"></i> Dernière activité</div>
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
                    <?php if (empty($reunions_avenir)) { ?>
                        <div style="color:#64748b;">Aucune réunion à venir.</div>
                    <?php } else { foreach ($reunions_avenir as $r) { ?>
                    <div class="meeting-card">
                        <div class="meeting-card-left"></div>
                        <div class="meeting-card-content">
                            <div class="meeting-title-row">
                                <span class="meeting-title"><b><?php echo htmlspecialchars($r['titre']); ?></b></span>
                                <span style="margin-left:auto; color:#64748b;">
                                    <?php echo date('d/m/Y', strtotime($r['date'])) . ', ' . substr($r['heure'], 0, 5); ?>
                                </span>
                            </div>
                            <div class="meeting-details">
                                <?php echo htmlspecialchars($r['type']); ?> · Participants : <?php echo htmlspecialchars($r['participants'] ?? ''); ?>
                            </div>
                            <div class="meeting-actions">
                                <a href="#" class="meeting-link">Voir</a>
                                <!-- Annulation à implémenter -->
                            </div>
                        </div>
                    </div>
                    <?php }} ?>
                </div>
            </section>
            <!-- Réunions -->
            <section id="reunions" class="section">
                <div class="welcome-card">
                    <h2>Toutes les réunions</h2>
                    <table style="width:100%; border-collapse:collapse; margin-top:18px;">
                        <thead>
                            <tr style="background:#eaf1fb; color:#2563eb;">
                                <th style="padding:10px 8px; text-align:left;">Titre</th>
                                <th style="padding:10px 8px; text-align:left;">Date</th>
                                <th style="padding:10px 8px; text-align:left;">Statut</th>
                                <th style="padding:10px 8px; text-align:left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $stmt = $pdo->prepare('SELECT * FROM reunion WHERE adresse = ? ORDER BY date DESC, heure DESC');
                        $stmt->execute([$_SESSION['user']['adresse']]);
                        $reunions_all = $stmt->fetchAll();
                        if (empty($reunions_all)) { ?>
                            <tr><td colspan="4" style="color:#64748b;">Aucune réunion.</td></tr>
                        <?php } else { foreach ($reunions_all as $r) { 
                            $statut = (strtotime($r['date']) < strtotime(date('Y-m-d'))) ? 'Terminée' : 'À venir';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['titre']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($r['date'])) . ', ' . substr($r['heure'], 0, 5); ?></td>
                                <td><span style="color:#64748b; font-weight:bold;"><?php echo $statut; ?></span></td>
                                <td><a href="#" class="meeting-link" data-titre="<?php echo htmlspecialchars($r['titre']); ?>" data-date="<?php echo date('d/m/Y', strtotime($r['date'])) . ', ' . substr($r['heure'], 0, 5); ?>" data-type="<?php echo htmlspecialchars($r['type']); ?>" data-description="<?php echo htmlspecialchars($r['description'] ?? ''); ?>">Voir les détails</a></td>
                            </tr>
                        <?php }} ?>
                        </tbody>
                    </table>
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
                            <h3 style="color:#2563eb; margin-bottom:16px;"><i class="fa fa-user"></i> Profil utilisateur</h3>
                            <div class="param-section">
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Nom complet</label><br>
                                    <input type="text" value="<?php echo $nom; ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;" readonly>
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Email</label><br>
                                    <input type="email" value="<?php echo $email; ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;" readonly>
                                </div>
                                <div style="margin-bottom:16px;">
                                    <label style="font-weight:600; color:#374151;">Fuseau horaire</label><br>
                                    <select style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
                                        <option>Europe/Paris (UTC+1)</option>
                                        <option>UTC</option>
                                        <option>America/New_York</option>
                                    </select>
                                </div>
                                <button class="btn-create" style="font-size:0.9em;"><i class="fa fa-save"></i> Sauvegarder</button>
                            </div>
                        </div>
                        <div style="flex:1;">
                            <h3 style="color:#2563eb; margin-bottom:16px;"><i class="fa fa-bell"></i> Notifications</h3>
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
                                    <select style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
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
                            <h3 style="color:#2563eb; margin-bottom:16px;"><i class="fa fa-palette"></i> Apparence</h3>
                            <div class="param-section">
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Thème</label><br>
                                    <select style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
                                        <option>Clair</option>
                                        <option>Sombre</option>
                                        <option>Auto</option>
                                    </select>
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-weight:600; color:#374151;">Langue</label><br>
                                    <select id="language-select" style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
                                        <option value="fr">Français</option>
                                        <option value="en">English</option>
                                        <option value="es">Español</option>
                                    </select>
                                </div>
                                <button class="btn-create" style="font-size:0.9em;"><i class="fa fa-save"></i> Sauvegarder</button>
                            </div>
                        </div>
                        <div style="flex:1;">
                            <h3 style="color:#2563eb; margin-bottom:16px;"><i class="fa fa-shield-alt"></i> Sécurité</h3>
                            <div class="param-section">
                                <form method="post" action="user.php#parametres">
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600; color:#374151;">Mot de passe actuel</label><br>
                                        <input type="password" name="current_password" required style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600; color:#374151;">Nouveau mot de passe</label><br>
                                        <input type="password" name="new_password" required style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
                                    </div>
                                    <div style="margin-bottom:16px;">
                                        <label style="font-weight:600; color:#374151;">Confirmer le mot de passe</label><br>
                                        <input type="password" name="confirm_password" required style="width:100%; padding:8px 12px; border-radius:8px; border:1.5px solid #2563eb; margin-top:4px;">
                                    </div>
                                    <button class="btn-create" style="font-size:0.9em;" type="submit" name="change_password"><i class="fa fa-key"></i> Changer le mot de passe</button>
                                    <?php if (isset($pwd_message)) { ?>
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
                <div style="background:#eaf1fb; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,0.13); padding:36px 32px; min-width:320px; max-width:90vw; margin:auto;">
                    <h2 style="color:#2563eb; margin-top:0;">Créer une réunion</h2>
                    <form id="form-create-meeting" method="post" action="user.php#planification">
                        <div style="margin-bottom:14px;">
                            <label for="titre">Titre de la réunion</label><br>
                            <input type="text" id="titre" name="titre" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #2563eb;">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label for="date">Date</label><br>
                            <input type="date" id="date" name="date" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #2563eb;">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label for="heure">Heure</label><br>
                            <input type="time" id="heure" name="heure" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #2563eb;">
                        </div>
                        <div style="margin-bottom:18px;">
                            <label for="type">Type de réunion</label><br>
                            <select id="type" name="type" required style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #2563eb;">
                                <option value="">Sélectionner</option>
                                <option value="En ligne">En ligne</option>
                                <option value="Présentiel">Présentiel</option>
                                <option value="Mixte">Mixte</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px;">
                            <label for="description">Description</label><br>
                            <textarea id="description" name="description" rows="3" style="width:100%; padding:8px 10px; border-radius:8px; border:1.5px solid #2563eb; resize:vertical;" placeholder="Description de la réunion..."></textarea>
                        </div>
                        <input type="hidden" name="create_meeting" value="1">
                        <div style="text-align:right;">
                            <button type="button" id="close-modal-create" class="btn-annuler">Annuler</button>
                            <button type="submit" class="btn-create"><i class="fa fa-check"></i> Créer</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="user.js"></script>
    <script>
    // Navigation onglets (ajout SMS)
    document.querySelectorAll('.sidebar-menu a[data-section]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.section').forEach(function(sec) { sec.classList.remove('active'); });
            var section = this.getAttribute('data-section');
            document.getElementById(section).classList.add('active');
            document.querySelectorAll('.sidebar-menu a').forEach(function(a) { a.classList.remove('active'); });
            this.classList.add('active');
        });
    });
    </script>
    <div id="modal-details" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:2000; align-items:center; justify-content:center;">
        <div id="modal-details-content" style="background:#fff; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,0.13); padding:36px 32px; min-width:320px; max-width:90vw; margin:auto; position:relative;">
            <button id="close-modal-details" style="position:absolute; top:12px; right:18px; background:none; border:none; font-size:1.5em; color:#2563eb; cursor:pointer;">&times;</button>
            <h2 style="color:#2563eb; margin-top:0;">Détails de la réunion</h2>
            <div id="modal-details-body"></div>
        </div>
    </div>
    <script>
    // Modal détails réunion (similaire à dashboard admin)
    document.querySelectorAll('.meeting-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var titre = this.getAttribute('data-titre');
            var date = this.getAttribute('data-date');
            var type = this.getAttribute('data-type');
            var description = this.getAttribute('data-description');
            var html = '<div><b>Titre :</b> ' + titre + '</div>';
            html += '<div><b>Date :</b> ' + date + '</div>';
            html += '<div><b>Type :</b> ' + type + '</div>';
            html += '<div><b>Description :</b> ' + (description ? description : '-') + '</div>';
            document.getElementById('modal-details-body').innerHTML = html;
            document.getElementById('modal-details').style.display = 'flex';
        });
    });
    document.getElementById('close-modal-details').onclick = function() {
        document.getElementById('modal-details').style.display = 'none';
    };
    window.onclick = function(event) {
        var modal = document.getElementById('modal-details');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
    </script>
</body>
</html>