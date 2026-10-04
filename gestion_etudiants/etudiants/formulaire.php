<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$id = (int) ($_GET['id'] ?? $_POST['idEtudiant'] ?? 0);
$etudiant = null;

if ($id > 0) {

    $requete = $connexion->prepare("SELECT * FROM etudiant WHERE idEtudiant = :id");
    $requete->execute([':id' => $id]);
    $etudiant = $requete->fetch();

    if (!$etudiant) {
        flash('erreur', 'Étudiant introuvable.');
        rediriger('etudiants/index.php');
    }
}

$edition = $etudiant !== null;

$filieres = $connexion->query("SELECT idFiliere, nomFiliere FROM filiere ORDER BY nomFiliere")->fetchAll();
$classes = $connexion->query("SELECT idClasse, nomClasse, annee, idFiliere FROM classe ORDER BY annee DESC, nomClasse")->fetchAll();

$val = [
    'matricule' => $etudiant['matricule'] ?? '',
    'nom_Etud' => $etudiant['nom_Etud'] ?? '',
    'prenom_Etud' => $etudiant['prenom_Etud'] ?? '',
    'sexe' => $etudiant['sexe'] ?? '',
    'dateNaissance' => $etudiant['dateNaissance'] ?? '',
    'lieuNaissance' => $etudiant['lieuNaissance'] ?? '',
    'telephone' => $etudiant['telephone'] ?? '',
    'email_Etud' => $etudiant['email_Etud'] ?? '',
    'adresse' => $etudiant['adresse'] ?? '',
    'idFiliere' => '',
    'idClasse' => '',
    'statut' => 'CONFIRMEE',
    'montant' => '',
];

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerifier();

    foreach ($val as $cle => $inutile) {
        $val[$cle] = trim((string) ($_POST[$cle] ?? ''));
    }

    $val['matricule'] = strtoupper($val['matricule']);

    /* Vérifications */

    if ($val['nom_Etud'] === '' || mb_strlen($val['nom_Etud']) > 50) {
        $erreurs[] = 'Le nom est obligatoire (50 caractères maximum).';
    }

    if ($val['prenom_Etud'] === '' || mb_strlen($val['prenom_Etud']) > 50) {
        $erreurs[] = 'Le prénom est obligatoire (50 caractères maximum).';
    }

    if (!in_array($val['sexe'], ['M', 'F'], true)) {
        $erreurs[] = 'Choisis le sexe.';
    }

    $naissance = DateTime::createFromFormat('Y-m-d', $val['dateNaissance']);

    if (!$naissance || $naissance->format('Y-m-d') !== $val['dateNaissance'] || $naissance > new DateTime('today')) {
        $erreurs[] = 'La date de naissance est invalide.';
    }

    if (!preg_match('/^[0-9+().\s-]{6,20}$/', $val['telephone'])) {
        $erreurs[] = 'Le numéro de téléphone est obligatoire et doit être valide.';
    }

    if ($val['email_Etud'] !== '' && (!filter_var($val['email_Etud'], FILTER_VALIDATE_EMAIL) || mb_strlen($val['email_Etud']) > 100)) {
        $erreurs[] = "L'email n'est pas valide.";
    }

    if (mb_strlen($val['adresse']) > 100 || mb_strlen($val['lieuNaissance']) > 100) {
        $erreurs[] = "L'adresse et le lieu de naissance doivent faire 100 caractères au maximum.";
    }

    if ($val['matricule'] !== '') {

        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $val['matricule'])) {
            $erreurs[] = 'Le matricule ne peut contenir que des lettres, chiffres et tirets (3 à 20 caractères).';
        } else {

            // Règle de gestion R1 : matricule unique
            $verif = $connexion->prepare("SELECT 1 FROM etudiant WHERE matricule = :matricule AND idEtudiant <> :id");
            $verif->execute([':matricule' => $val['matricule'], ':id' => $id]);

            if ($verif->fetchColumn()) {
                $erreurs[] = 'Ce matricule est déjà attribué à un autre étudiant.';
            }
        }
    }

    if (!$edition) {

        $classeChoisie = null;

        foreach ($classes as $c) {
            if ((int) $c['idClasse'] === (int) $val['idClasse']) {
                $classeChoisie = $c;
            }
        }

        if (!$classeChoisie || (int) $classeChoisie['idFiliere'] !== (int) $val['idFiliere']) {
            $erreurs[] = 'Choisis une filière et une classe qui correspond à cette filière.';
        }

        if (!isset(STATUTS_INSCRIPTION[$val['statut']])) {
            $erreurs[] = "Choisis le statut de l'inscription.";
        }

        if ($val['montant'] !== '' && (!is_numeric($val['montant']) || (float) $val['montant'] < 0)) {
            $erreurs[] = 'Le montant payé doit être un nombre positif.';
        }
    }

    $nouvellePhoto = null;

    if (!$erreurs) {
        $nouvellePhoto = enregistrerPhoto($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], $erreurs);
    }

    /* Enregistrement */

    if (!$erreurs) {

        try {

            $connexion->beginTransaction();

            $champs = [
                ':nom' => $val['nom_Etud'],
                ':prenom' => $val['prenom_Etud'],
                ':sexe' => $val['sexe'],
                ':naissance' => $val['dateNaissance'],
                ':lieu' => $val['lieuNaissance'] !== '' ? $val['lieuNaissance'] : null,
                ':adresse' => $val['adresse'] !== '' ? $val['adresse'] : null,
                ':telephone' => $val['telephone'],
                ':email' => $val['email_Etud'] !== '' ? $val['email_Etud'] : null,
            ];

            if ($edition) {

                $champs[':matricule'] = $val['matricule'] !== '' ? $val['matricule'] : $etudiant['matricule'];
                $champs[':photo'] = $nouvellePhoto ?? $etudiant['photo'];
                $champs[':id'] = $id;

                $connexion->prepare("
                    UPDATE etudiant SET
                        matricule = :matricule, nom_Etud = :nom, prenom_Etud = :prenom, sexe = :sexe,
                        dateNaissance = :naissance, lieuNaissance = :lieu, adresse = :adresse,
                        telephone = :telephone, email_Etud = :email, photo = :photo
                    WHERE idEtudiant = :id
                ")->execute($champs);

            } else {

                $champs[':matricule'] = $val['matricule'] !== '' ? $val['matricule'] : genererMatricule($connexion);
                $champs[':photo'] = $nouvellePhoto;

                $connexion->prepare("
                    INSERT INTO etudiant
                        (matricule, nom_Etud, prenom_Etud, sexe, dateNaissance, lieuNaissance, adresse, telephone, email_Etud, photo)
                    VALUES
                        (:matricule, :nom, :prenom, :sexe, :naissance, :lieu, :adresse, :telephone, :email, :photo)
                ")->execute($champs);

                $idEtudiant = (int) $connexion->lastInsertId();

                // Règles R2 et R7 : l'inscription est enregistrée avec l'étudiant
                $connexion->prepare("
                    INSERT INTO inscription (dateInscription, statut, montant, idEtudiant, idUtilisateur, idClasse)
                    VALUES (CURDATE(), :statut, :montant, :etudiant, :utilisateur, :classe)
                ")->execute([
                    ':statut' => $val['statut'],
                    ':montant' => $val['montant'] !== '' ? $val['montant'] : 0,
                    ':etudiant' => $idEtudiant,
                    ':utilisateur' => $_SESSION['utilisateur']['idUtilisateur'],
                    ':classe' => (int) $val['idClasse'],
                ]);
            }

            $connexion->commit();

            if ($edition && $nouvellePhoto) {
                supprimerPhoto($etudiant['photo']);
            }

            flash('succes', $edition ? 'Informations de l\'étudiant modifiées.' : 'Étudiant enregistré et inscrit.');

            rediriger('etudiants/index.php');

        } catch (PDOException $e) {

            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            supprimerPhoto($nouvellePhoto);

            error_log('Enregistrement étudiant : ' . $e->getMessage());

            $erreurs[] = "Une erreur est survenue pendant l'enregistrement (le matricule existe peut-être déjà).";
        }
    }
}

