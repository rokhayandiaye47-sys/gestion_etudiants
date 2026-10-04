<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    $actuel = (string) ($_POST['actuel'] ?? '');
    $nouveau = (string) ($_POST['nouveau'] ?? '');
    $confirmation = (string) ($_POST['confirmation'] ?? '');

    // Limite : 5 mots de passe actuels incorrects par tranche de 10 minutes
    $maintenant = time();

    $echecs = array_filter(
        $_SESSION['parametres_echecs'] ?? [],
        fn($t) => $t > $maintenant - 600
    );

    if (count($echecs) >= 5) {

        $erreurs[] = 'Trop de tentatives. Réessaie dans quelques minutes.';

    } else {

        $requete = $connexion->prepare("SELECT motdepass FROM utilisateur WHERE idUtilisateur = :id");
        $requete->execute([':id' => $_SESSION['utilisateur']['idUtilisateur']]);
        $hash = $requete->fetchColumn();

        if (!$hash || !password_verify($actuel, $hash)) {

            $echecs[] = $maintenant;
            $_SESSION['parametres_echecs'] = $echecs;

            $erreurs[] = 'Le mot de passe actuel est incorrect.';

        } elseif (strlen($nouveau) < 8) {

            $erreurs[] = 'Le nouveau mot de passe doit faire 8 caractères au minimum.';

        } elseif ($nouveau !== $confirmation) {

            $erreurs[] = 'Les deux mots de passe ne correspondent pas.';

        } else {

            $connexion->prepare("UPDATE utilisateur SET motdepass = :mdp WHERE idUtilisateur = :id")
                ->execute([
                    ':mdp' => password_hash($nouveau, PASSWORD_DEFAULT),
                    ':id' => $_SESSION['utilisateur']['idUtilisateur'],
                ]);

            session_regenerate_id(true);
            unset($_SESSION['parametres_echecs']);

            flash('succes', 'Ton mot de passe a été modifié.');

            rediriger('parametres/index.php');
        }
    }
}

$titre = 'Paramètres';
$actif = 'parametres';

$u = utilisateurConnecte();

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte" style="max-width:560px;">

    <h2>Mon compte</h2>

    <p><strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong></p>
    <p style="color:var(--gris);"><?= e($u['email']) ?> — <?= e(ROLES[$u['role']] ?? '') ?></p>

</div>

<div class="carte" style="max-width:560px;">

    <h2>Changer mon mot de passe</h2>

    <?php if ($erreurs): ?>
        <ul class="erreurs"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <form method="post" autocomplete="off">

        <?= csrfChamp() ?>

        <div class="champ">
            <label for="actuel">Mot de passe actuel</label>
            <input type="password" id="actuel" name="actuel" required autocomplete="current-password">
        </div>

        <div class="champ">
            <label for="nouveau">Nouveau mot de passe</label>
            <input type="password" id="nouveau" name="nouveau" minlength="8" required autocomplete="new-password">
            <small>8 caractères minimum.</small>
        </div>

        <div class="champ">
            <label for="confirmation">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirmation" name="confirmation" minlength="8" required autocomplete="new-password">
        </div>

        <div class="pied-form">
            <button type="submit" class="btn">Enregistrer</button>
        </div>

    </form>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
