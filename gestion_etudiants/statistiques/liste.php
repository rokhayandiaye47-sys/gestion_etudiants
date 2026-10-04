<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$annees = $connexion->query("SELECT DISTINCT annee FROM classe ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);
$filieres = $connexion->query("SELECT idFiliere, nomFiliere FROM filiere ORDER BY nomFiliere")->fetchAll();
$classes = $connexion->query("SELECT idClasse, nomClasse, annee, idFiliere FROM classe ORDER BY annee DESC, nomClasse")->fetchAll();

$annee = parametreGet('annee', anneeCourante($connexion));
$idFiliere = (int) parametreGet('idFiliere');
$idClasse = (int) parametreGet('idClasse');

$conditions = ["i.statut <> 'ANNULEE'", 'c.annee = :annee'];
$parametres = [':annee' => $annee];

if ($idFiliere > 0) {
    $conditions[] = 'f.idFiliere = :idFiliere';
    $parametres[':idFiliere'] = $idFiliere;
}

if ($idClasse > 0) {
    $conditions[] = 'c.idClasse = :idClasse';
    $parametres[':idClasse'] = $idClasse;
}

$requete = $connexion->prepare("
    SELECT e.matricule, e.nom_Etud, e.prenom_Etud, e.sexe, e.dateNaissance, e.telephone,
           c.nomClasse, f.nomFiliere
    FROM inscription i
    INNER JOIN etudiant e ON e.idEtudiant = i.idEtudiant
    INNER JOIN classe c ON c.idClasse = i.idClasse
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
    WHERE " . implode(' AND ', $conditions) . "
    ORDER BY f.nomFiliere, c.nomClasse, e.nom_Etud, e.prenom_Etud
");

$requete->execute($parametres);
$etudiants = $requete->fetchAll();

$titre = 'Liste des étudiants';
$actif = 'statistiques';

require __DIR__ . '/../includes/haut.php';

?>

<div class="carte no-print">

    <form method="get" class="filtres">

        <div class="champ">
            <label for="annee">Année académique</label>
            <select id="annee" name="annee">
                <?php foreach ($annees as $a): ?>
                    <option value="<?= e($a) ?>" <?= $annee === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
                <?php if (!$annees): ?><option value="<?= e($annee) ?>"><?= e($annee) ?></option><?php endif; ?>
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

        <button type="submit" class="btn">Afficher</button>
        <button type="button" class="btn btn-sec" data-imprimer>Imprimer la liste</button>

    </form>

</div>

<div class="carte">

    <h2>École Supérieure de Gestion Numérique — Liste des étudiants</h2>

    <p style="margin-bottom:14px; color:var(--gris);">
        Année académique <?= e($annee) ?> — <?= count($etudiants) ?> étudiant(s) — édité le <?= date('d/m/Y') ?>
    </p>

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th>N°</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Sexe</th><th>Naissance</th><th>Téléphone</th><th>Classe</th><th>Filière</th></tr>
            </thead>

            <tbody>
                <?php foreach ($etudiants as $n => $et): ?>
                    <tr>
                        <td><?= $n + 1 ?></td>
                        <td><?= e($et['matricule']) ?></td>
                        <td><?= e($et['nom_Etud']) ?></td>
                        <td><?= e($et['prenom_Etud']) ?></td>
                        <td><?= e($et['sexe']) ?></td>
                        <td><?= e(dateFr($et['dateNaissance'])) ?></td>
                        <td><?= e($et['telephone']) ?></td>
                        <td><?= e($et['nomClasse']) ?></td>
                        <td><?= e($et['nomFiliere']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>

        <?php if (!$etudiants): ?><p class="vide">Aucun étudiant pour ces critères.</p><?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
