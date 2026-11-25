-- Table de la liste d'utilisateurs (MySQL / MariaDB)
-- En local : créer la base « yolo », puis importer ce fichier (phpMyAdmin ou HeidiSQL).

CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pseudo VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