$titre = $edition ? 'Modifier un étudiant' : 'Ajouter un étudiant';
$actif = 'etudiants';

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte">

    <?php if ($erreurs): ?>
        <ul class="erreurs"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <?= csrfChamp() ?>
        <input type="hidden" name="idEtudiant" value="<?= $id ?>">

        <div class="grille-form">

            <div class="titre-section">Informations personnelles</div>

            <div class="champ">
                <label for="matricule">Matricule</label>
                <input type="text" id="matricule" name="matricule" maxlength="20" placeholder="Ex : ESGN24011" value="<?= e($val['matricule']) ?>">
                <small>Laisse vide pour générer un matricule automatiquement.</small>
            </div>

            <div class="champ">
                <label for="nom_Etud">Nom *</label>
                <input type="text" id="nom_Etud" name="nom_Etud" maxlength="50" required value="<?= e($val['nom_Etud']) ?>">
            </div>

            <div class="champ">
                <label for="prenom_Etud">Prénom *</label>
                <input type="text" id="prenom_Etud" name="prenom_Etud" maxlength="50" required value="<?= e($val['prenom_Etud']) ?>">
            </div>

            <div class="champ">
                <label for="dateNaissance">Date de naissance *</label>
                <input type="date" id="dateNaissance" name="dateNaissance" required max="<?= date('Y-m-d') ?>" value="<?= e($val['dateNaissance']) ?>">
            </div>

            <div class="champ">
                <label for="lieuNaissance">Lieu de naissance</label>
                <input type="text" id="lieuNaissance" name="lieuNaissance" maxlength="100" value="<?= e($val['lieuNaissance']) ?>">
            </div>

            <div class="champ">
                <label for="sexe">Sexe *</label>
                <select id="sexe" name="sexe" required>
                    <option value="">Sélectionner le sexe</option>
                    <option value="M" <?= $val['sexe'] === 'M' ? 'selected' : '' ?>>Masculin</option>
                    <option value="F" <?= $val['sexe'] === 'F' ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>

            <div class="champ">
                <label for="telephone">Téléphone *</label>
                <input type="text" id="telephone" name="telephone" maxlength="20" required placeholder="Ex : 77 123 45 67" value="<?= e($val['telephone']) ?>">
            </div>

            <div class="champ">
                <label for="email_Etud">Email</label>
                <input type="email" id="email_Etud" name="email_Etud" maxlength="100" placeholder="etudiant@esgn.sn" value="<?= e($val['email_Etud']) ?>">
            </div>

            <div class="champ">
                <label for="photo">Photo</label>
                <input type="file" id="photo" name="photo" accept="image/png,image/jpeg">
                <small>PNG ou JPG, 2 Mo maximum.</small>
            </div>

            <div class="champ large">
                <label for="adresse">Adresse</label>
                <input type="text" id="adresse" name="adresse" maxlength="100" value="<?= e($val['adresse']) ?>">
            </div>

            <?php if ($edition): ?>

                <div class="pleine">
                    <p style="color:var(--gris);">
                        La filière et la classe se modifient depuis la fiche de l'étudiant, dans ses
                        <a href="<?= e(url('inscriptions/index.php?q=' . urlencode($etudiant['matricule']))) ?>">inscriptions</a>.
                    </p>
                </div>

            <?php else: ?>

                <div class="titre-section">Informations académiques (inscription)</div>

                <div class="champ">
                    <label for="idFiliere">Filière *</label>
                    <select id="idFiliere" name="idFiliere" required data-filtre-cible="idClasse">
                        <option value="">Sélectionner une filière</option>
                        <?php foreach ($filieres as $f): ?>
                            <option value="<?= (int) $f['idFiliere'] ?>" <?= (int) $val['idFiliere'] === (int) $f['idFiliere'] ? 'selected' : '' ?>><?= e($f['nomFiliere']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="champ">
                    <label for="idClasse">Classe *</label>
                    <select id="idClasse" name="idClasse" required>
                        <option value="">Sélectionner une classe</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c['idClasse'] ?>" data-filiere="<?= (int) $c['idFiliere'] ?>" <?= (int) $val['idClasse'] === (int) $c['idClasse'] ? 'selected' : '' ?>><?= e($c['nomClasse'] . ' (' . $c['annee'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="champ">
                    <label for="statut">Statut de l'inscription *</label>
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

            <?php endif; ?>

        </div>

        <div class="pied-form">
            <a class="btn btn-sec" href="<?= e(url('etudiants/index.php')) ?>">Annuler</a>
            <button type="submit" class="btn"><?= $edition ? 'Enregistrer les modifications' : "Enregistrer l'étudiant" ?></button>
        </div>

    </form>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
