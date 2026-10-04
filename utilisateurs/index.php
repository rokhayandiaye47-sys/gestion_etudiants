<?php

require_once __DIR__ . '/../includes/init.php';

exigerRole(['administrateur']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'statut') {

    csrfVerifier();

    $id = (int) ($_POST['idUtilisateur'] ?? 0);

    if ($id === (int) $_SESSION['utilisateur']['idUtilisateur']) {

        flash('erreur', 'Tu ne peux pas désactiver ton propre compte.');

    } else {

        $connexion->prepare("UPDATE utilisateur SET actif = 1 - actif WHERE idUtilisateur = :id")
            ->execute([':id' => $id]);

        flash('succes', 'Statut du compte mis à jour.');
    }

    rediriger('utilisateurs/index.php');
}

$titre = 'Gestion des utilisateurs';
$actif = 'utilisateurs';

$utilisateurs = $connexion->query("
    SELECT idUtilisateur, nom, prenom, email, role, actif
    FROM utilisateur
    ORDER BY nom, prenom
")->fetchAll();

require __DIR__ . '/../includes/haut.php';

?>

<div class="entete-page">
    <p>Comptes qui peuvent se connecter à l'application.</p>
    <a class="btn" href="<?= e(url('utilisateurs/formulaire.php')) ?>">+ Nouvel utilisateur</a>
</div>

<div class="carte">

    <div class="tableau-conteneur">

        <table class="tableau">

            <thead>
                <tr><th>Nom et prénom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr>
            </thead>

            <tbody>

                <?php foreach ($utilisateurs as $u): ?>

                    <tr>
                        <td><strong><?= e($u['nom'] . ' ' . $u['prenom']) ?></strong></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e(ROLES[$u['role']] ?? $u['role']) ?></td>
                        <td><?= (int) $u['actif'] === 1 ? '<span class="badge badge-vert">Actif</span>' : '<span class="badge badge-rouge">Désactivé</span>' ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-sec btn-petit" href="<?= e(url('utilisateurs/formulaire.php?id=' . (int) $u['idUtilisateur'])) ?>">Modifier</a>

                                <?php if ((int) $u['idUtilisateur'] !== (int) $_SESSION['utilisateur']['idUtilisateur']): ?>

                                    <form method="post" data-confirm="Confirmer le changement de statut de ce compte ?">
                                        <?= csrfChamp() ?>
                                        <input type="hidden" name="action" value="statut">
                                        <input type="hidden" name="idUtilisateur" value="<?= (int) $u['idUtilisateur'] ?>">
                                        <button type="submit" class="btn btn-sec btn-petit"><?= (int) $u['actif'] === 1 ? 'Désactiver' : 'Réactiver' ?></button>
                                    </form>

                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/../includes/bas.php'; ?>
