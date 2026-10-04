<?php

$u = utilisateurConnecte();
$titre = $titre ?? 'Gestion des étudiants';
$actif = $actif ?? '';

$menu = [
    'dashboard' => ['Tableau de bord', 'dashboard.php', '🏠', true],
    'etudiants' => ['Étudiants', 'etudiants/index.php', '👤', true],
    'classes' => ['Classes', 'classes/index.php', '👥', true],
    'filieres' => ['Filières', 'filieres/index.php', '📖', true],
    'inscriptions' => ['Inscriptions', 'inscriptions/index.php', '📝', true],
    'utilisateurs' => ['Utilisateurs', 'utilisateurs/index.php', '🔑', estAdministrateur()],
    'statistiques' => ['Statistiques', 'statistiques/index.php', '📊', true],
    'parametres' => ['Paramètres', 'parametres/index.php', '⚙️', true],
];

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($titre) ?> - Gestion des étudiants</title>

    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">

</head>

<body>

<div class="app">

    <aside class="sidebar" id="sidebar">

        <div class="logo">
            <span class="logo-icone">🎓</span>
            <span>GESTION<br><small>DES ÉTUDIANTS</small></span>
        </div>

        <nav class="menu">

            <?php foreach ($menu as $cle => $item): ?>

                <?php if ($item[3]): ?>

                    <a class="<?= $actif === $cle ? 'actif' : '' ?>" href="<?= e(url($item[1])) ?>">
                        <span><?= $item[2] ?></span> <?= e($item[0]) ?>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

        </nav>

        <div class="ecole">
            <strong>École Supérieure de<br>Gestion Numérique</strong>
            <hr>
            Année académique<br><?= e(anneeCourante($connexion)) ?>
        </div>

    </aside>

    <div class="contenu">

        <header class="topbar">

            <button type="button" class="burger" id="burger" aria-label="Menu">☰</button>

            <h1><?= e($titre) ?></h1>

            <div class="utilisateur">
                <span class="avatar avatar-initiales"><?= e(mb_strtoupper(mb_substr($u['prenom'] ?? '', 0, 1) . mb_substr($u['nom'] ?? '', 0, 1))) ?></span>
                <span>
                    <?= e(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?><br>
                    <small><?= e(ROLES[$u['role'] ?? ''] ?? '') ?></small>
                </span>
                <a class="btn btn-sec" href="<?= e(url('logout.php')) ?>">Déconnexion</a>
            </div>

        </header>

        <main class="page">

            <?php foreach (lireFlash() as $message): ?>

                <div class="flash flash-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>

            <?php endforeach; ?>
