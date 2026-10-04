<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {

    csrfVerifier();

    $id = (int) ($_POST['idEtudiant'] ?? 0);

    $requete = $connexion->prepare("SELECT photo FROM etudiant WHERE idEtudiant = :id");
    $requete->execute([':id' => $id]);
    $etudiant = $requete->fetch();

    if ($etudiant) {

        // Les inscriptions de l'étudiant sont supprimées avec lui (clé étrangère en cascade)
        $connexion->prepare("DELETE FROM etudiant WHERE idEtudiant = :id")->execute([':id' => $id]);

        supprimerPhoto($etudiant['photo']);

        flash('succes', "Le dossier de l'étudiant a été supprimé, avec ses inscriptions.");
    }

    rediriger('etudiants/index.php');
}

$titre = 'Liste des étudiants';
$actif = 'etudiants';

$q = parametreGet('q');
$idFiliere = (int) parametreGet('idFiliere');
$idClasse = (int) parametreGet('idClasse');
$sexe = parametreGet('sexe');

$filieres = $connexion->query("SELECT idFiliere, nomFiliere FROM filiere ORDER BY nomFiliere")->fetchAll();
$classes = $connexion->query("SELECT idClasse, nomClasse, annee, idFiliere FROM classe ORDER BY annee DESC, nomClasse")->fetchAll();

/* Chaque étudiant est présenté avec sa dernière inscription non annulée */

$from = "
    FROM etudiant e
    LEFT JOIN inscription i ON i.idInscription = (
        SELECT i2.idInscription
        FROM inscription i2
        WHERE i2.idEtudiant = e.idEtudiant AND i2.statut <> 'ANNULEE'
        ORDER BY i2.dateInscription DESC, i2.idInscription DESC
        LIMIT 1
    )
    LEFT JOIN classe c ON c.idClasse = i.idClasse
    LEFT JOIN filiere f ON f.idFiliere = c.idFiliere
";

$conditions = [];
$parametres = [];

if ($q !== '') {
    $conditions[] = '(e.nom_Etud LIKE :q1 OR e.prenom_Etud LIKE :q2 OR e.matricule LIKE :q3)';
    $parametres[':q1'] = $parametres[':q2'] = $parametres[':q3'] = '%' . $q . '%';
}

if ($idFiliere > 0) {
    $conditions[] = 'f.idFiliere = :idFiliere';
    $parametres[':idFiliere'] = $idFiliere;
}

if ($idClasse > 0) {
    $conditions[] = 'c.idClasse = :idClasse';
    $parametres[':idClasse'] = $idClasse;
}

if (in_array($sexe, ['M', 'F'], true)) {
    $conditions[] = 'e.sexe = :sexe';
    $parametres[':sexe'] = $sexe;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$requete = $connexion->prepare("SELECT COUNT(*) $from $where");
$requete->execute($parametres);

$p = pagination((int) $requete->fetchColumn(), 15, (int) ($_GET['page'] ?? 1));

$requete = $connexion->prepare("
    SELECT e.idEtudiant, e.matricule, e.nom_Etud, e.prenom_Etud, e.sexe, e.telephone, e.photo,
           c.nomClasse, f.nomFiliere
    $from $where
    ORDER BY e.nom_Etud, e.prenom_Etud
    LIMIT {$p['limite']} OFFSET {$p['offset']}
");

$requete->execute($parametres);
$etudiants = $requete->fetchAll();

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">
    <p><?= (int) $p['total'] ?> étudiant(s) trouvé(s).</p>
    <a class="btn" href="<?= e(url('etudiants/formulaire.php')) ?>">+ Ajouter un étudiant</a>
</div>

<div class="carte">

    <form method="get" class="filtres">

        <div class="champ" style="flex:1; min-width:220px;">
            <label for="q">Recherche</label>
            <input type="text" id="q" name="q" placeholder="Nom, prénom ou matricule" value="<?= e($q) ?>">
        </div>

        <div class="champ">
            <label for="idFiliere">Filière</label>
            <select id="idFiliere" name="idFiliere" data-filtre-cible="idClasse">
                <option value="">Toutes</option>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?= (int) $f['idFiliere'] ?>" <?= $idFiliere === (int) $f['idFiliere'] ? 'selected' : '' ?>><?= e($f['nomFiliere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="idClasse">Classe</label>
            <select id="idClasse" name="idClasse">
                <option value="">Toutes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= (int) $c['idClasse'] ?>" data-filiere="<?= (int) $c['idFiliere'] ?>" <?= $idClasse === (int) $c['idClasse'] ? 'selected' : '' ?>><?= e($c['nomClasse'] . ' (' . $c['annee'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="sexe">Sexe</label>
            <select id="sexe" name="sexe">
                <option value="">Tous</option>
                <option value="M" <?= $sexe === 'M' ? 'selected' : '' ?>>Masculin</option>
                <option value="F" <?= $sexe === 'F' ? 'selected' : '' ?>>Féminin</option>
            </select>
        </div>

        <button type="submit" class="btn">Filtrer</button>

    </form>

</div>

<div class="carte">

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th></th><th>Matricule</th><th>Nom et prénom</th><th>Sexe</th><th>Classe</th><th>Filière</th><th>Téléphone</th><th>Actions</th></tr>
            </thead>

            <tbody>

                <?php foreach ($etudiants as $et): ?>

                    <tr>
                        <td><?= avatarEtudiant($et) ?></td>
                        <td><?= e($et['matricule']) ?></td>
                        <td><strong><?= e($et['nom_Etud'] . ' ' . $et['prenom_Etud']) ?></strong></td>
                        <td><?= e($et['sexe']) ?></td>
                        <td><?= e($et['nomClasse'] ?? '—') ?></td>
                        <td><?= e($et['nomFiliere'] ?? '—') ?></td>
                        <td><?= e($et['telephone']) ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-sec btn-petit" href="<?= e(url('etudiants/voir.php?id=' . (int) $et['idEtudiant'])) ?>">Voir</a>
                                <a class="btn btn-sec btn-petit" href="<?= e(url('etudiants/formulaire.php?id=' . (int) $et['idEtudiant'])) ?>">Modifier</a>
                                <form method="post" data-confirm="Supprimer définitivement cet étudiant et toutes ses inscriptions ?">
                                    <?= csrfChamp() ?>
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="idEtudiant" value="<?= (int) $et['idEtudiant'] ?>">
                                    <button type="submit" class="btn btn-danger btn-petit">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if (!$etudiants): ?>
            <p class="vide">Aucun étudiant trouvé.</p>
        <?php endif; ?>

    </div>

    <?php afficherPagination($p); ?>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
