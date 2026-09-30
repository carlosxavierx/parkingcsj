-- =====================================================================
--  Sprint 1 — Model E/R: Script de creació de la base de dades
--  Projecte: Sistema de Reserves de Places de Pàrquing · Tarraco Parkings
--  SGBD: MySQL / MariaDB   ·   Joc de caràcters: utf8mb4
-- =====================================================================

-- Elimina la base de dades si existeix (per a desenvolupament)
DROP DATABASE IF EXISTS tarraco_parkings;
CREATE DATABASE tarraco_parkings
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tarraco_parkings;

-- ---------------------------------------------------------------------
-- 1. CLIENTS  (usuaris registrats; els administradors es marquen amb es_admin)
-- ---------------------------------------------------------------------
CREATE TABLE clients (
    id_client         INT AUTO_INCREMENT,
    nom               VARCHAR(50)  NOT NULL,
    primer_cognom     VARCHAR(50)  NOT NULL,
    segon_cognom      VARCHAR(50)  NULL,
    email             VARCHAR(120) NOT NULL,
    telefon           VARCHAR(20)  NULL,
    password_hash     VARCHAR(255) NOT NULL,
    es_admin          BOOLEAN      NOT NULL DEFAULT 0,
    actiu             BOOLEAN      NOT NULL DEFAULT 1,
    data_registre     DATETIME     NULL,
    CONSTRAINT pk_clients PRIMARY KEY (id_client),
    CONSTRAINT uq_clients_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. VEHICLES  (vehicles de cada client)
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    id_vehicle    INT AUTO_INCREMENT,
    id_client     INT          NOT NULL,
    matricula     VARCHAR(10)  NOT NULL,
    marca         VARCHAR(50)  NOT NULL,
    model         VARCHAR(50)  NOT NULL,
    CONSTRAINT pk_vehicles PRIMARY KEY (id_vehicle),
    CONSTRAINT uq_vehicles_matricula UNIQUE (matricula),
    CONSTRAINT fk_vehicles_client
        FOREIGN KEY (id_client) REFERENCES clients (id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. PARQUINGS  (pàrquings municipals)
-- ---------------------------------------------------------------------
CREATE TABLE parquings (
    id_parquing         INT AUTO_INCREMENT,
    nom                 VARCHAR(80)  NOT NULL,
    ubicacio            VARCHAR(200) NOT NULL,
    places_totals       INT          NOT NULL DEFAULT 0,
    places_reservables  INT          NOT NULL DEFAULT 0,
    actiu               BOOLEAN      NOT NULL DEFAULT 1,
    CONSTRAINT pk_parquings PRIMARY KEY (id_parquing)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. PLACES  (places reservables de cada pàrquing)
-- ---------------------------------------------------------------------
CREATE TABLE places (
    id_placa      INT AUTO_INCREMENT,
    id_parquing   INT         NOT NULL,
    codi_placa    VARCHAR(20) NOT NULL,
    tipus         ENUM('general', 'mobilitat_reduida', 'electric') NOT NULL DEFAULT 'general',
    estat         ENUM('lliure', 'reservada', 'ocupada') NOT NULL DEFAULT 'lliure',
    CONSTRAINT pk_places PRIMARY KEY (id_placa),
    CONSTRAINT uq_places_codi UNIQUE (id_parquing, codi_placa),
    CONSTRAINT fk_places_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. TARIFES  (configuració de preus, editable des de l'administració)
-- ---------------------------------------------------------------------
CREATE TABLE tarifes (
    id_tarifa  INT AUTO_INCREMENT,
    concepte   VARCHAR(60)  NOT NULL,
    preu       DECIMAL(10,2) NOT NULL,
    actiu      BOOLEAN       NOT NULL DEFAULT 1,
    CONSTRAINT pk_tarifes PRIMARY KEY (id_tarifa)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. RESERVES  (les reserves NO s'eliminen; només canvien d'estat)
-- ---------------------------------------------------------------------
CREATE TABLE reserves (
    id_reserva              INT AUTO_INCREMENT,
    numero_reserva          VARCHAR(14)  NOT NULL,
    id_client               INT          NOT NULL,
    id_vehicle              INT          NOT NULL,
    id_parquing             INT          NOT NULL,
    id_placa                INT          NULL,
    data                    DATE         NOT NULL,
    hora_entrada            TIME         NOT NULL,
    hora_sortida            TIME         NOT NULL,
    preu_base               DECIMAL(10,2) NOT NULL,
    despeses_gestio         DECIMAL(10,2) NOT NULL,
    preu_total              DECIMAL(10,2) NOT NULL,
    estat                   ENUM('pendent', 'confirmada', 'cancel·lada', 'completada') NOT NULL DEFAULT 'pendent',
    condicions_acceptades   BOOLEAN      NOT NULL DEFAULT 0,
    pdf_path                VARCHAR(255) NULL,
    creada_a                DATETIME     NULL,
    CONSTRAINT pk_reserves PRIMARY KEY (id_reserva),
    CONSTRAINT uq_reserves_numero UNIQUE (numero_reserva),
    CONSTRAINT fk_reserves_client
        FOREIGN KEY (id_client) REFERENCES clients (id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_vehicle
        FOREIGN KEY (id_vehicle) REFERENCES vehicles (id_vehicle)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_placa
        FOREIGN KEY (id_placa) REFERENCES places (id_placa)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_reserves_horari CHECK (hora_sortida > hora_entrada)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PAGAMENTS  (1 reserva = 1 pagament)
-- ---------------------------------------------------------------------
CREATE TABLE pagaments (
    id_pagament    INT AUTO_INCREMENT,
    id_reserva     INT          NOT NULL,
    metode         ENUM('targeta', 'paypal', 'apple_pay') NOT NULL,
    import         DECIMAL(10,2) NOT NULL,
    estat          ENUM('pendent', 'processat', 'reemborsat') NOT NULL DEFAULT 'pendent',
    referencia     VARCHAR(100) NULL,
    data_pagament  DATETIME     NULL,
    CONSTRAINT pk_pagaments PRIMARY KEY (id_pagament),
    CONSTRAINT uq_pagaments_reserva UNIQUE (id_reserva),
    CONSTRAINT fk_pagaments_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. ACCESSOS  (control de matrícules: entrades i sortides)
-- ---------------------------------------------------------------------
CREATE TABLE accessos (
    id_acces          INT AUTO_INCREMENT,
    id_parquing       INT          NOT NULL,
    matricula         VARCHAR(10)  NOT NULL,
    id_reserva        INT          NULL,
    tipus             ENUM('entrada', 'sortida') NOT NULL,
    data_hora         DATETIME     NOT NULL,
    barrera_oberta    BOOLEAN      NOT NULL,
    motiu_denegacio   VARCHAR(100) NULL,
    CONSTRAINT pk_accessos PRIMARY KEY (id_acces),
    CONSTRAINT fk_accessos_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_accessos_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  ÍNDEXS RECOMANATS (rendiment de les consultes freqüents)
-- =====================================================================
CREATE INDEX idx_vehicles_matricula ON vehicles (matricula);
CREATE INDEX idx_reserves_client    ON reserves (id_client);
CREATE INDEX idx_reserves_data      ON reserves (data);
CREATE INDEX idx_reserves_estat     ON reserves (estat);
CREATE INDEX idx_accessos_matricula ON accessos (matricula);
CREATE INDEX idx_accessos_data      ON accessos (data_hora);

-- =====================================================================
--  DADES SEMILLA (seed)
-- =====================================================================

-- Tarifes segons l'enunciat
INSERT INTO tarifes (concepte, preu, actiu) VALUES
('1a hora',                1.90, 1),
('2a hora',                1.90, 1),
('3a hora',                0.20, 1),
('preu maxim diari',       4.00, 1),
('nocturna (22:00-06:00)', 0.60, 1);

-- Pàrquings municipals de Tarragona
INSERT INTO parquings (nom, ubicacio, places_totals, places_reservables, actiu) VALUES
('Pàrquing Imperial Tàrraco',      'Av. Catalunya, s/n · Tarragona',   450, 100, 1),
('Pàrquing Sant Francesc',         'Carrer Assalt, 1 · Tarragona',     300,  80, 1),
('Pàrquing Sant Pere i Sant Pau',  'Camí de l''Institut, 12 · Tarragona', 200,  50, 1);

-- Places reservables del primer pàrquing (mostra)
INSERT INTO places (id_parquing, codi_placa, tipus, estat) VALUES
(1, 'P1-A01', 'general',            'lliure'),
(1, 'P1-A02', 'general',            'lliure'),
(1, 'P1-A03', 'mobilitat_reduida',  'lliure'),
(1, 'P1-A04', 'electric',           'lliure'),
(1, 'P1-A05', 'general',            'lliure');

-- Client de demostració.
-- IMPORTANT: el password_hash s'ha de generar amb password_hash() de PHP.
-- Exemple per a la contrasenya "Demo1234!":
--   SELECT password_hash_en_php;  (genera: $2y$10$....)
INSERT INTO clients (nom, primer_cognom, segon_cognom, email, telefon, password_hash, es_admin, actiu, data_registre) VALUES
('Client', 'Demostració', 'Tarraco', 'client@demo.cat', '977000000', '$2y$10$CANVIAR_A_UN_HASH_REAL', 0, 1, NOW());

-- Administrador de demostració (mateixa nota sobre el hash)
INSERT INTO clients (nom, primer_cognom, segon_cognom, email, telefon, password_hash, es_admin, actiu, data_registre) VALUES
('Admin', 'Tarraco', 'Parkings', 'admin@demo.cat', '977111111', '$2y$10$CANVIAR_A_UN_HASH_REAL', 1, 1, NOW());

-- Vehicle de demostració per al client
INSERT INTO vehicles (id_client, matricula, marca, model) VALUES
(1, '1234ABC', 'Seat', 'Ibiza');