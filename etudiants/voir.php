<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur', 'scolarite']);

$id = (int) ($_GET['id'] ?? 0);

$requete = $connexion->prepare("SELECT * FROM etudiant WHERE idEtudiant = :id");
$requete->execute([':id' => $id]);
$etudiant = $requete->fetch();

if (!$etudiant) {
    flash('erreur', 'Étudiant introuvable.');
    rediriger('etudiants/index.php');
}

$requete = $connexion->prepare("
    SELECT i.idInscription, i.dateInscription, i.statut, i.montant, c.nomClasse, c.annee, f.nomFiliere
    FROM inscription i
    INNER JOIN classe c ON c.idClasse = i.idClasse
    INNER JOIN filiere f ON f.idFiliere = c.idFiliere
    WHERE i.idEtudiant = :id
    ORDER BY i.dateInscription DESC, i.idInscription DESC
");

$requete->execute([':id' => $id]);
$inscriptions = $requete->fetchAll();

$titre = 'Fiche étudiant';
$actif = 'etudiants';

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">
    <a class="btn btn-sec" href="<?= e(url('etudiants/index.php')) ?>">← Retour à la liste</a>
    <div class="actions">
        <a class="btn" href="<?= e(url('inscriptions/formulaire.php?idEtudiant=' . $id)) ?>">+ Nouvelle inscription</a>
        <a class="btn btn-sec" href="<?= e(url('etudiants/formulaire.php?id=' . $id)) ?>">Modifier</a>
    </div>
</div>

<div class="carte">

    <div class="fiche">

        <?php if (!empty($etudiant['photo'])): ?>
            <img class="photo" src="<?= e(url('uploads/photos/' . $etudiant['photo'])) ?>" alt="">
        <?php else: ?>
            <div class="photo">👤</div>
        <?php endif; ?>

        <dl>
            <dt>Matricule</dt><dd><?= e($etudiant['matricule']) ?></dd>
            <dt>Nom et prénom</dt><dd><strong><?= e($etudiant['nom_Etud'] . ' ' . $etudiant['prenom_Etud']) ?></strong></dd>
            <dt>Sexe</dt><dd><?= $etudiant['sexe'] === 'F' ? 'Féminin' : 'Masculin' ?></dd>
            <dt>Date de naissance</dt><dd><?= e(dateFr($etudiant['dateNaissance'])) ?></dd>
            <dt>Lieu de naissance</dt><dd><?= e($etudiant['lieuNaissance'] ?: '—') ?></dd>
            <dt>Téléphone</dt><dd><?= e($etudiant['telephone']) ?></dd>
            <dt>Email</dt><dd><?= e($etudiant['email_Etud'] ?: '—') ?></dd>
            <dt>Adresse</dt><dd><?= e($etudiant['adresse'] ?: '—') ?></dd>
        </dl>

    </div>

</div>

<div class="carte">

    <h2>Inscriptions</h2>

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th>N°</th><th>Année</th><th>Classe</th><th>Filière</th><th>Date</th><th>Montant payé</th><th>Statut</th></tr>
            </thead>

            <tbody>

                <?php foreach ($inscriptions as $i): ?>

                    <tr>
                        <td><?= (int) $i['idInscription'] ?></td>
                        <td><?= e($i['annee']) ?></td>
                        <td><?= e($i['nomClasse']) ?></td>
                        <td><?= e($i['nomFiliere']) ?></td>
                        <td><?= e(dateFr($i['dateInscription'])) ?></td>
                        <td><?= number_format((float) $i['montant'], 0, ',', ' ') ?> FCFA</td>
                        <td><?= badgeStatut($i['statut']) ?></td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if (!$inscriptions): ?>
            <p class="vide">Aucune inscription pour cet étudiant.</p>
        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
