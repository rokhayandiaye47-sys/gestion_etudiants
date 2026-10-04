<?php

/*
|--------------------------------------------------------------------------
| Initialisation commune : session, base de données et fonctions utiles
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Chemin web du projet (ex. "/gestion_etudiants"), calculé automatiquement
$cheminProjet = realpath(__DIR__ . '/..');
$cheminWeb = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$baseUrl = '/gestion_etudiants';

if ($cheminProjet !== false && $cheminWeb !== false) {

    $a = str_replace('\\', '/', $cheminProjet);
    $b = rtrim(str_replace('\\', '/', $cheminWeb), '/');

    if (strncasecmp($a, $b, strlen($b)) === 0) {
        $baseUrl = rtrim(substr($a, strlen($b)), '/');
    }
}

define('BASE_URL', $baseUrl);
define('DOSSIER_PHOTOS', __DIR__ . '/../uploads/photos/');

const STATUTS_INSCRIPTION = [
    'CONFIRMEE' => 'Confirmé',
    'EN_ATTENTE' => 'En attente',
    'EN_COURS' => 'En cours',
    'ANNULEE' => 'Annulé',
];

const ROLES = [
    'administrateur' => 'Administrateur',
    'scolarite' => 'Service de la scolarité',
];


/* ----------------------------- Aides générales ----------------------------- */

function e($valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

function url(string $chemin = ''): string
{
    return BASE_URL . '/' . ltrim($chemin, '/');
}

function rediriger(string $chemin): void
{
    header('Location: ' . url($chemin));
    exit;
}

function parametreGet(string $cle, string $defaut = ''): string
{
    return trim((string) ($_GET[$cle] ?? $defaut));
}

function dateFr(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : '';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function lireFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function badgeStatut(string $code): string
{
    $classes = [
        'CONFIRMEE' => 'badge-vert',
        'EN_ATTENTE' => 'badge-orange',
        'EN_COURS' => 'badge-bleu',
        'ANNULEE' => 'badge-rouge',
    ];

    return '<span class="badge ' . ($classes[$code] ?? '') . '">'
        . e(STATUTS_INSCRIPTION[$code] ?? $code) . '</span>';
}

function anneeCourante(PDO $connexion): string
{
    try {
        $annee = $connexion->query("SELECT MAX(annee) FROM classe")->fetchColumn();
    } catch (PDOException $e) {
        $annee = null;
    }

    if ($annee) {
        return (string) $annee;
    }

    $debut = (int) date('n') >= 9 ? (int) date('Y') : (int) date('Y') - 1;

    return $debut . '-' . ($debut + 1);
}


/* ------------------------------ Authentification --------------------------- */

function utilisateurConnecte(): ?array
{
    return $_SESSION['utilisateur'] ?? null;
}

function exigerConnexion(): void
{
    if (!utilisateurConnecte()) {
        rediriger('login.php');
    }
}

function estAdministrateur(): bool
{
    $u = utilisateurConnecte();
    return $u !== null && $u['role'] === 'administrateur';
}

function exigerRole(array $roles): void
{
    global $connexion;

    exigerConnexion();

    if (!in_array($_SESSION['utilisateur']['role'], $roles, true)) {

        http_response_code(403);

        $titre = 'Accès refusé';
        $actif = '';

        require __DIR__ . '/haut.php';

        echo '<div class="carte"><p>Tu n\'as pas le droit d\'accéder à cette page.</p>'
            . '<p style="margin-top:12px;"><a class="btn" href="' . e(url('dashboard.php')) . '">Retour au tableau de bord</a></p></div>';

        require __DIR__ . '/bas.php';
        exit;
    }
}


/* ---------------------------------- CSRF ----------------------------------- */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfChamp(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerifier(): void
{
    $attendu = $_SESSION['csrf_token'] ?? '';
    $envoye = $_POST['csrf_token'] ?? '';

    if ($attendu === '' || !is_string($envoye) || !hash_equals($attendu, $envoye)) {
        http_response_code(403);
        die('Requête refusée : jeton de sécurité invalide ou expiré. Recharge la page et réessaie.');
    }
}


/* -------------------------------- Pagination ------------------------------- */

function pagination(int $total, int $parPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $parPage));
    $page = min(max(1, $page), $pages);

    return [
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'limite' => $parPage,
        'offset' => ($page - 1) * $parPage,
    ];
}

function afficherPagination(array $p): void
{
    if ($p['pages'] <= 1) {
        return;
    }

    echo '<div class="pagination">';

    for ($i = 1; $i <= $p['pages']; $i++) {

        $parametres = $_GET;
        $parametres['page'] = $i;

        echo '<a class="' . ($i === $p['page'] ? 'actif' : '') . '" href="?'
            . e(http_build_query($parametres)) . '">' . $i . '</a>';
    }

    echo '</div>';
}


/* ---------------------------- Étudiants et photos -------------------------- */

function genererMatricule(PDO $connexion): string
{
    $prefixe = 'ESGN' . date('y');

    $requete = $connexion->prepare("SELECT COUNT(*) FROM etudiant WHERE matricule LIKE :prefixe");
    $requete->execute([':prefixe' => $prefixe . '%']);

    $numero = (int) $requete->fetchColumn() + 1;

    do {

        $matricule = $prefixe . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);

        $verif = $connexion->prepare("SELECT 1 FROM etudiant WHERE matricule = :matricule");
        $verif->execute([':matricule' => $matricule]);

        $existe = (bool) $verif->fetchColumn();
        $numero++;

    } while ($existe);

    return $matricule;
}

function enregistrerPhoto(array $fichier, array &$erreurs): ?string
{
    $code = $fichier['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($code !== UPLOAD_ERR_OK) {
        $erreurs[] = "La photo n'a pas pu être envoyée.";
        return null;
    }

    if ($fichier['size'] > 2 * 1024 * 1024) {
        $erreurs[] = 'La photo dépasse 2 Mo.';
        return null;
    }

    $info = @getimagesize($fichier['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    if (!$info || !isset($types[$info['mime']])) {
        $erreurs[] = 'La photo doit être au format PNG ou JPG.';
        return null;
    }

    if (!is_dir(DOSSIER_PHOTOS)) {
        mkdir(DOSSIER_PHOTOS, 0755, true);
    }

    $nom = bin2hex(random_bytes(10)) . '.' . $types[$info['mime']];

    if (!move_uploaded_file($fichier['tmp_name'], DOSSIER_PHOTOS . $nom)) {
        $erreurs[] = "La photo n'a pas pu être enregistrée.";
        return null;
    }

    return $nom;
}

function supprimerPhoto(?string $nom): void
{
    if ($nom && preg_match('/^[a-f0-9]{20}\.(jpg|png)$/', $nom)) {

        $fichier = DOSSIER_PHOTOS . $nom;

        if (is_file($fichier)) {
            @unlink($fichier);
        }
    }
}

function avatarEtudiant(array $e): string
{
    if (!empty($e['photo'])) {
        return '<img class="avatar" src="' . e(url('uploads/photos/' . $e['photo'])) . '" alt="">';
    }

    $initiales = mb_strtoupper(mb_substr($e['prenom_Etud'] ?? '', 0, 1) . mb_substr($e['nom_Etud'] ?? '', 0, 1));

    return '<span class="avatar avatar-initiales">' . e($initiales) . '</span>';
}
