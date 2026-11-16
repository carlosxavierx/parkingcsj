-- =====================================================================
--  CSJ Parkings · Sistema de Reserves de Places de Pàrquing
--  Script de creació de la base de dades (Sprint 2 · E/R actualitzat)
--  SGBD: MariaDB 10.6+ / MySQL 8+   ·   Joc de caràcters: utf8mb4
--
--  Canvis respecte al Sprint 1 (veure docs/sprint2/04-model-er-actualitzat.md):
--    · clients -> usuaris amb rol (client, operador, admin)
--    · nova taula metodes_pagament (l'usuari "té" mètodes de pagament)
--    · pagaments 1:N amb la reserva (reserva + cobrament d'excés)
--    · reserves amb DATETIME d'entrada i sortida (permet reserves de nit)
--    · dades sensibles xifrades (telèfon, DNI) i bloqueig per intents fallits
--    · nova taula historial_reserves (auditoria: les reserves mai s'eliminen)
--    · tarifes configurables per pàrquing
--    · valors ENUM sense accents ni símbols (cancellada) per evitar errors al codi
-- =====================================================================

DROP DATABASE IF EXISTS csj_parkings;
CREATE DATABASE csj_parkings
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE csj_parkings;

-- ---------------------------------------------------------------------
-- 1. PARQUINGS  (pàrquings municipals de Tarragona)
--    Es crea primer perquè usuaris (operadors) en depèn.
-- ---------------------------------------------------------------------
CREATE TABLE parquings (
    id_parquing         INT UNSIGNED AUTO_INCREMENT,
    nom                 VARCHAR(100)  NOT NULL,
    adreca              VARCHAR(200)  NOT NULL,
    codi_postal         CHAR(5)       NOT NULL,
    latitud             DECIMAL(9,7)  NOT NULL,
    longitud            DECIMAL(10,7) NOT NULL,
    tipus               ENUM('subterrani', 'superficie', 'plantes') NOT NULL DEFAULT 'superficie',
    places_totals       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    places_reservables  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    horari              VARCHAR(40)   NOT NULL DEFAULT '24 h',
    telefon             VARCHAR(20)   NULL,
    descripcio          VARCHAR(500)  NULL,
    serveis             SET('cobert', 'cctv', 'pmr', 'barrera', 'recarrega', 'ascensor', 'moto', '24h') NULL,
    actiu               BOOLEAN       NOT NULL DEFAULT 1,
    data_alta           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_parquings PRIMARY KEY (id_parquing),
    CONSTRAINT uq_parquings_nom UNIQUE (nom),
    CONSTRAINT chk_parquings_places CHECK (places_reservables <= places_totals)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. USUARIS  (clients, operadors de pàrquing i administradors)
--    Dades sensibles: telèfon i DNI es guarden XIFRATS amb AES-256 des de PHP
--    (openssl_encrypt). dni_hash (SHA-256 amb "pebre") permet comprovar que
--    un DNI no estigui repetit sense desxifrar-lo.
-- ---------------------------------------------------------------------
CREATE TABLE usuaris (
    id_usuari             INT UNSIGNED AUTO_INCREMENT,
    nom                   VARCHAR(50)   NOT NULL,
    primer_cognom         VARCHAR(50)   NOT NULL,
    segon_cognom          VARCHAR(50)   NULL,
    email                 VARCHAR(120)  NOT NULL,
    telefon_xifrat        VARCHAR(255)  NULL,
    dni_xifrat            VARCHAR(255)  NULL,
    dni_hash              CHAR(64)      NULL,
    password_hash         VARCHAR(255)  NOT NULL,
    rol                   ENUM('client', 'operador', 'admin') NOT NULL DEFAULT 'client',
    id_parquing_assignat  INT UNSIGNED  NULL,          -- només per a operadors
    actiu                 BOOLEAN       NOT NULL DEFAULT 1,
    intents_fallits       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloquejat_fins        DATETIME      NULL,          -- protecció contra força bruta
    data_registre         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultim_acces           DATETIME      NULL,
    CONSTRAINT pk_usuaris PRIMARY KEY (id_usuari),
    CONSTRAINT uq_usuaris_email UNIQUE (email),
    CONSTRAINT uq_usuaris_dni UNIQUE (dni_hash),
    CONSTRAINT fk_usuaris_parquing
        FOREIGN KEY (id_parquing_assignat) REFERENCES parquings (id_parquing)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. VEHICLES  (un usuari pot tenir diversos vehicles)
--    No s'esborren si tenen reserves: es desactiven (actiu = 0).
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    id_vehicle    INT UNSIGNED AUTO_INCREMENT,
    id_usuari     INT UNSIGNED  NOT NULL,
    matricula     VARCHAR(10)   NOT NULL,          -- format normalitzat: 1234ABC
    marca         VARCHAR(50)   NOT NULL,
    model         VARCHAR(50)   NOT NULL,
    color         VARCHAR(30)   NULL,
    tipus         ENUM('cotxe', 'moto', 'furgoneta') NOT NULL DEFAULT 'cotxe',
    electric      BOOLEAN       NOT NULL DEFAULT 0,
    actiu         BOOLEAN       NOT NULL DEFAULT 1,
    data_alta     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_vehicles PRIMARY KEY (id_vehicle),
    CONSTRAINT uq_vehicles_matricula UNIQUE (matricula),   -- clau per al lector de matrícules
    CONSTRAINT fk_vehicles_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. METODES_PAGAMENT  (mètodes de pagament de l'usuari · simulats)
--    MAI es guarda el número complet de la targeta ni el CVV:
--    només els 4 últims dígits i un "token" de la passarel·la simulada.
-- ---------------------------------------------------------------------
CREATE TABLE metodes_pagament (
    id_metode           INT UNSIGNED AUTO_INCREMENT,
    id_usuari           INT UNSIGNED  NOT NULL,
    tipus               ENUM('targeta', 'paypal', 'bizum') NOT NULL,
    titular             VARCHAR(100)  NOT NULL,
    marca_targeta       VARCHAR(20)   NULL,          -- Visa, Mastercard...
    ultims_digits       CHAR(4)       NULL,          -- targeta: 4 últims dígits
    caducitat           CHAR(5)       NULL,          -- MM/AA
    referencia_xifrada  VARCHAR(255)  NULL,          -- email PayPal / telèfon Bizum (xifrat)
    token_passarella    CHAR(32)      NOT NULL,      -- identificador simulat de la passarel·la
    per_defecte         BOOLEAN       NOT NULL DEFAULT 0,
    actiu               BOOLEAN       NOT NULL DEFAULT 1,
    data_alta           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_metodes_pagament PRIMARY KEY (id_metode),
    CONSTRAINT uq_metodes_token UNIQUE (token_passarella),
    CONSTRAINT fk_metodes_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. PLACES  (places reservables de cada pàrquing)
--    L'ocupació d'una plaça en un moment depèn de les reserves; aquí només
--    es guarda si està operativa o fora de servei.
-- ---------------------------------------------------------------------
CREATE TABLE places (
    id_placa      INT UNSIGNED AUTO_INCREMENT,
    id_parquing   INT UNSIGNED  NOT NULL,
    codi_placa    VARCHAR(20)   NOT NULL,
    planta        VARCHAR(5)    NULL,
    tipus         ENUM('general', 'pmr', 'electric', 'moto') NOT NULL DEFAULT 'general',
    estat         ENUM('disponible', 'fora_servei') NOT NULL DEFAULT 'disponible',
    CONSTRAINT pk_places PRIMARY KEY (id_placa),
    CONSTRAINT uq_places_codi UNIQUE (id_parquing, codi_placa),
    CONSTRAINT fk_places_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. TARIFES  (editables des del panel d'administració)
--    id_parquing NULL = tarifa general; amb valor = tarifa pròpia del pàrquing.
-- ---------------------------------------------------------------------
CREATE TABLE tarifes (
    id_tarifa     INT UNSIGNED AUTO_INCREMENT,
    id_parquing   INT UNSIGNED  NULL,
    codi          ENUM('preu_hora_inicial', 'hores_inicials', 'preu_hora_extra',
                       'preu_nocturn', 'inici_nocturn', 'fi_nocturn',
                       'maxim_diari', 'percentatge_gestio') NOT NULL,
    descripcio    VARCHAR(100)  NOT NULL,
    valor         DECIMAL(10,2) NOT NULL,
    actiu         BOOLEAN       NOT NULL DEFAULT 1,
    modificada_a  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_tarifes PRIMARY KEY (id_tarifa),
    CONSTRAINT uq_tarifes_codi UNIQUE (id_parquing, codi),
    CONSTRAINT fk_tarifes_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_tarifes_valor CHECK (valor >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. RESERVES  (les reserves NO s'eliminen mai; només canvien d'estat)
--    Estats: pendent -> confirmada (activa) -> en_curs (vehicle dins)
--            -> completada · o cancellada en qualsevol moment abans d'entrar.
-- ---------------------------------------------------------------------
CREATE TABLE reserves (
    id_reserva              INT UNSIGNED AUTO_INCREMENT,
    numero_reserva          VARCHAR(20)   NOT NULL,          -- CSJ-AAAAMMDD-XXXXXX
    id_usuari               INT UNSIGNED  NOT NULL,
    id_vehicle              INT UNSIGNED  NOT NULL,
    id_parquing             INT UNSIGNED  NOT NULL,
    id_placa                INT UNSIGNED  NULL,
    id_metode_pagament      INT UNSIGNED  NOT NULL,          -- s'usa també per cobrar l'excés
    entrada_prevista        DATETIME      NOT NULL,
    sortida_prevista        DATETIME      NOT NULL,
    preu_base               DECIMAL(10,2) NOT NULL,
    despeses_gestio         DECIMAL(10,2) NOT NULL,
    preu_total              DECIMAL(10,2) NOT NULL,
    estat                   ENUM('pendent', 'confirmada', 'en_curs', 'completada', 'cancellada')
                            NOT NULL DEFAULT 'pendent',
    condicions_acceptades   BOOLEAN       NOT NULL,
    data_acceptacio         DATETIME      NOT NULL,
    observacions            VARCHAR(255)  NULL,
    creada_a                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificada_a            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    cancellada_a            DATETIME      NULL,
    cancellada_per          INT UNSIGNED  NULL,
    motiu_cancellacio       VARCHAR(255)  NULL,
    CONSTRAINT pk_reserves PRIMARY KEY (id_reserva),
    CONSTRAINT uq_reserves_numero UNIQUE (numero_reserva),
    CONSTRAINT fk_reserves_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
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
    CONSTRAINT fk_reserves_metode
        FOREIGN KEY (id_metode_pagament) REFERENCES metodes_pagament (id_metode)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_cancellada_per
        FOREIGN KEY (cancellada_per) REFERENCES usuaris (id_usuari)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_reserves_horari     CHECK (sortida_prevista > entrada_prevista),
    CONSTRAINT chk_reserves_condicions CHECK (condicions_acceptades = 1),
    CONSTRAINT chk_reserves_preu       CHECK (preu_total = preu_base + despeses_gestio)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. PAGAMENTS  (1 reserva -> N pagaments: reserva, excés de temps, reemborsament)
-- ---------------------------------------------------------------------
CREATE TABLE pagaments (
    id_pagament         INT UNSIGNED AUTO_INCREMENT,
    id_reserva          INT UNSIGNED  NOT NULL,
    id_metode_pagament  INT UNSIGNED  NOT NULL,
    concepte            ENUM('reserva', 'exces', 'reemborsament') NOT NULL DEFAULT 'reserva',
    import              DECIMAL(10,2) NOT NULL,
    estat               ENUM('pendent', 'completat', 'fallit', 'reemborsat') NOT NULL DEFAULT 'pendent',
    referencia          VARCHAR(40)   NULL,          -- referència de la passarel·la simulada
    data_pagament       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_pagaments PRIMARY KEY (id_pagament),
    CONSTRAINT fk_pagaments_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pagaments_metode
        FOREIGN KEY (id_metode_pagament) REFERENCES metodes_pagament (id_metode)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_pagaments_import CHECK (import >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. ACCESSOS  (lector de matrícules: entrades i sortides)
-- ---------------------------------------------------------------------
CREATE TABLE accessos (
    id_acces          INT UNSIGNED AUTO_INCREMENT,
    id_parquing       INT UNSIGNED  NOT NULL,
    matricula         VARCHAR(10)   NOT NULL,         -- la llegida, encara que no existeixi a vehicles
    id_vehicle        INT UNSIGNED  NULL,
    id_reserva        INT UNSIGNED  NULL,
    id_operador       INT UNSIGNED  NULL,             -- operador que fa la simulació
    tipus             ENUM('entrada', 'sortida') NOT NULL,
    data_hora         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    barrera_oberta    BOOLEAN       NOT NULL,
    motiu             VARCHAR(100)  NULL,             -- motiu de denegació o observació
    minuts_exces      SMALLINT UNSIGNED NULL,         -- només a la sortida
    CONSTRAINT pk_accessos PRIMARY KEY (id_acces),
    CONSTRAINT fk_accessos_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_accessos_vehicle
        FOREIGN KEY (id_vehicle) REFERENCES vehicles (id_vehicle)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_accessos_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_accessos_operador
        FOREIGN KEY (id_operador) REFERENCES usuaris (id_usuari)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. HISTORIAL_RESERVES  (auditoria de tots els canvis d'una reserva)
-- ---------------------------------------------------------------------
CREATE TABLE historial_reserves (
    id_historial  INT UNSIGNED AUTO_INCREMENT,
    id_reserva    INT UNSIGNED  NOT NULL,
    id_usuari     INT UNSIGNED  NULL,                 -- qui ha fet el canvi (NULL = sistema)
    accio         ENUM('creacio', 'modificacio', 'canvi_estat', 'cancellacio', 'acces') NOT NULL,
    detall        VARCHAR(500)  NULL,                 -- p. ex. "estat: confirmada -> cancellada"
    data_hora     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_historial PRIMARY KEY (id_historial),
    CONSTRAINT fk_historial_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_historial_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  ÍNDEXS (consultes freqüents i cerques avançades de l'administrador)
-- =====================================================================
CREATE INDEX idx_usuaris_rol          ON usuaris (rol);
CREATE INDEX idx_vehicles_usuari      ON vehicles (id_usuari);
CREATE INDEX idx_metodes_usuari       ON metodes_pagament (id_usuari);
CREATE INDEX idx_reserves_usuari      ON reserves (id_usuari);
CREATE INDEX idx_reserves_disponib    ON reserves (id_parquing, estat, entrada_prevista, sortida_prevista);
CREATE INDEX idx_reserves_estat       ON reserves (estat);
CREATE INDEX idx_reserves_entrada     ON reserves (entrada_prevista);
CREATE INDEX idx_pagaments_reserva    ON pagaments (id_reserva);
CREATE INDEX idx_accessos_matricula   ON accessos (matricula, data_hora);
CREATE INDEX idx_accessos_parquing    ON accessos (id_parquing, data_hora);
CREATE INDEX idx_historial_reserva    ON historial_reserves (id_reserva);

-- =====================================================================
--  VISTES
-- =====================================================================

-- Detall de reserves per al panel d'administració i les cerques avançades
CREATE VIEW v_reserves_detall AS
SELECT r.id_reserva,
       r.numero_reserva,
       r.estat,
       r.entrada_prevista,
       r.sortida_prevista,
       r.preu_total,
       r.creada_a,
       u.id_usuari,
       CONCAT(u.nom, ' ', u.primer_cognom, IFNULL(CONCAT(' ', u.segon_cognom), '')) AS client,
       u.email,
       v.matricula,
       CONCAT(v.marca, ' ', v.model) AS vehicle,
       p.id_parquing,
       p.nom AS parquing,
       pl.codi_placa,
       mp.tipus AS metode_pagament
FROM reserves r
JOIN usuaris u           ON u.id_usuari   = r.id_usuari
JOIN vehicles v          ON v.id_vehicle  = r.id_vehicle
JOIN parquings p         ON p.id_parquing = r.id_parquing
LEFT JOIN places pl      ON pl.id_placa   = r.id_placa
JOIN metodes_pagament mp ON mp.id_metode  = r.id_metode_pagament;

-- Ocupació actual de cada pàrquing (places reservables ocupades ara mateix)
CREATE VIEW v_ocupacio_actual AS
SELECT p.id_parquing,
       p.nom,
       p.places_reservables,
       COUNT(r.id_reserva) AS reserves_actives_ara,
       p.places_reservables - COUNT(r.id_reserva) AS places_lliures_ara
FROM parquings p
LEFT JOIN reserves r
       ON r.id_parquing = p.id_parquing
      AND r.estat IN ('confirmada', 'en_curs')
      AND NOW() BETWEEN r.entrada_prevista AND r.sortida_prevista
WHERE p.actiu = 1
GROUP BY p.id_parquing, p.nom, p.places_reservables;
