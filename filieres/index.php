<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {

    exigerRole(['administrateur']);
    csrfVerifier();

    try {

        $connexion->prepare("DELETE FROM filiere WHERE idFiliere = :id")
            ->execute([':id' => (int) ($_POST['idFiliere'] ?? 0)]);

        flash('succes', 'Filière supprimée.');

    } catch (PDOException $e) {

        flash('erreur', 'Impossible de supprimer cette filière : des classes y sont rattachées.');
    }

    rediriger('filieres/index.php');
}

$titre = 'Gestion des filières';
$actif = 'filieres';
$admin = estAdministrateur();

$filieres = $connexion->query("
    SELECT
        f.idFiliere,
        f.nomFiliere,
        f.description,
        (SELECT COUNT(*) FROM classe c WHERE c.idFiliere = f.idFiliere) AS nb_classes,
        (SELECT COUNT(DISTINCT i.idEtudiant)
            FROM inscription i
            INNER JOIN classe c ON c.idClasse = i.idClasse
            WHERE c.idFiliere = f.idFiliere AND i.statut <> 'ANNULEE') AS nb_etudiants
    FROM filiere f
    ORDER BY f.nomFiliere
")->fetchAll();

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">
    <p>Les filières de formation de l'école.</p>
    <?php if ($admin): ?>
        <a class="btn" href="<?= e(url('filieres/formulaire.php')) ?>">+ Nouvelle filière</a>
    <?php endif; ?>
</div>

<div class="carte">

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th>Filière</th><th>Description</th><th>Classes</th><th>Étudiants</th><?php if ($admin): ?><th>Actions</th><?php endif; ?></tr>
            </thead>

            <tbody>

                <?php foreach ($filieres as $f): ?>

                    <tr>
                        <td><strong><?= e($f['nomFiliere']) ?></strong></td>
                        <td><?= e($f['description']) ?></td>
                        <td><?= (int) $f['nb_classes'] ?></td>
                        <td><?= (int) $f['nb_etudiants'] ?></td>

                        <?php if ($admin): ?>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-sec btn-petit" href="<?= e(url('filieres/formulaire.php?id=' . (int) $f['idFiliere'])) ?>">Modifier</a>
                                    <form method="post" data-confirm="Supprimer cette filière ?">
                                        <?= csrfChamp() ?>
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="idFiliere" value="<?= (int) $f['idFiliere'] ?>">
                                        <button type="submit" class="btn btn-danger btn-petit">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if (!$filieres): ?>
            <p class="vide">Aucune filière enregistrée.</p>
        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
