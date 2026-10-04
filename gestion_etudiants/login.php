<?php

require_once __DIR__ . '/includes/init.php';

if (utilisateurConnecte()) {
    rediriger('dashboard.php');
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    $email = trim((string) ($_POST['email'] ?? ''));
    $motDePasse = (string) ($_POST['motdepass'] ?? '');

    // Limite : 5 échecs par tranche de 10 minutes
    $maintenant = time();

    $echecs = array_filter(
        $_SESSION['login_echecs'] ?? [],
        fn($t) => $t > $maintenant - 600
    );

    if (count($echecs) >= 5) {

        $erreur = 'Trop de tentatives. Réessaie dans quelques minutes.';

    } else {

        $requete = $connexion->prepare("SELECT * FROM utilisateur WHERE email = :email LIMIT 1");
        $requete->execute([':email' => $email]);
        $utilisateur = $requete->fetch();

        if ($utilisateur && (int) $utilisateur['actif'] === 1 && password_verify($motDePasse, $utilisateur['motdepass'])) {

            session_regenerate_id(true);

            unset($_SESSION['login_echecs']);

            $_SESSION['utilisateur'] = [
                'idUtilisateur' => (int) $utilisateur['idUtilisateur'],
                'nom' => $utilisateur['nom'],
                'prenom' => $utilisateur['prenom'],
                'email' => $utilisateur['email'],
                'role' => $utilisateur['role'],
            ];

            rediriger('dashboard.php');
        }

        $echecs[] = $maintenant;
        $_SESSION['login_echecs'] = $echecs;

        $erreur = 'Email ou mot de passe incorrect.';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion - Gestion des étudiants</title>

    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">

</head>

<body class="connexion-page">

<div class="connexion-boite">

    <h1>🎓 Gestion des étudiants</h1>
    <p class="sous">École Supérieure de Gestion Numérique (ESGN)</p>

    <?php if ($erreur !== ''): ?>

        <div class="flash flash-erreur"><?= e($erreur) ?></div>

    <?php endif; ?>

    <form method="post" autocomplete="off">

        <?= csrfChamp() ?>

        <div class="champ">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
        </div>

        <div class="champ">
            <label for="motdepass">Mot de passe</label>
            <input type="password" id="motdepass" name="motdepass" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn">Se connecter</button>

    </form>

</div>

</body>

</html>
