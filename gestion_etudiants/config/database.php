<?php

/*
|--------------------------------------------------------------------------
| Connexion à la base de données (PDO)
|--------------------------------------------------------------------------
| Valeurs par défaut pour WAMP en local. Pour l'hébergement, modifie-les
| ici ou définis les variables d'environnement GESTION_DB_HOST,
| GESTION_DB_NAME, GESTION_DB_USER et GESTION_DB_PASSWORD.
*/

$hoteBdd = getenv('GESTION_DB_HOST') ?: 'localhost';
$nomBdd = getenv('GESTION_DB_NAME') ?: 'gestion_etudiants';
$utilisateurBdd = getenv('GESTION_DB_USER') ?: 'root';
$motDePasseBdd = getenv('GESTION_DB_PASSWORD') !== false ? getenv('GESTION_DB_PASSWORD') : '';

try {

    $connexion = new PDO(
        "mysql:host=$hoteBdd;dbname=$nomBdd;charset=utf8mb4",
        $utilisateurBdd,
        $motDePasseBdd,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $e) {

    error_log('Connexion base de données : ' . $e->getMessage());

    // Le détail technique n'est affiché que si GESTION_DEBUG est défini
    die(
        getenv('GESTION_DEBUG')
            ? 'Erreur de connexion à la base de données : ' . $e->getMessage()
            : 'Erreur de connexion à la base de données.'
    );
}
