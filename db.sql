CREATE TABLE IF NOT EXISTS account_wtc (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(100) NOT NULL,
    pdp VARCHAR(255) NOT NULL DEFAULT 'pdp_base.png',
    admin TINYINT(1) NOT NULL DEFAULT 0,
    gerer_seances TINYINT(1) NOT NULL DEFAULT 0,
    ban TINYINT(1) NOT NULL DEFAULT 0,
    maintenance TINYINT(1) NOT NULL DEFAULT 0,
    accept_email TINYINT(1) NOT NULL DEFAULT 1,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    reglement_accepte TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(255) DEFAULT NULL,
    verification_token_expires DATETIME DEFAULT NULL,
    password_reset_token VARCHAR(255) DEFAULT NULL,
    password_reset_expires DATETIME DEFAULT NULL
);

INSERT INTO account_wtc (firstname, lastname, email, `password`, admin, gerer_seances, email_verified) VALUES ("admin", "admin", "admin@admin.fr", "$2y$10$jgqlubHdvwg7cTs1V6C/a.RX92qQhmYV7wLzDMEA7K00g9zluuJmq", 1, 1, 1), ("Freddy", "admin", "freddy@admin.fr", "$2y$10$.DLWGa0n5s/Vxs5E/5Oz6u97tkyZedYFgGMyFAK34Qkxn1q.hUng2", 1, 1, 1);

CREATE TABLE IF NOT EXISTS seance_templates (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    duration_value INT NOT NULL DEFAULT 1,
    duration_unit VARCHAR(10) NOT NULL,
    repeat_value INT NOT NULL DEFAULT 1,
    repeat_unit VARCHAR(10) NOT NULL,
    sessions JSON NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES account_wtc(id) ON DELETE CASCADE,
    INDEX (created_by)
);

CREATE TABLE IF NOT EXISTS seances (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_seance DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    type_seance VARCHAR(100) NOT NULL,
    coach VARCHAR(150) NOT NULL,
    lieu_seance VARCHAR(150) NOT NULL,
    lieu_rdv VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    template_id INT NULL,
    is_modified TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES account_wtc(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES seance_templates(id) ON DELETE SET NULL,
    INDEX (template_id)
);

CREATE TABLE IF NOT EXISTS inscriptions_seances (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    seance_id INT NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(150) NOT NULL,
    account_id INT NULL,
    inscrit_par INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES account_wtc(id) ON DELETE CASCADE,
    FOREIGN KEY (inscrit_par) REFERENCES account_wtc(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS persistent_tokens (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    user_agent VARCHAR(255),
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_used DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES account_wtc(id) ON DELETE CASCADE,
    INDEX (token),
    INDEX (account_id)
);

CREATE TABLE IF NOT EXISTS signalements_wtc (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES account_wtc(id) ON DELETE CASCADE,
    INDEX (account_id),
    INDEX (created_at)
);

CREATE TABLE IF NOT EXISTS index_links (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    link_key VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    title VARCHAR(150) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);