<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$titre = 'Statistiques';
$actif = 'statistiques';

$annees = $connexion->query("SELECT DISTINCT annee FROM classe ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);

$annee = parametreGet('annee', anneeCourante($connexion));

$executer = function (string $sql) use ($connexion, $annee): array {
    $requete = $connexion->prepare($sql);
    $requete->execute([':annee' => $annee]);
    return $requete->fetchAll();
};

$parFiliere = $executer("
    SELECT f.nomFiliere AS libelle, COUNT(i.idInscription) AS total
    FROM filiere f
    INNER JOIN classe c ON c.idFiliere = f.idFiliere AND c.annee = :annee
    LEFT JOIN inscription i ON i.idClasse = c.idClasse AND i.statut <> 'ANNULEE'
    GROUP BY f.idFiliere, f.nomFiliere
    ORDER BY total DESC, f.nomFiliere
");

$parClasse = $executer("
    SELECT c.nomClasse, f.nomFiliere, COUNT(i.idInscription) AS total
    FROM classe c
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
    LEFT JOIN inscription i ON i.idClasse = c.idClasse AND i.statut <> 'ANNULEE'
    WHERE c.annee = :annee
    GROUP BY c.idClasse, c.nomClasse, f.nomFiliere
    ORDER BY f.nomFiliere, c.nomClasse
");

$parSexe = $executer("
    SELECT e.sexe, COUNT(*) AS total
    FROM inscription i
    INNER JOIN etudiant e ON e.idEtudiant = i.idEtudiant
    INNER JOIN classe c ON c.idClasse = i.idClasse
    WHERE i.statut <> 'ANNULEE' AND c.annee = :annee
    GROUP BY e.sexe
");

$parStatut = $executer("
    SELECT i.statut, COUNT(*) AS total
    FROM inscription i
    INNER JOIN classe c ON c.idClasse = i.idClasse
    WHERE c.annee = :annee
    GROUP BY i.statut
");

$montant = $executer("
    SELECT COALESCE(SUM(i.montant), 0) AS total
    FROM inscription i
    INNER JOIN classe c ON c.idClasse = i.idClasse
    WHERE i.statut <> 'ANNULEE' AND c.annee = :annee
");

$totalInscrits = array_sum(array_column($parFiliere, 'total'));
$maxFiliere = max(1, $parFiliere ? max(array_column($parFiliere, 'total')) : 1);

$sexes = ['M' => 0, 'F' => 0];

foreach ($parSexe as $ligne) {
    $sexes[$ligne['sexe']] = (int) $ligne['total'];
}

$statuts = [];

foreach ($parStatut as $ligne) {
    $statuts[$ligne['statut']] = (int) $ligne['total'];
}

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">

    <form method="get" class="filtres">
        <div class="champ">
            <label for="annee">Année académique</label>
            <select id="annee" name="annee" onchange="this.form.submit()">
                <?php foreach ($annees as $a): ?>
                    <option value="<?= e($a) ?>" <?= $annee === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
                <?php if (!$annees): ?><option value="<?= e($annee) ?>"><?= e($annee) ?></option><?php endif; ?>
            </select>
        </div>
    </form>

    <div class="actions">
        <button type="button" class="btn btn-sec" data-imprimer>Imprimer ces statistiques</button>
        <a class="btn" href="<?= e(url('statistiques/liste.php?annee=' . urlencode($annee))) ?>">Liste des étudiants (imprimable)</a>
    </div>

</div>

<div class="grille-stats">
    <div class="stat"><div class="stat-icone">🎓</div><div><span class="libelle">Étudiants inscrits</span><strong><?= $totalInscrits ?></strong></div></div>
    <div class="stat"><div class="stat-icone">🏫</div><div><span class="libelle">Classes</span><strong><?= count($parClasse) ?></strong></div></div>
    <div class="stat"><div class="stat-icone">♂</div><div><span class="libelle">Garçons</span><strong><?= $sexes['M'] ?></strong></div></div>
    <div class="stat"><div class="stat-icone">♀</div><div><span class="libelle">Filles</span><strong><?= $sexes['F'] ?></strong></div></div>
</div>

<div class="deux-colonnes">

    <div class="carte">

        <h2>Nombre d'étudiants par filière</h2>

        <?php foreach ($parFiliere as $ligne): ?>

            <div class="ligne-barre">
                <span class="nom"><?= e($ligne['libelle']) ?></span>
                <span class="piste"><span class="remplissage" style="display:block; width: <?= round($ligne['total'] / $maxFiliere * 100) ?>%;"></span></span>
                <span class="nombre"><?= (int) $ligne['total'] ?></span>
            </div>

        <?php endforeach; ?>

        <?php if (!$parFiliere): ?><p class="vide">Aucune donnée pour cette année.</p><?php endif; ?>

    </div>

    <div class="carte">

        <h2>Répartition selon le sexe</h2>

        <?php $totalSexe = max(1, $sexes['M'] + $sexes['F']); ?>

        <div class="ligne-barre">
            <span class="nom">Masculin</span>
            <span class="piste"><span class="remplissage" style="display:block; width: <?= round($sexes['M'] / $totalSexe * 100) ?>%;"></span></span>
            <span class="nombre"><?= round($sexes['M'] / $totalSexe * 100) ?>%</span>
        </div>

        <div class="ligne-barre">
            <span class="nom">Féminin</span>
            <span class="piste"><span class="remplissage" style="display:block; width: <?= round($sexes['F'] / $totalSexe * 100) ?>%;"></span></span>
            <span class="nombre"><?= round($sexes['F'] / $totalSexe * 100) ?>%</span>
        </div>

        <h2 style="margin-top:24px;">Inscriptions par statut</h2>

        <?php foreach (STATUTS_INSCRIPTION as $code => $libelle): ?>
            <div class="ligne-barre">
                <span class="nom"><?= badgeStatut($code) ?></span>
                <span class="nombre" style="margin-left:auto;"><?= $statuts[$code] ?? 0 ?></span>
            </div>
        <?php endforeach; ?>

        <p style="margin-top:14px; color:var(--gris);">
            Montants payés (hors inscriptions annulées) :
            <strong><?= number_format((float) ($montant[0]['total'] ?? 0), 0, ',', ' ') ?> FCFA</strong>
        </p>

    </div>

</div>

<div class="carte">

    <h2>Nombre d'étudiants par classe</h2>

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead><tr><th>Classe</th><th>Filière</th><th>Effectif</th></tr></thead>

            <tbody>
                <?php foreach ($parClasse as $ligne): ?>
                    <tr>
                        <td><strong><?= e($ligne['nomClasse']) ?></strong></td>
                        <td><?= e($ligne['nomFiliere']) ?></td>
                        <td><?= (int) $ligne['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>

        <?php if (!$parClasse): ?><p class="vide">Aucune classe pour cette année.</p><?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
