<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$id = (int) ($_GET['id'] ?? $_POST['idInscription'] ?? 0);
$inscription = null;

if ($id > 0) {

    $requete = $connexion->prepare("SELECT * FROM inscription WHERE idInscription = :id");
    $requete->execute([':id' => $id]);
    $inscription = $requete->fetch();

    if (!$inscription) {
        flash('erreur', 'Inscription introuvable.');
        rediriger('inscriptions/index.php');
    }
}

$etudiants = $connexion->query("SELECT idEtudiant, matricule, nom_Etud, prenom_Etud FROM etudiant ORDER BY nom_Etud, prenom_Etud")->fetchAll();

$classes = $connexion->query("
    SELECT c.idClasse, c.nomClasse, c.annee, f.nomFiliere
    FROM classe c
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
    ORDER BY c.annee DESC, f.nomFiliere, c.nomClasse
")->fetchAll();

$val = [
    'idEtudiant' => $inscription['idEtudiant'] ?? (int) ($_GET['idEtudiant'] ?? 0),
    'idClasse' => $inscription['idClasse'] ?? '',
    'dateInscription' => $inscription['dateInscription'] ?? date('Y-m-d'),
    'statut' => $inscription['statut'] ?? 'CONFIRMEE',
    'montant' => $inscription['montant'] ?? '',
];

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    foreach ($val as $cle => $inutile) {
        $val[$cle] = trim((string) ($_POST[$cle] ?? ''));
    }

    $verifEtudiant = $connexion->prepare("SELECT 1 FROM etudiant WHERE idEtudiant = :id");
    $verifEtudiant->execute([':id' => (int) $val['idEtudiant']]);

    if (!$verifEtudiant->fetchColumn()) {
        $erreurs[] = 'Choisis un étudiant.';
    }

    $requete = $connexion->prepare("SELECT annee FROM classe WHERE idClasse = :id");
    $requete->execute([':id' => (int) $val['idClasse']]);
    $anneeClasse = $requete->fetchColumn();

    if (!$anneeClasse) {
        $erreurs[] = 'Choisis une classe.';
    }

    $date = DateTime::createFromFormat('Y-m-d', $val['dateInscription']);

    if (!$date || $date->format('Y-m-d') !== $val['dateInscription']) {
        $erreurs[] = "La date d'inscription est invalide.";
    }

    if (!isset(STATUTS_INSCRIPTION[$val['statut']])) {
        $erreurs[] = 'Statut invalide.';
    }

    if ($val['montant'] !== '' && (!is_numeric($val['montant']) || (float) $val['montant'] < 0)) {
        $erreurs[] = 'Le montant payé doit être un nombre positif.';
    }

    // Règle de gestion R2 : une seule inscription (non annulée) par étudiant et par année académique
    if (!$erreurs && $anneeClasse && $val['statut'] !== 'ANNULEE') {

        $verif = $connexion->prepare("
            SELECT COUNT(*)
            FROM inscription i
            INNER JOIN classe c ON c.idClasse = i.idClasse
            WHERE i.idEtudiant = :etudiant
              AND i.statut <> 'ANNULEE'
              AND c.annee = :annee
              AND i.idInscription <> :id
        ");

        $verif->execute([':etudiant' => (int) $val['idEtudiant'], ':annee' => $anneeClasse, ':id' => $id]);

        if ((int) $verif->fetchColumn() > 0) {
            $erreurs[] = "Cet étudiant a déjà une inscription active pour l'année $anneeClasse (une seule filière et une seule classe par année).";
        }
    }

    if (!$erreurs) {

        $parametres = [
            ':date' => $val['dateInscription'],
            ':statut' => $val['statut'],
            ':montant' => $val['montant'] !== '' ? $val['montant'] : 0,
            ':etudiant' => (int) $val['idEtudiant'],
            ':classe' => (int) $val['idClasse'],
        ];

        if ($inscription) {

            $parametres[':id'] = $id;

            $connexion->prepare("
                UPDATE inscription
                SET dateInscription = :date, statut = :statut, montant = :montant, idEtudiant = :etudiant, idClasse = :classe
                WHERE idInscription = :id
            ")->execute($parametres);

            flash('succes', 'Inscription modifiée.');

        } else {

            $parametres[':utilisateur'] = $_SESSION['utilisateur']['idUtilisateur'];

            $connexion->prepare("
                INSERT INTO inscription (dateInscription, statut, montant, idEtudiant, idUtilisateur, idClasse)
                VALUES (:date, :statut, :montant, :etudiant, :utilisateur, :classe)
            ")->execute($parametres);

            flash('succes', 'Inscription enregistrée.');
        }

        rediriger('inscriptions/index.php');
    }
}

$titre = $inscription ? "Modifier l'inscription" : 'Nouvelle inscription';
$actif = 'inscriptions';

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte" style="max-width:720px;">

    <?php if ($erreurs): ?>
        <ul class="erreurs"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <form method="post">

        <?= csrfChamp() ?>
        <input type="hidden" name="idInscription" value="<?= $id ?>">

        <div class="champ">
            <label for="idEtudiant">Étudiant *</label>
            <select id="idEtudiant" name="idEtudiant" required>
                <option value="">Choisir un étudiant</option>
                <?php foreach ($etudiants as $et): ?>
                    <option value="<?= (int) $et['idEtudiant'] ?>" <?= (int) $val['idEtudiant'] === (int) $et['idEtudiant'] ? 'selected' : '' ?>>
                        <?= e($et['matricule'] . ' — ' . $et['nom_Etud'] . ' ' . $et['prenom_Etud']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="idClasse">Classe *</label>
            <select id="idClasse" name="idClasse" required>
                <option value="">Choisir une classe</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= (int) $c['idClasse'] ?>" <?= (int) $val['idClasse'] === (int) $c['idClasse'] ? 'selected' : '' ?>>
                        <?= e($c['nomClasse'] . ' — ' . $c['nomFiliere'] . ' (' . $c['annee'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grille-form" style="grid-template-columns: repeat(3, 1fr);">

            <div class="champ">
                <label for="dateInscription">Date d'inscription *</label>
                <input type="date" id="dateInscription" name="dateInscription" required value="<?= e($val['dateInscription']) ?>">
            </div>

            <div class="champ">
                <label for="statut">Statut *</label>
                <select id="statut" name="statut" required>
                    <?php foreach (STATUTS_INSCRIPTION as $code => $libelle): ?>
                        <option value="<?= e($code) ?>" <?= $val['statut'] === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ">
                <label for="montant">Montant payé (FCFA)</label>
                <input type="number" id="montant" name="montant" min="0" step="0.01" value="<?= e($val['montant']) ?>">
            </div>

        </div>

        <div class="pied-form">
            <a class="btn btn-sec" href="<?= e(url('inscriptions/index.php')) ?>">Annuler</a>
            <button type="submit" class="btn">Enregistrer</button>
        </div>

    </form>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
