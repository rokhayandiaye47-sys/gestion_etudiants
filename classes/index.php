<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {

    exigerRole(['administrateur']);
    csrfVerifier();

    try {

        $connexion->prepare("DELETE FROM classe WHERE idClasse = :id")
            ->execute([':id' => (int) ($_POST['idClasse'] ?? 0)]);

        flash('succes', 'Classe supprimée.');

    } catch (PDOException $e) {

        flash('erreur', 'Impossible de supprimer cette classe : des inscriptions y sont rattachées.');
    }

    rediriger('classes/index.php');
}

$titre = 'Gestion des classes';
$actif = 'classes';
$admin = estAdministrateur();

$idFiliere = (int) parametreGet('idFiliere');
$annee = parametreGet('annee');

$filieres = $connexion->query("SELECT idFiliere, nomFiliere FROM filiere ORDER BY nomFiliere")->fetchAll();
$annees = $connexion->query("SELECT DISTINCT annee FROM classe ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);

$conditions = [];
$parametres = [];

if ($idFiliere > 0) {
    $conditions[] = 'c.idFiliere = :idFiliere';
    $parametres[':idFiliere'] = $idFiliere;
}

if ($annee !== '') {
    $conditions[] = 'c.annee = :annee';
    $parametres[':annee'] = $annee;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$requete = $connexion->prepare("
    SELECT
        c.idClasse, c.nomClasse, c.niveau, c.annee, f.nomFiliere,
        (SELECT COUNT(*) FROM inscription i WHERE i.idClasse = c.idClasse AND i.statut <> 'ANNULEE') AS nb_etudiants
    FROM classe c
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
    $where
    ORDER BY c.annee DESC, f.nomFiliere, c.nomClasse
");

$requete->execute($parametres);
$classes = $requete->fetchAll();

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">
    <p>Les classes de chaque filière, par année académique.</p>
    <?php if ($admin): ?>
        <a class="btn" href="<?= e(url('classes/formulaire.php')) ?>">+ Nouvelle classe</a>
    <?php endif; ?>
</div>

<div class="carte">

    <form method="get" class="filtres">

        <div class="champ">
            <label for="idFiliere">Filière</label>
            <select id="idFiliere" name="idFiliere">
                <option value="">Toutes</option>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?= (int) $f['idFiliere'] ?>" <?= $idFiliere === (int) $f['idFiliere'] ? 'selected' : '' ?>><?= e($f['nomFiliere']) ?></option>
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
                <tr><th>Classe</th><th>Niveau</th><th>Filière</th><th>Année</th><th>Étudiants</th><?php if ($admin): ?><th>Actions</th><?php endif; ?></tr>
            </thead>

            <tbody>

                <?php foreach ($classes as $c): ?>

                    <tr>
                        <td><strong><?= e($c['nomClasse']) ?></strong></td>
                        <td><?= e($c['niveau']) ?></td>
                        <td><?= e($c['nomFiliere']) ?></td>
                        <td><?= e($c['annee']) ?></td>
                        <td><?= (int) $c['nb_etudiants'] ?></td>

                        <?php if ($admin): ?>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-sec btn-petit" href="<?= e(url('classes/formulaire.php?id=' . (int) $c['idClasse'])) ?>">Modifier</a>
                                    <form method="post" data-confirm="Supprimer cette classe ?">
                                        <?= csrfChamp() ?>
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="idClasse" value="<?= (int) $c['idClasse'] ?>">
                                        <button type="submit" class="btn btn-danger btn-petit">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if (!$classes): ?>
            <p class="vide">Aucune classe trouvée.</p>
        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
