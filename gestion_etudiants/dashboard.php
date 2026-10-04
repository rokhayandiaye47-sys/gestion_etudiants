<?php

require_once __DIR__ . '/includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$titre = 'Tableau de bord';
$actif = 'dashboard';

$compter = function (string $sql) use ($connexion): int {
    return (int) $connexion->query($sql)->fetchColumn();
};

$nbEtudiants = $compter("SELECT COUNT(*) FROM etudiant");
$nbEtudiantsMois = $compter("SELECT COUNT(*) FROM etudiant WHERE date_creation >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$nbClasses = $compter("SELECT COUNT(*) FROM classe");
$nbFilieres = $compter("SELECT COUNT(*) FROM filiere");
$nbInscriptions = $compter("SELECT COUNT(*) FROM inscription WHERE statut <> 'ANNULEE'");
$nbInscriptionsMois = $compter("SELECT COUNT(*) FROM inscription WHERE statut <> 'ANNULEE' AND dateInscription >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

/* Inscriptions des 12 derniers mois */

$moisCourts = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

$parMois = [];

$requete = $connexion->query("
    SELECT DATE_FORMAT(dateInscription, '%Y-%m') AS mois, COUNT(*) AS total
    FROM inscription
    WHERE statut <> 'ANNULEE'
      AND dateInscription >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
    GROUP BY mois
");

foreach ($requete->fetchAll() as $ligne) {
    $parMois[$ligne['mois']] = (int) $ligne['total'];
}

$serie = [];

for ($i = 11; $i >= 0; $i--) {

    $cle = date('Y-m', strtotime("first day of -$i month"));

    $serie[] = [
        'libelle' => $moisCourts[(int) substr($cle, 5, 2) - 1],
        'total' => $parMois[$cle] ?? 0,
    ];
}

$maxSerie = max(1, max(array_column($serie, 'total')));

/* Répartition par filière */

$repartition = $connexion->query("
    SELECT f.nomFiliere, COUNT(i.idInscription) AS total
    FROM filiere f
    INNER JOIN classe c ON c.idFiliere = f.idFiliere
    INNER JOIN inscription i ON i.idClasse = c.idClasse AND i.statut <> 'ANNULEE'
    GROUP BY f.idFiliere, f.nomFiliere
    ORDER BY total DESC
")->fetchAll();

$totalRepartition = array_sum(array_column($repartition, 'total'));
$couleurs = ['#0647b8', '#1760d1', '#4b83df', '#78a2e8', '#a8c3f2', '#d0def8'];

/* Inscriptions récentes */

$recentes = $connexion->query("
    SELECT i.idInscription, i.dateInscription, i.statut, e.idEtudiant, e.nom_Etud, e.prenom_Etud, c.nomClasse, f.nomFiliere
    FROM inscription i
    INNER JOIN etudiant e ON e.idEtudiant = i.idEtudiant
    INNER JOIN classe c ON c.idClasse = i.idClasse
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
    ORDER BY i.dateInscription DESC, i.idInscription DESC
    LIMIT 5
")->fetchAll();

require __DIR__ . '/includes/haut.php';

?>

<div class="grille-stats">

    <div class="stat">
        <div class="stat-icone">👥</div>
        <div><span class="libelle">Étudiants</span><strong><?= $nbEtudiants ?></strong><small>+<?= $nbEtudiantsMois ?> ce mois</small></div>
    </div>

    <div class="stat">
        <div class="stat-icone">🏫</div>
        <div><span class="libelle">Classes</span><strong><?= $nbClasses ?></strong></div>
    </div>

    <div class="stat">
        <div class="stat-icone">🎓</div>
        <div><span class="libelle">Filières</span><strong><?= $nbFilieres ?></strong></div>
    </div>

    <div class="stat">
        <div class="stat-icone">📋</div>
        <div><span class="libelle">Inscriptions</span><strong><?= $nbInscriptions ?></strong><small>+<?= $nbInscriptionsMois ?> ce mois</small></div>
    </div>

</div>

<div class="deux-colonnes">

    <div class="carte">

        <h2>Inscriptions par mois (12 derniers mois)</h2>

        <div class="barres">

            <?php foreach ($serie as $mois): ?>

                <div class="barre-col">
                    <span class="valeur"><?= $mois['total'] ?></span>
                    <div class="barre" style="height: <?= round($mois['total'] / $maxSerie * 150) ?>px;"></div>
                    <?= e($mois['libelle']) ?>
                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <div class="carte">

        <h2>Répartition par filière</h2>

        <?php if ($totalRepartition === 0): ?>

            <p class="vide">Aucune inscription pour le moment.</p>

        <?php else: ?>

            <div class="donut-bloc">

                <svg class="donut" viewBox="0 0 42 42">

                    <circle cx="21" cy="21" r="15.91549431" fill="none" stroke="#eaf1ff" stroke-width="6"></circle>

                    <?php

                    $decalage = 0;

                    foreach ($repartition as $i => $ligne):

                        $pct = $ligne['total'] / $totalRepartition * 100;

                    ?>

                        <circle cx="21" cy="21" r="15.91549431" fill="none"
                                stroke="<?= $couleurs[$i % count($couleurs)] ?>" stroke-width="6"
                                stroke-dasharray="<?= round($pct, 2) ?> <?= round(100 - $pct, 2) ?>"
                                stroke-dashoffset="<?= round(25 - $decalage, 2) ?>"></circle>

                        <?php $decalage += $pct; ?>

                    <?php endforeach; ?>

                    <text x="21" y="21.5" text-anchor="middle" font-size="6"><?= $totalRepartition ?></text>
                    <text x="21" y="27" text-anchor="middle" font-size="2.6" style="font-weight:normal;">Inscriptions</text>

                </svg>

                <div class="legende">

                    <?php foreach ($repartition as $i => $ligne): ?>

                        <div>
                            <span class="pastille" style="background: <?= $couleurs[$i % count($couleurs)] ?>;"></span>
                            <?= e($ligne['nomFiliere']) ?>
                            <span class="pct"><?= round($ligne['total'] / $totalRepartition * 100) ?>%</span>
                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>

<div class="carte">

    <div class="entete-page" style="margin-bottom:10px;">
        <h2 style="margin:0;">Inscriptions récentes</h2>
        <a class="btn btn-sec" href="<?= e(url('inscriptions/index.php')) ?>">Voir toutes les inscriptions →</a>
    </div>

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th>N°</th><th>Étudiant</th><th>Classe</th><th>Filière</th><th>Date</th><th>Statut</th><th></th></tr>
            </thead>

            <tbody>

                <?php foreach ($recentes as $r): ?>

                    <tr>
                        <td><?= (int) $r['idInscription'] ?></td>
                        <td><?= e($r['nom_Etud'] . ' ' . $r['prenom_Etud']) ?></td>
                        <td><?= e($r['nomClasse']) ?></td>
                        <td><?= e($r['nomFiliere']) ?></td>
                        <td><?= e(dateFr($r['dateInscription'])) ?></td>
                        <td><?= badgeStatut($r['statut']) ?></td>
                        <td><a href="<?= e(url('etudiants/voir.php?id=' . (int) $r['idEtudiant'])) ?>">Voir</a></td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if (!$recentes): ?>

            <p class="vide">Aucune inscription enregistrée.</p>

        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/includes/bas.php'; ?>
