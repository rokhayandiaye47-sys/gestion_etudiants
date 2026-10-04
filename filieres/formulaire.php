<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur']);

$id = (int) ($_GET['id'] ?? $_POST['idFiliere'] ?? 0);
$filiere = null;

if ($id > 0) {

    $requete = $connexion->prepare("SELECT * FROM filiere WHERE idFiliere = :id");
    $requete->execute([':id' => $id]);
    $filiere = $requete->fetch();

    if (!$filiere) {
        flash('erreur', 'Filière introuvable.');
        rediriger('filieres/index.php');
    }
}

$val = [
    'nomFiliere' => $filiere['nomFiliere'] ?? '',
    'description' => $filiere['description'] ?? '',
];

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    $val['nomFiliere'] = trim((string) ($_POST['nomFiliere'] ?? ''));
    $val['description'] = trim((string) ($_POST['description'] ?? ''));

    if ($val['nomFiliere'] === '' || mb_strlen($val['nomFiliere']) > 100) {
        $erreurs[] = 'Le nom de la filière est obligatoire (100 caractères maximum).';
    }

    if (mb_strlen($val['description']) > 255) {
        $erreurs[] = 'La description doit faire 255 caractères au maximum.';
    }

    if (!$erreurs) {

        $verif = $connexion->prepare("SELECT 1 FROM filiere WHERE nomFiliere = :nom AND idFiliere <> :id");
        $verif->execute([':nom' => $val['nomFiliere'], ':id' => $id]);

        if ($verif->fetchColumn()) {
            $erreurs[] = 'Une filière porte déjà ce nom.';
        }
    }

    if (!$erreurs) {

        if ($filiere) {

            $connexion->prepare("UPDATE filiere SET nomFiliere = :nom, description = :description WHERE idFiliere = :id")
                ->execute([':nom' => $val['nomFiliere'], ':description' => $val['description'], ':id' => $id]);

            flash('succes', 'Filière modifiée.');

        } else {

            $connexion->prepare("INSERT INTO filiere (nomFiliere, description) VALUES (:nom, :description)")
                ->execute([':nom' => $val['nomFiliere'], ':description' => $val['description']]);

            flash('succes', 'Filière ajoutée.');
        }

        rediriger('filieres/index.php');
    }
}

$titre = $filiere ? 'Modifier la filière' : 'Nouvelle filière';
$actif = 'filieres';

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte" style="max-width:640px;">

    <?php if ($erreurs): ?>
        <ul class="erreurs"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <form method="post">

        <?= csrfChamp() ?>
        <input type="hidden" name="idFiliere" value="<?= $id ?>">

        <div class="champ">
            <label for="nomFiliere">Nom de la filière *</label>
            <input type="text" id="nomFiliere" name="nomFiliere" maxlength="100" required value="<?= e($val['nomFiliere']) ?>">
        </div>

        <div class="champ">
            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="255"><?= e($val['description']) ?></textarea>
        </div>

        <div class="pied-form">
            <a class="btn btn-sec" href="<?= e(url('filieres/index.php')) ?>">Annuler</a>
            <button type="submit" class="btn">Enregistrer</button>
        </div>

    </form>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
