<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {

    csrfVerifier();

    $connexion->prepare("DELETE FROM inscription WHERE idInscription = :id")
        ->execute([':id' => (int) ($_POST['idInscription'] ?? 0)]);

    flash('succes', 'Inscription supprimée.');

    rediriger('inscriptions/index.php');
}

$titre = 'Gestion des inscriptions';
$actif = 'inscriptions';

$q = parametreGet('q');
$statut = parametreGet('statut');
$idClasse = (int) parametreGet('idClasse');
$idFiliere = (int) parametreGet('idFiliere');
$annee = parametreGet('annee');

$filieres = $connexion->query("SELECT idFiliere, nomFiliere FROM filiere ORDER BY nomFiliere")->fetchAll();
$classes = $connexion->query("SELECT idClasse, nomClasse, annee, idFiliere FROM classe ORDER BY annee DESC, nomClasse")->fetchAll();
$annees = $connexion->query("SELECT DISTINCT annee FROM classe ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);

$from = "
    FROM inscription i
    INNER JOIN etudiant e ON e.idEtudiant = i.idEtudiant
    INNER JOIN classe c ON c.idClasse = i.idClasse
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
";

$conditions = [];
$parametres = [];

if ($q !== '') {
    $conditions[] = '(e.nom_Etud LIKE :q1 OR e.prenom_Etud LIKE :q2 OR e.matricule LIKE :q3)';
    $parametres[':q1'] = $parametres[':q2'] = $parametres[':q3'] = '%' . $q . '%';
}

if (isset(STATUTS_INSCRIPTION[$statut])) {
    $conditions[] = 'i.statut = :statut';
    $parametres[':statut'] = $statut;
}

if ($idClasse > 0) {
    $conditions[] = 'c.idClasse = :idClasse';
    $parametres[':idClasse'] = $idClasse;
}

if ($idFiliere > 0) {
    $conditions[] = 'f.idFiliere = :idFiliere';
    $parametres[':idFiliere'] = $idFiliere;
}

if ($annee !== '') {
    $conditions[] = 'c.annee = :annee';
    $parametres[':annee'] = $annee;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$requete = $connexion->prepare("SELECT COUNT(*) $from $where");
$requete->execute($parametres);

$p = pagination((int) $requete->fetchColumn(), 15, (int) ($_GET['page'] ?? 1));

$requete = $connexion->prepare("
    SELECT i.idInscription, i.dateInscription, i.statut, i.montant,
           e.idEtudiant, e.matricule, e.nom_Etud, e.prenom_Etud,
           c.nomClasse, c.annee, f.nomFiliere
    $from $where
    ORDER BY i.dateInscription DESC, i.idInscription DESC
    LIMIT {$p['limite']} OFFSET {$p['offset']}
");

$requete->execute($parametres);
$inscriptions = $requete->fetchAll();

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">
    <p><?= (int) $p['total'] ?> inscription(s) trouvée(s).</p>
    <a class="btn" href="<?= e(url('inscriptions/formulaire.php')) ?>">+ Nouvelle inscription</a>
</div>

<div class="carte">

    <form method="get" class="filtres">

        <div class="champ" style="flex:1; min-width:200px;">
            <label for="q">Recherche</label>
            <input type="text" id="q" name="q" placeholder="Nom de l'étudiant ou matricule" value="<?= e($q) ?>">
        </div>

        <div class="champ">
            <label for="statut">Statut</label>
            <select id="statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (STATUTS_INSCRIPTION as $code => $libelle): ?>
                    <option value="<?= e($code) ?>" <?= $statut === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
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
            <label for="annee">Année académique</label>
            <select id="annee" name="annee">
                <option value="">Toutes</option>
                <?php foreach ($annees as $a): ?>
                    <option value="<?= e($a) ?>" <?= $annee === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn">Filtrer</button>

    </form>

</div>

<div class="carte">

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th>N°</th><th>Matricule</th><th>Étudiant</th><th>Classe</th><th>Filière</th><th>Année</th><th>Date</th><th>Montant payé</th><th>Statut</th><th>Actions</th></tr>
            </thead>

            <tbody>

                <?php foreach ($inscriptions as $i): ?>

                    <tr>
                        <td><?= (int) $i['idInscription'] ?></td>
                        <td><?= e($i['matricule']) ?></td>
                        <td><strong><?= e($i['nom_Etud'] . ' ' . $i['prenom_Etud']) ?></strong></td>
                        <td><?= e($i['nomClasse']) ?></td>
                        <td><?= e($i['nomFiliere']) ?></td>
                        <td><?= e($i['annee']) ?></td>
                        <td><?= e(dateFr($i['dateInscription'])) ?></td>
                        <td><?= number_format((float) $i['montant'], 0, ',', ' ') ?></td>
                        <td><?= badgeStatut($i['statut']) ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-sec btn-petit" href="<?= e(url('etudiants/voir.php?id=' . (int) $i['idEtudiant'])) ?>">Voir</a>
                                <a class="btn btn-sec btn-petit" href="<?= e(url('inscriptions/formulaire.php?id=' . (int) $i['idInscription'])) ?>">Modifier</a>
                                <form method="post" data-confirm="Supprimer cette inscription ?">
                                    <?= csrfChamp() ?>
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="idInscription" value="<?= (int) $i['idInscription'] ?>">
                                    <button type="submit" class="btn btn-danger btn-petit">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if (!$inscriptions): ?>
            <p class="vide">Aucune inscription trouvée.</p>
        <?php endif; ?>

    </div>

    <?php afficherPagination($p); ?>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
