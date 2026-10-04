<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur']);

$id = (int) ($_GET['id'] ?? $_POST['idClasse'] ?? 0);
$classe = null;

if ($id > 0) {

    $requete = $connexion->prepare("SELECT * FROM classe WHERE idClasse = :id");
    $requete->execute([':id' => $id]);
    $classe = $requete->fetch();

    if (!$classe) {
        flash('erreur', 'Classe introuvable.');
        rediriger('classes/index.php');
    }
}

$filieres = $connexion->query("SELECT idFiliere, nomFiliere FROM filiere ORDER BY nomFiliere")->fetchAll();

$val = [
    'nomClasse' => $classe['nomClasse'] ?? '',
    'niveau' => $classe['niveau'] ?? '',
    'annee' => $classe['annee'] ?? anneeCourante($connexion),
    'idFiliere' => $classe['idFiliere'] ?? '',
];

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    $val['nomClasse'] = trim((string) ($_POST['nomClasse'] ?? ''));
    $val['niveau'] = trim((string) ($_POST['niveau'] ?? ''));
    $val['annee'] = trim((string) ($_POST['annee'] ?? ''));
    $val['idFiliere'] = (int) ($_POST['idFiliere'] ?? 0);

    if ($val['nomClasse'] === '' || mb_strlen($val['nomClasse']) > 50) {
        $erreurs[] = 'Le nom de la classe est obligatoire (50 caractères maximum).';
    }

    if ($val['niveau'] === '' || mb_strlen($val['niveau']) > 20) {
        $erreurs[] = 'Le niveau est obligatoire (20 caractères maximum).';
    }

    if (!preg_match('/^(\d{4})-(\d{4})$/', $val['annee'], $m) || (int) $m[2] !== (int) $m[1] + 1) {
        $erreurs[] = "L'année académique doit ressembler à 2025-2026.";
    }

    $verifFiliere = $connexion->prepare("SELECT 1 FROM filiere WHERE idFiliere = :id");
    $verifFiliere->execute([':id' => $val['idFiliere']]);

    if (!$verifFiliere->fetchColumn()) {
        $erreurs[] = 'Choisis une filière.';
    }

    if (!$erreurs) {

        $verif = $connexion->prepare("
            SELECT 1 FROM classe
            WHERE nomClasse = :nom AND annee = :annee AND idClasse <> :id
        ");

        $verif->execute([':nom' => $val['nomClasse'], ':annee' => $val['annee'], ':id' => $id]);

        if ($verif->fetchColumn()) {
            $erreurs[] = 'Cette classe existe déjà pour cette année académique.';
        }
    }

    if (!$erreurs) {

        $parametres = [
            ':nom' => $val['nomClasse'],
            ':niveau' => $val['niveau'],
            ':annee' => $val['annee'],
            ':filiere' => $val['idFiliere'],
        ];

        if ($classe) {

            $parametres[':id'] = $id;

            $connexion->prepare("
                UPDATE classe
                SET nomClasse = :nom, niveau = :niveau, annee = :annee, idFiliere = :filiere
                WHERE idClasse = :id
            ")->execute($parametres);

            flash('succes', 'Classe modifiée.');

        } else {

            $connexion->prepare("
                INSERT INTO classe (nomClasse, niveau, annee, idFiliere)
                VALUES (:nom, :niveau, :annee, :filiere)
            ")->execute($parametres);

            flash('succes', 'Classe ajoutée.');
        }

        rediriger('classes/index.php');
    }
}

$titre = $classe ? 'Modifier la classe' : 'Nouvelle classe';
$actif = 'classes';

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte" style="max-width:640px;">

    <?php if ($erreurs): ?>
        <ul class="erreurs"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <?php if (!$filieres): ?>
        <div class="flash flash-erreur">Crée d'abord une filière avant d'ajouter une classe.</div>
    <?php endif; ?>

    <form method="post">

        <?= csrfChamp() ?>
        <input type="hidden" name="idClasse" value="<?= $id ?>">

        <div class="champ">
            <label for="idFiliere">Filière *</label>
            <select id="idFiliere" name="idFiliere" required>
                <option value="">Choisir une filière</option>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?= (int) $f['idFiliere'] ?>" <?= (int) $val['idFiliere'] === (int) $f['idFiliere'] ? 'selected' : '' ?>><?= e($f['nomFiliere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="nomClasse">Nom de la classe *</label>
            <input type="text" id="nomClasse" name="nomClasse" maxlength="50" required placeholder="Ex : L2 IG" value="<?= e($val['nomClasse']) ?>">
        </div>

        <div class="champ">
            <label for="niveau">Niveau *</label>
            <input type="text" id="niveau" name="niveau" maxlength="20" required list="niveaux" placeholder="Ex : Licence 2" value="<?= e($val['niveau']) ?>">
            <datalist id="niveaux">
                <option value="Licence 1"><option value="Licence 2"><option value="Licence 3">
                <option value="Master 1"><option value="Master 2"><option value="BTS 1"><option value="BTS 2">
            </datalist>
        </div>

        <div class="champ">
            <label for="annee">Année académique *</label>
            <input type="text" id="annee" name="annee" maxlength="9" required placeholder="2025-2026" value="<?= e($val['annee']) ?>">
        </div>

        <div class="pied-form">
            <a class="btn btn-sec" href="<?= e(url('classes/index.php')) ?>">Annuler</a>
            <button type="submit" class="btn">Enregistrer</button>
        </div>

    </form>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
