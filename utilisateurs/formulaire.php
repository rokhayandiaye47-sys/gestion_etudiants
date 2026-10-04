<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur']);

$id = (int) ($_GET['id'] ?? $_POST['idUtilisateur'] ?? 0);
$compte = null;

if ($id > 0) {

    $requete = $connexion->prepare("SELECT * FROM utilisateur WHERE idUtilisateur = :id");
    $requete->execute([':id' => $id]);
    $compte = $requete->fetch();

    if (!$compte) {
        flash('erreur', 'Utilisateur introuvable.');
        rediriger('utilisateurs/index.php');
    }
}

$estMoi = $compte && (int) $compte['idUtilisateur'] === (int) $_SESSION['utilisateur']['idUtilisateur'];

$val = [
    'nom' => $compte['nom'] ?? '',
    'prenom' => $compte['prenom'] ?? '',
    'email' => $compte['email'] ?? '',
    'role' => $compte['role'] ?? 'scolarite',
];

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    $val['nom'] = trim((string) ($_POST['nom'] ?? ''));
    $val['prenom'] = trim((string) ($_POST['prenom'] ?? ''));
    $val['email'] = trim((string) ($_POST['email'] ?? ''));

    // On ne peut pas changer son propre rôle (pour ne pas se retirer l'accès d'administrateur)
    $val['role'] = $estMoi ? $compte['role'] : (string) ($_POST['role'] ?? '');

    $motDePasse = (string) ($_POST['motdepass'] ?? '');
    $confirmation = (string) ($_POST['confirmation'] ?? '');

    if ($val['nom'] === '' || $val['prenom'] === '' || mb_strlen($val['nom']) > 50 || mb_strlen($val['prenom']) > 50) {
        $erreurs[] = 'Le nom et le prénom sont obligatoires (50 caractères maximum).';
    }

    if (!filter_var($val['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($val['email']) > 100) {
        $erreurs[] = "L'email n'est pas valide.";
    }

    if (!isset(ROLES[$val['role']])) {
        $erreurs[] = 'Choisis un rôle.';
    }

    if (!$compte || $motDePasse !== '') {

        if (strlen($motDePasse) < 8) {
            $erreurs[] = 'Le mot de passe doit faire 8 caractères au minimum.';
        } elseif ($motDePasse !== $confirmation) {
            $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
        }
    }

    if (!$erreurs) {

        $verif = $connexion->prepare("SELECT 1 FROM utilisateur WHERE email = :email AND idUtilisateur <> :id");
        $verif->execute([':email' => $val['email'], ':id' => $id]);

        if ($verif->fetchColumn()) {
            $erreurs[] = 'Cet email est déjà utilisé par un autre compte.';
        }
    }

    if (!$erreurs) {

        if ($compte) {

            $parametres = [
                ':nom' => $val['nom'], ':prenom' => $val['prenom'],
                ':email' => $val['email'], ':role' => $val['role'], ':id' => $id,
            ];

            $sql = "UPDATE utilisateur SET nom = :nom, prenom = :prenom, email = :email, role = :role";

            if ($motDePasse !== '') {
                $sql .= ", motdepass = :motdepass";
                $parametres[':motdepass'] = password_hash($motDePasse, PASSWORD_DEFAULT);
            }

            $connexion->prepare($sql . " WHERE idUtilisateur = :id")->execute($parametres);

            if ($estMoi) {
                $_SESSION['utilisateur']['nom'] = $val['nom'];
                $_SESSION['utilisateur']['prenom'] = $val['prenom'];
                $_SESSION['utilisateur']['email'] = $val['email'];
            }

            flash('succes', 'Utilisateur modifié.');

        } else {

            // Règle de gestion R6 : l'administrateur crée les autres utilisateurs
            $connexion->prepare("
                INSERT INTO utilisateur (nom, prenom, email, motdepass, role, actif)
                VALUES (:nom, :prenom, :email, :motdepass, :role, 1)
            ")->execute([
                ':nom' => $val['nom'], ':prenom' => $val['prenom'], ':email' => $val['email'],
                ':motdepass' => password_hash($motDePasse, PASSWORD_DEFAULT), ':role' => $val['role'],
            ]);

            flash('succes', 'Utilisateur créé. Communique-lui son email et son mot de passe.');
        }

        rediriger('utilisateurs/index.php');
    }
}

$titre = $compte ? "Modifier l'utilisateur" : 'Nouvel utilisateur';
$actif = 'utilisateurs';

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte" style="max-width:640px;">

    <?php if ($erreurs): ?>
        <ul class="erreurs"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <form method="post" autocomplete="off">

        <?= csrfChamp() ?>
        <input type="hidden" name="idUtilisateur" value="<?= $id ?>">

        <div class="grille-form" style="grid-template-columns: 1fr 1fr;">

            <div class="champ">
                <label for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" maxlength="50" required value="<?= e($val['nom']) ?>">
            </div>

            <div class="champ">
                <label for="prenom">Prénom *</label>
                <input type="text" id="prenom" name="prenom" maxlength="50" required value="<?= e($val['prenom']) ?>">
            </div>

            <div class="champ">
                <label for="email">Email (pour la connexion) *</label>
                <input type="email" id="email" name="email" maxlength="100" required value="<?= e($val['email']) ?>">
            </div>

            <div class="champ">
                <label for="role">Rôle *</label>
                <select id="role" name="role" <?= $estMoi ? 'disabled' : 'required' ?>>
                    <?php foreach (ROLES as $code => $libelle): ?>
                        <option value="<?= e($code) ?>" <?= $val['role'] === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($estMoi): ?><small>Tu ne peux pas changer ton propre rôle.</small><?php endif; ?>
            </div>

            <div class="champ">
                <label for="motdepass"><?= $compte ? 'Nouveau mot de passe' : 'Mot de passe *' ?></label>
                <input type="password" id="motdepass" name="motdepass" minlength="8" autocomplete="new-password" <?= $compte ? '' : 'required' ?>>
                <small><?= $compte ? 'Laisse vide pour ne pas le changer. ' : '' ?>8 caractères minimum.</small>
            </div>

            <div class="champ">
                <label for="confirmation">Confirmer le mot de passe</label>
                <input type="password" id="confirmation" name="confirmation" minlength="8" autocomplete="new-password" <?= $compte ? '' : 'required' ?>>
            </div>

        </div>

        <div class="pied-form">
            <a class="btn btn-sec" href="<?= e(url('utilisateurs/index.php')) ?>">Annuler</a>
            <button type="submit" class="btn">Enregistrer</button>
        </div>

    </form>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
