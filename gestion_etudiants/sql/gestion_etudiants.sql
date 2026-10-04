-- ============================================================
-- Application de gestion des étudiants - ESGN
-- Base de données MySQL (conforme au modèle logique du mémoire)
--
-- À exécuter UNE SEULE FOIS : phpMyAdmin > onglet "SQL" > coller > Exécuter
-- ============================================================

CREATE DATABASE IF NOT EXISTS gestion_etudiants
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_etudiants;

-- FILIERE (idFiliere, nomFiliere, description)
CREATE TABLE IF NOT EXISTS filiere (
    idFiliere   INT AUTO_INCREMENT PRIMARY KEY,
    nomFiliere  VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- CLASSE (idClasse, nomClasse, niveau, annee, #idFiliere)
CREATE TABLE IF NOT EXISTS classe (
    idClasse  INT AUTO_INCREMENT PRIMARY KEY,
    nomClasse VARCHAR(50) NOT NULL,
    niveau    VARCHAR(20) NOT NULL,
    annee     VARCHAR(20) NOT NULL,
    idFiliere INT NOT NULL,
    UNIQUE KEY uq_classe_annee (nomClasse, annee),
    CONSTRAINT fk_classe_filiere FOREIGN KEY (idFiliere)
        REFERENCES filiere (idFiliere) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ETUDIANT (idEtudiant, matricule, nom_Etud, prenom_Etud, sexe, dateNaissance, ... )
-- Règle R1 : le matricule est unique
CREATE TABLE IF NOT EXISTS etudiant (
    idEtudiant    INT AUTO_INCREMENT PRIMARY KEY,
    matricule     VARCHAR(20) NOT NULL UNIQUE,
    nom_Etud      VARCHAR(50) NOT NULL,
    prenom_Etud   VARCHAR(50) NOT NULL,
    sexe          CHAR(1) NOT NULL,
    dateNaissance DATE NOT NULL,
    lieuNaissance VARCHAR(100) NULL,
    adresse       VARCHAR(100) NULL,
    telephone     VARCHAR(20) NOT NULL,
    email_Etud    VARCHAR(100) NULL,
    photo         VARCHAR(60) NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etudiant_nom (nom_Etud, prenom_Etud)
) ENGINE=InnoDB;

-- UTILISATEUR (idUtilisateur, nom, prenom, email, motdepass, role)
-- role : 'administrateur' ou 'scolarite'
CREATE TABLE IF NOT EXISTS utilisateur (
    idUtilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(50) NOT NULL,
    prenom        VARCHAR(50) NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    motdepass     VARCHAR(255) NOT NULL,
    role          VARCHAR(30) NOT NULL DEFAULT 'scolarite',
    actif         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- INSCRIPTION (idInscription, dateInscription, statut, montant, #idEtudiant, #idUtilisateur, #idClasse)
-- statut : CONFIRMEE, EN_ATTENTE, EN_COURS, ANNULEE
CREATE TABLE IF NOT EXISTS inscription (
    idInscription   INT AUTO_INCREMENT PRIMARY KEY,
    dateInscription DATE NOT NULL,
    statut          VARCHAR(30) NOT NULL DEFAULT 'CONFIRMEE',
    montant         DECIMAL(10,2) NOT NULL DEFAULT 0,
    idEtudiant      INT NOT NULL,
    idUtilisateur   INT NULL,
    idClasse        INT NOT NULL,
    INDEX idx_inscription_etudiant (idEtudiant),
    INDEX idx_inscription_classe (idClasse),
    INDEX idx_inscription_date (dateInscription),
    CONSTRAINT fk_inscription_etudiant FOREIGN KEY (idEtudiant)
        REFERENCES etudiant (idEtudiant) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inscription_utilisateur FOREIGN KEY (idUtilisateur)
        REFERENCES utilisateur (idUtilisateur) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inscription_classe FOREIGN KEY (idClasse)
        REFERENCES classe (idClasse) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Premier compte administrateur
--   Email        : admin@esgn.sn
--   Mot de passe : Esgn@Admin2026
-- IMPORTANT : change ce mot de passe dès la première connexion
-- (menu Paramètres > Changer mon mot de passe).
-- ============================================================
INSERT INTO utilisateur (nom, prenom, email, motdepass, role, actif)
SELECT 'Administrateur', 'ESGN', 'admin@esgn.sn', '$2y$12$.UJyYMRXpMmRT8pfcTvv9.ELpWZqzHbUWh9baMcTmzYZgDQhkafcW', 'administrateur', 1
WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE email = 'admin@esgn.sn');

-- ============================================================
-- Données d'exemple (OPTIONNEL : tu peux supprimer ce bloc
-- et créer tes vraies filières et classes depuis l'application)
-- ============================================================
INSERT IGNORE INTO filiere (nomFiliere, description) VALUES
    ('Informatique de gestion', 'Conception et gestion des systèmes d''information'),
    ('Réseaux & Télécoms', 'Administration des réseaux et des télécommunications'),
    ('Développement logiciel', 'Développement d''applications web et mobiles'),
    ('Multimédia', 'Création numérique et communication visuelle');

INSERT IGNORE INTO classe (nomClasse, niveau, annee, idFiliere) VALUES
    ('L1 IG', 'Licence 1', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Informatique de gestion')),
    ('L2 IG', 'Licence 2', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Informatique de gestion')),
    ('L3 IG', 'Licence 3', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Informatique de gestion')),
    ('L1 RT', 'Licence 1', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Réseaux & Télécoms')),
    ('L2 RT', 'Licence 2', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Réseaux & Télécoms')),
    ('L1 DL', 'Licence 1', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Développement logiciel')),
    ('L2 DL', 'Licence 2', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Développement logiciel')),
    ('L1 MM', 'Licence 1', '2025-2026', (SELECT idFiliere FROM filiere WHERE nomFiliere = 'Multimédia'));
