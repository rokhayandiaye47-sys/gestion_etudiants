# Gestion des étudiants - ESGN

Application web de gestion des étudiants, des inscriptions, des classes et des filières. Elle est développée en PHP avec PDO et utilise MySQL ou MariaDB.

## Fonctionnalités

- Tableau de bord et statistiques sur les inscriptions.
- Gestion des étudiants et de leurs photos.
- Gestion des inscriptions, avec recherche et filtres.
- Consultation des classes et filières.
- Gestion des comptes par l'administrateur.
- Rôles administrateur et service de la scolarité.

## Prérequis

- PHP 7.4 ou plus récent.
- MySQL ou MariaDB.
- Apache (WAMP convient pour une installation locale).
- Extensions PHP PDO MySQL et mbstring activées.

## Installation locale avec WAMP

1. Copier le projet dans `C:\wamp64\www\gestion_etudiants`.
2. Démarrer WAMP et ouvrir [phpMyAdmin](http://localhost/phpmyadmin).
3. Importer le script `gestion_etudiants/sql/gestion_etudiants.sql` depuis l'onglet **SQL**. Il crée la base `gestion_etudiants`, les tables et des données d'exemple.
4. Vérifier la configuration de la base dans `config/database.php`. Par défaut, elle utilise `localhost`, la base `gestion_etudiants`, l'utilisateur `root` et un mot de passe vide. Les variables d'environnement `GESTION_DB_HOST`, `GESTION_DB_NAME`, `GESTION_DB_USER` et `GESTION_DB_PASSWORD` peuvent aussi être utilisées.
5. Ouvrir [http://localhost/gestion_etudiants/](http://localhost/gestion_etudiants/).

## Première connexion

- **Email :** `admin@esgn.sn`
- **Mot de passe :** `Esgn@Admin2026`

Changez ce mot de passe dès la première connexion, dans **Paramètres**. Ces identifiants de démonstration sont publics : ne les conservez pas sur une installation accessible en ligne.

## Mise en ligne

- Configurez une base et un utilisateur MySQL dédiés (n'utilisez pas `root`).
- Définissez les variables de connexion ou adaptez `config/database.php`.
- Activez HTTPS et vérifiez que `uploads/photos` est accessible en écriture.
- Changez le mot de passe administrateur fourni avec les données initiales.

## Sécurité

L'application utilise des mots de passe hachés, des requêtes PDO préparées, une protection CSRF et une limitation des tentatives de connexion. Les photos envoyées sont limitées aux formats PNG et JPG et à 2 Mo.
