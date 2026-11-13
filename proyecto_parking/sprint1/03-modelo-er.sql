-- =====================================================================
--  Sprint 1 — Model E/R: script de creació de la base de dades (v2)
--  Projecte : Sistema de Reserves de Places de Pàrquing · Tarraco Parkings
--  SGBD     : MariaDB >= 10.5  /  MySQL >= 8.0.16   (CHECK, triggers, vistes)
--  Charset  : utf8mb4 / utf8mb4_unicode_ci
--
--  Canvis respecte la v1 (resum, detall a 02-modelo-er.md §7):
--   * Rols d'usuari (client, operador, administrador) i separació
--     usuaris (credencials) / clients (dades personals).
--   * Mètodes de pagament del client (tokenitzats, mai el número complet).
--   * Reserves amb DATETIME d'entrada i sortida (permet nits i diversos dies).
--   * numero_reserva VARCHAR(20) (abans 14 i no hi cabia TPK-AAAAMMDD-NNNNNN).
--   * Pagaments 1:N (reserva, excés de temps, reemborsament).
--   * Lectors de matrícules, accessos, historial de canvis de reserves.
--   * Tarifes amb vigència i configurables per pàrquing; condicions versionades.
--   * Integritat reforçada: FK compostes, CHECKs, triggers (no solapament,
--     capacitat, prohibició d'esborrar reserves) i vistes per a l'admin.
-- =====================================================================

DROP DATABASE IF EXISTS tarraco_parkings;
CREATE DATABASE tarraco_parkings
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tarraco_parkings;

-- =====================================================================
--  BLOC 1 · USUARIS, ROLS I CLIENTS
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. ROLS  (client, operador, administrador...). Taula i no ENUM perquè
--    en el futur es puguin afegir rols sense alterar l'estructura.
-- ---------------------------------------------------------------------
CREATE TABLE rols (
    id_rol       TINYINT UNSIGNED AUTO_INCREMENT,
    codi         VARCHAR(20)  NOT NULL,
    nom          VARCHAR(50)  NOT NULL,
    descripcio   VARCHAR(255) NULL,
    CONSTRAINT pk_rols PRIMARY KEY (id_rol),
    CONSTRAINT uq_rols_codi UNIQUE (codi)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. USUARIS  (credencials d'accés, comunes a tots els rols)
-- ---------------------------------------------------------------------
CREATE TABLE usuaris (
    id_usuari         INT UNSIGNED AUTO_INCREMENT,
    id_rol            TINYINT UNSIGNED NOT NULL,
    email             VARCHAR(120) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,             -- bcrypt / argon2 (password_hash de PHP)
    email_verificat   BOOLEAN      NOT NULL DEFAULT 0,
    token_verificacio CHAR(64)     NULL,                 -- verificació d'email / recuperar contrasenya
    token_caducitat   DATETIME     NULL,
    intents_fallits   TINYINT UNSIGNED NOT NULL DEFAULT 0, -- bloqueig contra força bruta
    bloquejat_fins    DATETIME     NULL,
    actiu             BOOLEAN      NOT NULL DEFAULT 1,   -- baixa lògica (mai s'esborra)
    ultim_acces       DATETIME     NULL,
    creat_a           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualitzat_a     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_usuaris PRIMARY KEY (id_usuari),
    CONSTRAINT uq_usuaris_email UNIQUE (email),
    CONSTRAINT fk_usuaris_rol
        FOREIGN KEY (id_rol) REFERENCES rols (id_rol)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_usuaris_email CHECK (email LIKE '%_@_%._%')
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. CLIENTS  (dades personals dels usuaris amb rol 'client'; 1:1 amb usuaris)
--    Dades sensibles: el DNI es guarda XIFRAT (AES) i, a part, un hash
--    (HMAC-SHA256) per poder comprovar que no està repetit sense desxifrar-lo.
-- ---------------------------------------------------------------------
CREATE TABLE clients (
    id_client          INT UNSIGNED AUTO_INCREMENT,
    id_usuari          INT UNSIGNED NOT NULL,
    nom                VARCHAR(50)  NOT NULL,
    primer_cognom      VARCHAR(50)  NOT NULL,
    segon_cognom       VARCHAR(50)  NULL,
    dni_xifrat         VARBINARY(255) NOT NULL,          -- AES_ENCRYPT(dni, clau_app)
    dni_hash           CHAR(64)     NOT NULL,            -- SHA2(clau_app + dni, 256)
    telefon            VARCHAR(20)  NOT NULL,
    data_naixement     DATE         NULL,
    adreca             VARCHAR(150) NULL,
    codi_postal        CHAR(5)      NULL,
    poblacio           VARCHAR(80)  NULL,
    consentiment_rgpd  DATETIME     NOT NULL,            -- quan va acceptar la política de privacitat
    accepta_publicitat BOOLEAN      NOT NULL DEFAULT 0,
    CONSTRAINT pk_clients PRIMARY KEY (id_client),
    CONSTRAINT uq_clients_usuari UNIQUE (id_usuari),
    CONSTRAINT uq_clients_dni UNIQUE (dni_hash),
    CONSTRAINT fk_clients_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_clients_cp CHECK (codi_postal IS NULL OR codi_postal REGEXP '^[0-9]{5}$')
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. VEHICLES  (un client en pot tenir diversos)
--    La matrícula es guarda normalitzada (majúscules, sense espais ni guions).
--    Només pot haver-hi UN vehicle ACTIU amb cada matrícula (columna generada),
--    però es conserva l'històric si un vehicle es dona de baixa o canvia d'amo.
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    id_vehicle        INT UNSIGNED AUTO_INCREMENT,
    id_client         INT UNSIGNED NOT NULL,
    matricula         VARCHAR(10)  NOT NULL,
    marca             VARCHAR(50)  NOT NULL,
    model             VARCHAR(50)  NOT NULL,
    color             VARCHAR(30)  NULL,
    tipus             ENUM('turisme', 'moto', 'furgoneta') NOT NULL DEFAULT 'turisme',
    es_electric       BOOLEAN      NOT NULL DEFAULT 0,
    actiu             BOOLEAN      NOT NULL DEFAULT 1,
    matricula_activa  VARCHAR(10)  AS (IF(actiu = 1, matricula, NULL)) STORED,
    creat_a           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_vehicles PRIMARY KEY (id_vehicle),
    CONSTRAINT uq_vehicles_matricula_activa UNIQUE (matricula_activa),
    CONSTRAINT uq_vehicles_id_client UNIQUE (id_vehicle, id_client),   -- per a la FK composta de reserves
    CONSTRAINT fk_vehicles_client
        FOREIGN KEY (id_client) REFERENCES clients (id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_vehicles_matricula CHECK (matricula REGEXP '^[A-Z0-9]{4,10}$')
) ENGINE=InnoDB;

CREATE INDEX idx_vehicles_matricula ON vehicles (matricula);

-- ---------------------------------------------------------------------
-- 5. METODES_PAGAMENT  (mètodes de pagament desats pel client)
--    MAI es guarda el número complet de la targeta ni el CVV (PCI-DSS):
--    només el token que retorna la passarel·la i els 4 últims dígits.
-- ---------------------------------------------------------------------
CREATE TABLE metodes_pagament (
    id_metode        INT UNSIGNED AUTO_INCREMENT,
    id_client        INT UNSIGNED NOT NULL,
    tipus            ENUM('targeta', 'paypal', 'bizum') NOT NULL,
    titular          VARCHAR(100) NOT NULL,
    marca_targeta    VARCHAR(20)  NULL,                  -- VISA, Mastercard...
    ultims_digits    CHAR(4)      NULL,                  -- **** **** **** 1234
    caducitat_mes    TINYINT UNSIGNED NULL,
    caducitat_any    SMALLINT UNSIGNED NULL,
    email_compte     VARCHAR(120) NULL,                  -- PayPal
    telefon_compte   VARCHAR(20)  NULL,                  -- Bizum
    token_passarela  VARCHAR(255) NOT NULL,              -- referència opaca de la passarel·la
    predeterminat    BOOLEAN      NOT NULL DEFAULT 0,
    actiu            BOOLEAN      NOT NULL DEFAULT 1,
    creat_a          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_metodes_pagament PRIMARY KEY (id_metode),
    CONSTRAINT uq_metodes_id_client UNIQUE (id_metode, id_client),   -- per a la FK composta de reserves
    CONSTRAINT fk_metodes_client
        FOREIGN KEY (id_client) REFERENCES clients (id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_metodes_digits   CHECK (ultims_digits IS NULL OR ultims_digits REGEXP '^[0-9]{4}$'),
    CONSTRAINT chk_metodes_mes      CHECK (caducitat_mes IS NULL OR caducitat_mes BETWEEN 1 AND 12),
    CONSTRAINT chk_metodes_targeta  CHECK (tipus <> 'targeta'
                                           OR (ultims_digits IS NOT NULL AND caducitat_mes IS NOT NULL
                                               AND caducitat_any IS NOT NULL))
) ENGINE=InnoDB;

-- =====================================================================
--  BLOC 2 · PÀRQUINGS, PLACES, LECTORS I TARIFES
-- =====================================================================

-- ---------------------------------------------------------------------
-- 6. PARQUINGS
-- ---------------------------------------------------------------------
CREATE TABLE parquings (
    id_parquing      INT UNSIGNED AUTO_INCREMENT,
    nom              VARCHAR(80)  NOT NULL,
    adreca           VARCHAR(150) NOT NULL,
    codi_postal      CHAR(5)      NOT NULL,
    poblacio         VARCHAR(80)  NOT NULL DEFAULT 'Tarragona',
    latitud          DECIMAL(9,6) NULL,                  -- per mostrar-lo al mapa
    longitud         DECIMAL(9,6) NULL,
    telefon          VARCHAR(20)  NULL,
    obert_24h        BOOLEAN      NOT NULL DEFAULT 1,
    hora_obertura    TIME         NULL,
    hora_tancament   TIME         NULL,
    places_totals    SMALLINT UNSIGNED NOT NULL,         -- places físiques (reservables + rotació)
    actiu            BOOLEAN      NOT NULL DEFAULT 1,
    creat_a          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_parquings PRIMARY KEY (id_parquing),
    CONSTRAINT uq_parquings_nom UNIQUE (nom),
    CONSTRAINT chk_parquings_places CHECK (places_totals > 0),
    CONSTRAINT chk_parquings_horari CHECK (obert_24h = 1
                                           OR (hora_obertura IS NOT NULL AND hora_tancament IS NOT NULL))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. OPERADORS_PARQUINGS  (relació N:M: quins pàrquings gestiona cada operador)
-- ---------------------------------------------------------------------
CREATE TABLE operadors_parquings (
    id_usuari     INT UNSIGNED NOT NULL,
    id_parquing   INT UNSIGNED NOT NULL,
    assignat_a    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_operadors_parquings PRIMARY KEY (id_usuari, id_parquing),
    CONSTRAINT fk_operadors_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_operadors_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. PLACES  (places de cada pàrquing; l'admin decideix quines són reservables)
--    'estat' és l'estat FÍSIC de la plaça. L'ocupació en una franja horària
--    NO es guarda aquí: es calcula a partir de les reserves (vista
--    v_disponibilitat_parquings), així no hi ha dades contradictòries.
-- ---------------------------------------------------------------------
CREATE TABLE places (
    id_placa      INT UNSIGNED AUTO_INCREMENT,
    id_parquing   INT UNSIGNED NOT NULL,
    codi_placa    VARCHAR(20)  NOT NULL,                 -- ex. P1-A01
    planta        TINYINT      NOT NULL DEFAULT 0,       -- -1, -2 = soterrani
    tipus         ENUM('general', 'mobilitat_reduida', 'electric', 'moto', 'familiar')
                               NOT NULL DEFAULT 'general',
    reservable    BOOLEAN      NOT NULL DEFAULT 1,
    estat         ENUM('operativa', 'fora_servei') NOT NULL DEFAULT 'operativa',
    CONSTRAINT pk_places PRIMARY KEY (id_placa),
    CONSTRAINT uq_places_codi UNIQUE (id_parquing, codi_placa),
    CONSTRAINT uq_places_id_parquing UNIQUE (id_placa, id_parquing),  -- per a la FK composta de reserves
    CONSTRAINT fk_places_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. LECTORS  (lectors de matrícules / barreres de cada pàrquing)
-- ---------------------------------------------------------------------
CREATE TABLE lectors (
    id_lector     INT UNSIGNED AUTO_INCREMENT,
    id_parquing   INT UNSIGNED NOT NULL,
    codi          VARCHAR(20)  NOT NULL,                 -- ex. P1-ENT-1
    tipus         ENUM('entrada', 'sortida') NOT NULL,
    adreca_ip     VARCHAR(45)  NULL,                     -- IPv4 / IPv6 del dispositiu
    actiu         BOOLEAN      NOT NULL DEFAULT 1,
    CONSTRAINT pk_lectors PRIMARY KEY (id_lector),
    CONSTRAINT uq_lectors_codi UNIQUE (codi),
    CONSTRAINT fk_lectors_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. TARIFES  (preus editables per l'admin sense tocar codi)
--     id_parquing NULL  = tarifa general (la de l'enunciat).
--     id_parquing <> NULL = tarifa específica d'un pàrquing (si en el futur cal).
--     La vigència permet canviar preus sense perdre l'històric.
-- ---------------------------------------------------------------------
CREATE TABLE tarifes (
    id_tarifa     INT UNSIGNED AUTO_INCREMENT,
    id_parquing   INT UNSIGNED NULL,
    codi          ENUM('hora_1_2', 'hora_3_endavant', 'maxim_diari', 'nocturna') NOT NULL,
    descripcio    VARCHAR(100) NOT NULL,
    preu          DECIMAL(6,2) NOT NULL,
    vigent_des    DATE         NOT NULL,
    vigent_fins   DATE         NULL,                     -- NULL = vigent actualment
    CONSTRAINT pk_tarifes PRIMARY KEY (id_tarifa),
    CONSTRAINT uq_tarifes UNIQUE (id_parquing, codi, vigent_des),
    CONSTRAINT fk_tarifes_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_tarifes_preu CHECK (preu >= 0),
    CONSTRAINT chk_tarifes_vigencia CHECK (vigent_fins IS NULL OR vigent_fins >= vigent_des)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. CONFIGURACIO  (paràmetres globals: % gestió, franja nocturna...)
-- ---------------------------------------------------------------------
CREATE TABLE configuracio (
    clau          VARCHAR(50)  NOT NULL,
    valor         VARCHAR(255) NOT NULL,
    descripcio    VARCHAR(255) NULL,
    CONSTRAINT pk_configuracio PRIMARY KEY (clau)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. CONDICIONS  (versions de les condicions d'ús que accepta el client)
-- ---------------------------------------------------------------------
CREATE TABLE condicions (
    id_condicions  INT UNSIGNED AUTO_INCREMENT,
    versio         VARCHAR(10)  NOT NULL,
    text           TEXT         NOT NULL,
    vigent_des     DATE         NOT NULL,
    CONSTRAINT pk_condicions PRIMARY KEY (id_condicions),
    CONSTRAINT uq_condicions_versio UNIQUE (versio)
) ENGINE=InnoDB;

-- =====================================================================
--  BLOC 3 · RESERVES, PAGAMENTS, ACCESSOS I AUDITORIA
-- =====================================================================

-- ---------------------------------------------------------------------
-- 13. RESERVES  (MAI s'eliminen: només canvien d'estat — veure trigger)
--     FK compostes per garantir que:
--       - el vehicle pertany al client de la reserva,
--       - el mètode de pagament pertany al client,
--       - la plaça assignada és del pàrquing reservat.
--     Els preus es guarden com a "foto" del moment de la reserva: si les
--     tarifes canvien després, la reserva conserva el preu que es va cobrar.
-- ---------------------------------------------------------------------
CREATE TABLE reserves (
    id_reserva              INT UNSIGNED AUTO_INCREMENT,
    numero_reserva          VARCHAR(20)  NOT NULL,       -- TPK-AAAAMMDD-NNNNNN (19 caràcters)
    id_client               INT UNSIGNED NOT NULL,
    id_vehicle              INT UNSIGNED NOT NULL,
    id_parquing             INT UNSIGNED NOT NULL,
    id_placa                INT UNSIGNED NULL,           -- s'assigna en confirmar / entrar
    id_metode               INT UNSIGNED NOT NULL,       -- mateix mètode per cobrar l'excés
    id_condicions           INT UNSIGNED NOT NULL,       -- versió de condicions acceptada
    data_hora_entrada       DATETIME     NOT NULL,
    data_hora_sortida       DATETIME     NOT NULL,
    preu_base               DECIMAL(8,2) NOT NULL,
    despeses_gestio         DECIMAL(8,2) NOT NULL,       -- 10 % del preu base
    preu_total              DECIMAL(8,2) NOT NULL,
    estat                   ENUM('pendent', 'confirmada', 'en_curs', 'completada',
                                 'cancelada', 'no_presentat') NOT NULL DEFAULT 'pendent',
    condicions_acceptades_a DATETIME     NOT NULL,
    motiu_cancelacio        VARCHAR(255) NULL,
    pdf_path                VARCHAR(255) NULL,
    creada_a                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualitzada_a          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_reserves PRIMARY KEY (id_reserva),
    CONSTRAINT uq_reserves_numero UNIQUE (numero_reserva),
    CONSTRAINT fk_reserves_client
        FOREIGN KEY (id_client) REFERENCES clients (id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_vehicle
        FOREIGN KEY (id_vehicle, id_client) REFERENCES vehicles (id_vehicle, id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_parquing
        FOREIGN KEY (id_parquing) REFERENCES parquings (id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_placa
        FOREIGN KEY (id_placa, id_parquing) REFERENCES places (id_placa, id_parquing)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_metode
        FOREIGN KEY (id_metode, id_client) REFERENCES metodes_pagament (id_metode, id_client)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserves_condicions
        FOREIGN KEY (id_condicions) REFERENCES condicions (id_condicions)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_reserves_numero  CHECK (numero_reserva REGEXP '^TPK-[0-9]{8}-[0-9]{6}$'),
    CONSTRAINT chk_reserves_horari  CHECK (data_hora_sortida > data_hora_entrada),
    CONSTRAINT chk_reserves_preus   CHECK (preu_base >= 0 AND despeses_gestio >= 0
                                           AND preu_total = preu_base + despeses_gestio),
    CONSTRAINT chk_reserves_cancel  CHECK (estat <> 'cancelada' OR motiu_cancelacio IS NOT NULL)
) ENGINE=InnoDB;

CREATE INDEX idx_reserves_estat_dates ON reserves (estat, data_hora_entrada, data_hora_sortida);
CREATE INDEX idx_reserves_parquing_dates ON reserves (id_parquing, data_hora_entrada, data_hora_sortida);

-- ---------------------------------------------------------------------
-- 14. RESERVES_HISTORIAL  (auditoria: qui ha canviat què i quan)
--     S'omple automàticament amb triggers. La web posa l'usuari que fa el
--     canvi a la variable de sessió @id_usuari_app abans de l'UPDATE.
--     id_usuari NULL = canvi fet pel sistema (lector, tasca programada...).
-- ---------------------------------------------------------------------
CREATE TABLE reserves_historial (
    id_historial    BIGINT UNSIGNED AUTO_INCREMENT,
    id_reserva      INT UNSIGNED NOT NULL,
    id_usuari       INT UNSIGNED NULL,
    accio           ENUM('creacio', 'canvi_estat', 'canvi_dades') NOT NULL,
    estat_anterior  VARCHAR(20)  NULL,
    estat_nou       VARCHAR(20)  NULL,
    detall          VARCHAR(500) NULL,
    data_canvi      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_reserves_historial PRIMARY KEY (id_historial),
    CONSTRAINT fk_historial_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_historial_usuari
        FOREIGN KEY (id_usuari) REFERENCES usuaris (id_usuari)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 15. PAGAMENTS  (1 reserva → N pagaments: reserva, excés, reemborsament)
-- ---------------------------------------------------------------------
CREATE TABLE pagaments (
    id_pagament          INT UNSIGNED AUTO_INCREMENT,
    id_reserva           INT UNSIGNED NOT NULL,
    id_metode            INT UNSIGNED NOT NULL,
    tipus                ENUM('reserva', 'exces', 'reemborsament') NOT NULL,
    import               DECIMAL(8,2) NOT NULL,
    estat                ENUM('pendent', 'completat', 'fallit', 'reemborsat') NOT NULL DEFAULT 'pendent',
    referencia_passarela VARCHAR(100) NULL,
    creat_a              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_pagament        DATETIME     NULL,
    CONSTRAINT pk_pagaments PRIMARY KEY (id_pagament),
    CONSTRAINT fk_pagaments_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pagaments_metode
        FOREIGN KEY (id_metode) REFERENCES metodes_pagament (id_metode)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_pagaments_import CHECK (import > 0),
    CONSTRAINT chk_pagaments_data   CHECK (estat <> 'completat' OR data_pagament IS NOT NULL)
) ENGINE=InnoDB;

CREATE INDEX idx_pagaments_reserva_tipus ON pagaments (id_reserva, tipus);

-- ---------------------------------------------------------------------
-- 16. ACCESSOS  (cada lectura de matrícula a una barrera)
--     id_vehicle / id_reserva són NULL si la matrícula no és d'un client
--     registrat o no té reserva (entrada per rotació si hi ha places lliures).
-- ---------------------------------------------------------------------
CREATE TABLE accessos (
    id_acces           BIGINT UNSIGNED AUTO_INCREMENT,
    id_lector          INT UNSIGNED NOT NULL,
    matricula_llegida  VARCHAR(10)  NOT NULL,
    id_vehicle         INT UNSIGNED NULL,
    id_reserva         INT UNSIGNED NULL,
    data_hora          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    barrera_oberta     BOOLEAN      NOT NULL,
    motiu              ENUM('reserva_valida', 'places_lliures', 'sense_reserva',
                            'parquing_ple', 'fora_horari', 'no_llegible',
                            'sortida', 'sortida_amb_exces') NOT NULL,
    minuts_exces       SMALLINT UNSIGNED NULL,           -- només a la sortida
    CONSTRAINT pk_accessos PRIMARY KEY (id_acces),
    CONSTRAINT fk_accessos_lector
        FOREIGN KEY (id_lector) REFERENCES lectors (id_lector)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_accessos_vehicle
        FOREIGN KEY (id_vehicle) REFERENCES vehicles (id_vehicle)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_accessos_reserva
        FOREIGN KEY (id_reserva) REFERENCES reserves (id_reserva)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_accessos_matricula ON accessos (matricula_llegida, data_hora);
CREATE INDEX idx_accessos_data      ON accessos (data_hora);

-- =====================================================================
--  BLOC 4 · TRIGGERS (regles de negoci garantides per la BD)
-- =====================================================================
DELIMITER $$

-- Les reserves, pagaments, accessos i historial NO es poden esborrar mai.
CREATE TRIGGER trg_reserves_no_delete BEFORE DELETE ON reserves
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Les reserves no es poden eliminar: marca-les com a cancelada.';
END$$

CREATE TRIGGER trg_pagaments_no_delete BEFORE DELETE ON pagaments
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Els pagaments no es poden eliminar.';
END$$

CREATE TRIGGER trg_accessos_no_delete BEFORE DELETE ON accessos
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El registre d''accessos no es pot eliminar.';
END$$

CREATE TRIGGER trg_historial_no_delete BEFORE DELETE ON reserves_historial
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'L''historial de reserves no es pot eliminar.';
END$$

-- Validació en crear una reserva:
--   1) el mateix vehicle no pot tenir dues reserves actives que se solapin;
--   2) la mateixa plaça no pot estar assignada a dues reserves solapades;
--   3) no es poden superar les places reservables operatives del pàrquing.
-- (La web, a més, ho comprova dins d'una transacció amb SELECT ... FOR UPDATE.)
CREATE TRIGGER trg_reserves_bi BEFORE INSERT ON reserves
FOR EACH ROW
BEGIN
    DECLARE v_solapades INT DEFAULT 0;
    DECLARE v_capacitat INT DEFAULT 0;

    IF NEW.estat IN ('pendent', 'confirmada', 'en_curs') THEN
        SELECT COUNT(*) INTO v_solapades FROM reserves r
         WHERE r.id_vehicle = NEW.id_vehicle
           AND r.estat IN ('pendent', 'confirmada', 'en_curs')
           AND r.data_hora_entrada < NEW.data_hora_sortida
           AND r.data_hora_sortida > NEW.data_hora_entrada;
        IF v_solapades > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Aquest vehicle ja té una reserva en aquesta franja horària.';
        END IF;

        IF NEW.id_placa IS NOT NULL THEN
            SELECT COUNT(*) INTO v_solapades FROM reserves r
             WHERE r.id_placa = NEW.id_placa
               AND r.estat IN ('pendent', 'confirmada', 'en_curs')
               AND r.data_hora_entrada < NEW.data_hora_sortida
               AND r.data_hora_sortida > NEW.data_hora_entrada;
            IF v_solapades > 0 THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'La plaça ja està reservada en aquesta franja horària.';
            END IF;
        END IF;

        SELECT COUNT(*) INTO v_capacitat FROM places p
         WHERE p.id_parquing = NEW.id_parquing
           AND p.reservable = 1 AND p.estat = 'operativa';
        SELECT COUNT(*) INTO v_solapades FROM reserves r
         WHERE r.id_parquing = NEW.id_parquing
           AND r.estat IN ('pendent', 'confirmada', 'en_curs')
           AND r.data_hora_entrada < NEW.data_hora_sortida
           AND r.data_hora_sortida > NEW.data_hora_entrada;
        IF v_solapades >= v_capacitat THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'No queden places reservables en aquest pàrquing per a la franja escollida.';
        END IF;
    END IF;
END$$

-- Una reserva cancel·lada, completada o no presentada és definitiva.
CREATE TRIGGER trg_reserves_bu BEFORE UPDATE ON reserves
FOR EACH ROW
BEGIN
    IF OLD.estat IN ('cancelada', 'completada', 'no_presentat') AND NEW.estat <> OLD.estat THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Una reserva finalitzada o cancel·lada no pot canviar d''estat.';
    END IF;
END$$

-- Auditoria automàtica.
CREATE TRIGGER trg_reserves_ai AFTER INSERT ON reserves
FOR EACH ROW
BEGIN
    INSERT INTO reserves_historial (id_reserva, id_usuari, accio, estat_nou, detall)
    VALUES (NEW.id_reserva, @id_usuari_app, 'creacio', NEW.estat,
            CONCAT('Reserva ', NEW.numero_reserva, ' creada. Total: ', NEW.preu_total, ' EUR'));
END$$

CREATE TRIGGER trg_reserves_au AFTER UPDATE ON reserves
FOR EACH ROW
BEGIN
    IF NEW.estat <> OLD.estat THEN
        INSERT INTO reserves_historial (id_reserva, id_usuari, accio, estat_anterior, estat_nou, detall)
        VALUES (NEW.id_reserva, @id_usuari_app, 'canvi_estat', OLD.estat, NEW.estat, NEW.motiu_cancelacio);
    END IF;
    IF NEW.data_hora_entrada <> OLD.data_hora_entrada
       OR NEW.data_hora_sortida <> OLD.data_hora_sortida
       OR NEW.id_vehicle <> OLD.id_vehicle
       OR NOT (NEW.id_placa <=> OLD.id_placa) THEN
        INSERT INTO reserves_historial (id_reserva, id_usuari, accio, detall)
        VALUES (NEW.id_reserva, @id_usuari_app, 'canvi_dades',
                CONCAT('Entrada ', OLD.data_hora_entrada, ' -> ', NEW.data_hora_entrada,
                       ' | Sortida ', OLD.data_hora_sortida, ' -> ', NEW.data_hora_sortida,
                       ' | Vehicle ', OLD.id_vehicle, ' -> ', NEW.id_vehicle,
                       ' | Plaça ', IFNULL(OLD.id_placa, '-'), ' -> ', IFNULL(NEW.id_placa, '-')));
    END IF;
END$$

DELIMITER ;

-- =====================================================================
--  BLOC 5 · VISTES (consultes per al panell d'administració i el lector)
-- =====================================================================

-- Ocupació actual de cada pàrquing (calculada, no guardada).
CREATE VIEW v_disponibilitat_parquings AS
SELECT  p.id_parquing,
        p.nom,
        p.places_totals,
        (SELECT COUNT(*) FROM places pl
          WHERE pl.id_parquing = p.id_parquing
            AND pl.reservable = 1 AND pl.estat = 'operativa')          AS places_reservables,
        (SELECT COUNT(*) FROM reserves r
          WHERE r.id_parquing = p.id_parquing
            AND r.estat IN ('confirmada', 'en_curs')
            AND NOW() BETWEEN r.data_hora_entrada AND r.data_hora_sortida) AS reserves_actives_ara,
        (SELECT COUNT(*) FROM reserves r
          WHERE r.id_parquing = p.id_parquing AND r.estat = 'en_curs')  AS vehicles_dins_amb_reserva
FROM parquings p
WHERE p.actiu = 1;

-- Detall complet de reserves per a la cerca avançada de l'administrador.
CREATE VIEW v_reserves_detall AS
SELECT  r.id_reserva,
        r.numero_reserva,
        r.estat,
        r.data_hora_entrada,
        r.data_hora_sortida,
        r.preu_total,
        c.id_client,
        CONCAT(c.nom, ' ', c.primer_cognom, IFNULL(CONCAT(' ', c.segon_cognom), '')) AS client,
        u.email,
        v.matricula,
        CONCAT(v.marca, ' ', v.model)                                             AS vehicle,
        pk.nom                                                                    AS parquing,
        pl.codi_placa,
        mp.tipus                                                                  AS metode_pagament,
        (SELECT IFNULL(SUM(pg.import), 0) FROM pagaments pg
          WHERE pg.id_reserva = r.id_reserva AND pg.tipus = 'exces'
            AND pg.estat = 'completat')                                           AS total_exces,
        r.creada_a
FROM reserves r
JOIN clients          c  ON c.id_client   = r.id_client
JOIN usuaris          u  ON u.id_usuari   = c.id_usuari
JOIN vehicles         v  ON v.id_vehicle  = r.id_vehicle
JOIN parquings        pk ON pk.id_parquing = r.id_parquing
LEFT JOIN places      pl ON pl.id_placa   = r.id_placa
JOIN metodes_pagament mp ON mp.id_metode  = r.id_metode;

-- Consulta que fa el lector de matrícules: reserves vàlides per entrar ARA
-- (marge de 15 min abans de l'hora d'entrada, configurable).
CREATE VIEW v_reserves_vigents AS
SELECT  r.id_reserva, r.numero_reserva, r.id_parquing, r.id_vehicle,
        v.matricula, r.data_hora_entrada, r.data_hora_sortida, r.estat
FROM reserves r
JOIN vehicles v ON v.id_vehicle = r.id_vehicle
WHERE r.estat IN ('confirmada', 'en_curs')
  AND NOW() >= r.data_hora_entrada - INTERVAL 15 MINUTE
  AND NOW() <= r.data_hora_sortida;

-- =====================================================================
--  BLOC 6 · DADES INICIALS (seed de demostració)
-- =====================================================================

-- Clau de xifrat NOMÉS per a la demo. En producció la clau la posa la
-- web (fitxer de configuració fora del DocumentRoot) i mai es guarda a la BD.
SET @clau_app = 'CANVIAR_AQUESTA_CLAU_EN_PRODUCCIO';

INSERT INTO rols (codi, nom, descripcio) VALUES
('client',   'Client',         'Usuari final: reserva places, gestiona perfil, vehicles i mètodes de pagament.'),
('operador', 'Operador',       'Personal d''un pàrquing: consulta reserves i accessos dels seus pàrquings.'),
('admin',    'Administrador',  'Gestió completa: pàrquings, places, tarifes, reserves i usuaris.');

INSERT INTO configuracio (clau, valor, descripcio) VALUES
('despeses_gestio_pct',  '10',    'Percentatge de despeses de gestió sobre el preu base'),
('nit_inici',            '22:00', 'Inici de la tarifa nocturna'),
('nit_fi',               '06:00', 'Fi de la tarifa nocturna'),
('marge_entrada_min',    '15',    'Minuts d''antelació amb què el lector accepta una reserva'),
('marge_cortesia_min',   '10',    'Minuts de cortesia abans de cobrar excés a la sortida');

-- Tarifes generals segons l'enunciat (id_parquing NULL = totes)
INSERT INTO tarifes (id_parquing, codi, descripcio, preu, vigent_des) VALUES
(NULL, 'hora_1_2',        'Preu 1a i 2a hora d''aparcament (per hora)', 1.90, '2026-09-01'),
(NULL, 'hora_3_endavant', 'Preu 3a hora d''aparcament i següents',      0.20, '2026-09-01'),
(NULL, 'maxim_diari',     'Preu màxim diari (per cada 24 h)',           4.00, '2026-09-01'),
(NULL, 'nocturna',        'Tarifa nocturna 22:00-06:00 (per hora)',     0.60, '2026-09-01');

INSERT INTO condicions (versio, text, vigent_des) VALUES
('1.0', 'Condicions generals de reserva de Tarraco Parkings: la reserva garanteix una plaça durant la franja contractada; '
        'l''excés de temps es cobrarà automàticament amb el mateix mitjà de pagament; les cancel·lacions '
        'es poden fer fins a l''hora d''entrada.', '2026-09-01');

-- Pàrquings municipals de Tarragona
INSERT INTO parquings (nom, adreca, codi_postal, latitud, longitud, telefon, obert_24h, hora_obertura, hora_tancament, places_totals) VALUES
('Aparcament Plaça de la Font', 'Carreró de la Palma',            '43003', 41.116981, 1.255289, '977000101', 1, NULL, NULL, 320),
('Aparcament Saavedra',         'Carrer de Josepa Massanés',      '43002', 41.118609, 1.253809, '977000102', 1, NULL, NULL, 140),
('Aparcament la Pedrera',       'Carrer Pons d''Icart',           '43004', 41.113207, 1.253097, '977000103', 0, '07:00', '23:00', 410),
('Aparcament Tabacalera',       'Passeig de la Independència',    '43005', 41.114011, 1.238065, '977000104', 1, NULL, NULL, 260),
('Aparcament Torroja',          'Passeig de Torroja',             '43003', 41.120774, 1.262104, '977000105', 0, '06:00', '00:00', 190);

-- Places reservables: 20 per pàrquing (P<id>-<planta><nn>), generades amb
-- una taula de números. Algunes són PMR / elèctriques.
INSERT INTO places (id_parquing, codi_placa, planta, tipus, reservable)
SELECT  pk.id_parquing,
        CONCAT('P', pk.id_parquing, '-', IF(n.num <= 10, 'A', 'B'), LPAD(n.num, 2, '0')),
        IF(n.num <= 10, -1, -2),
        CASE WHEN n.num IN (1, 2) THEN 'mobilitat_reduida'
             WHEN n.num IN (3, 4) THEN 'electric'
             ELSE 'general' END,
        1
FROM parquings pk
CROSS JOIN (SELECT d1.d * 10 + d0.d + 1 AS num
              FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d0
        CROSS JOIN (SELECT 0 d UNION ALL SELECT 1) d1) n;

UPDATE places SET estat = 'fora_servei' WHERE codi_placa = 'P1-B20';

-- Lectors d'entrada i sortida de cada pàrquing
INSERT INTO lectors (id_parquing, codi, tipus, adreca_ip)
SELECT id_parquing, CONCAT('P', id_parquing, '-ENT-1'), 'entrada', CONCAT('10.0.', id_parquing, '.11') FROM parquings;
INSERT INTO lectors (id_parquing, codi, tipus, adreca_ip)
SELECT id_parquing, CONCAT('P', id_parquing, '-SOR-1'), 'sortida', CONCAT('10.0.', id_parquing, '.12') FROM parquings;

-- Usuaris de demostració (hash bcrypt reals generats amb password_hash() de PHP)
--   client@demo.cat   / Client1234!
--   client2@demo.cat  / Client1234!
--   operador@demo.cat / Operador1234!
--   admin@demo.cat    / Admin1234!
INSERT INTO usuaris (id_rol, email, password_hash, email_verificat) VALUES
((SELECT id_rol FROM rols WHERE codi = 'client'),   'client@demo.cat',   '$2y$12$0e54DMejl3zHIVSmRuFx5uxA1F5tsQXtQoJzAPMH8.bSApjEPeRQi', 1),
((SELECT id_rol FROM rols WHERE codi = 'client'),   'client2@demo.cat',  '$2y$12$0e54DMejl3zHIVSmRuFx5uxA1F5tsQXtQoJzAPMH8.bSApjEPeRQi', 1),
((SELECT id_rol FROM rols WHERE codi = 'operador'), 'operador@demo.cat', '$2y$12$4KyDJ09zB.Yg2pNUUZ5Ss.1a5VudG.hc8ojhleM2JG19Sko47O3/6', 1),
((SELECT id_rol FROM rols WHERE codi = 'admin'),    'admin@demo.cat',    '$2y$12$jFtyg9.kleEE05mUjrjY8.9A014jPP5MM0xoJjD2NtOolS32Sx6s2', 1);

INSERT INTO operadors_parquings (id_usuari, id_parquing) VALUES (3, 1), (3, 2);

INSERT INTO clients (id_usuari, nom, primer_cognom, segon_cognom, dni_xifrat, dni_hash,
                     telefon, data_naixement, adreca, codi_postal, poblacio, consentiment_rgpd) VALUES
(1, 'Marta', 'Puig',   'Soler', AES_ENCRYPT('12345678Z', @clau_app), SHA2(CONCAT(@clau_app, '12345678Z'), 256),
    '600111222', '1995-04-12', 'Rambla Nova, 10', '43001', 'Tarragona', NOW()),
(2, 'Jordi', 'Ferrer', NULL,    AES_ENCRYPT('87654321X', @clau_app), SHA2(CONCAT(@clau_app, '87654321X'), 256),
    '600333444', '1988-11-30', 'Carrer Major, 5', '43003', 'Tarragona', NOW());

INSERT INTO vehicles (id_client, matricula, marca, model, color, tipus, es_electric) VALUES
(1, '1234BCD', 'Seat',    'Ibiza',  'Blanc', 'turisme', 0),
(1, '5678FGH', 'Tesla',   'Model 3','Negre', 'turisme', 1),
(2, '9012JKL', 'Renault', 'Kangoo', 'Gris',  'furgoneta', 0);

INSERT INTO metodes_pagament (id_client, tipus, titular, marca_targeta, ultims_digits,
                              caducitat_mes, caducitat_any, token_passarela, predeterminat) VALUES
(1, 'targeta', 'Marta Puig Soler', 'VISA',       '4242', 8, 2029, 'tok_demo_visa_4242', 1),
(2, 'targeta', 'Jordi Ferrer',     'Mastercard', '5454', 3, 2028, 'tok_demo_mc_5454',   1);
INSERT INTO metodes_pagament (id_client, tipus, titular, email_compte, token_passarela) VALUES
(1, 'paypal', 'Marta Puig Soler', 'marta.puig@demo.cat', 'tok_demo_paypal_marta');

-- Reserves de mostra (preus calculats amb l'algorisme de 01-esquema-funcional.md §6)
--  R1: confirmada, futura, 3 h diürnes              → 4,00 + 0,40 = 4,40 €
--  R2: completada, 1 h reservada, va sortir tard    → 1,90 + 0,19 = 2,09 € (+ excés)
--  R3: cancel·lada pel client
--  R4: confirmada nocturna que passa de dia (22:30 → 00:30) → 1,20 + 0,12 = 1,32 €
INSERT INTO reserves (numero_reserva, id_client, id_vehicle, id_parquing, id_placa, id_metode, id_condicions,
                      data_hora_entrada, data_hora_sortida, preu_base, despeses_gestio, preu_total,
                      estat, condicions_acceptades_a) VALUES
('TPK-20261120-000001', 1, 1, 1, NULL, 1, 1, '2026-11-20 08:00:00', '2026-11-20 11:00:00', 4.00, 0.40, 4.40, 'confirmada', '2026-11-13 10:00:00'),
('TPK-20261110-000002', 2, 3, 2, NULL, 2, 1, '2026-11-10 09:00:00', '2026-11-10 10:00:00', 1.90, 0.19, 2.09, 'confirmada', '2026-11-09 18:30:00'),
('TPK-20261118-000003', 1, 2, 4, NULL, 3, 1, '2026-11-18 16:00:00', '2026-11-18 18:00:00', 3.80, 0.38, 4.18, 'pendent',    '2026-11-12 12:00:00'),
('TPK-20261121-000004', 2, 3, 1, NULL, 2, 1, '2026-11-21 22:30:00', '2026-11-22 00:30:00', 1.20, 0.12, 1.32, 'confirmada', '2026-11-13 09:15:00');

INSERT INTO pagaments (id_reserva, id_metode, tipus, import, estat, referencia_passarela, data_pagament) VALUES
(1, 1, 'reserva', 4.40, 'completat', 'ch_demo_0001', '2026-11-13 10:00:30'),
(2, 2, 'reserva', 2.09, 'completat', 'ch_demo_0002', '2026-11-09 18:30:30'),
(4, 2, 'reserva', 1.32, 'completat', 'ch_demo_0004', '2026-11-13 09:15:30');

-- R2: entra a les 08:58 i surt a les 11:20 (reserva fins a les 10:00).
--     Preu real 09:00→11:20 = 3 h iniciades = 1,90+1,90+0,20 = 4,00 €.
--     Excés = 4,00 − 1,90 = 2,10 € + 10 % = 2,31 € (cobrat amb la mateixa targeta).
INSERT INTO accessos (id_lector, matricula_llegida, id_vehicle, id_reserva, data_hora, barrera_oberta, motiu, minuts_exces) VALUES
((SELECT id_lector FROM lectors WHERE codi = 'P2-ENT-1'), '9012JKL', 3, 2, '2026-11-10 08:58:00', 1, 'reserva_valida', NULL),
((SELECT id_lector FROM lectors WHERE codi = 'P2-SOR-1'), '9012JKL', 3, 2, '2026-11-10 11:20:00', 1, 'sortida_amb_exces', 80),
((SELECT id_lector FROM lectors WHERE codi = 'P1-ENT-1'), '0000XYZ', NULL, NULL, '2026-11-10 12:00:00', 1, 'places_lliures', NULL);

INSERT INTO pagaments (id_reserva, id_metode, tipus, import, estat, referencia_passarela, data_pagament) VALUES
(2, 2, 'exces', 2.31, 'completat', 'ch_demo_0005', '2026-11-10 11:20:05');

-- Canvis d'estat (queden registrats a reserves_historial pels triggers)
UPDATE reserves SET estat = 'en_curs'    WHERE id_reserva = 2;
UPDATE reserves SET estat = 'completada' WHERE id_reserva = 2;

SET @id_usuari_app = 1;   -- la cancel·la la mateixa clienta
UPDATE reserves SET estat = 'cancelada', motiu_cancelacio = 'Cancel·lada pel client des de la web'
 WHERE id_reserva = 3;
SET @id_usuari_app = NULL;

-- =====================================================================
--  Proves ràpides (descomenta per comprovar les regles):
--  DELETE FROM reserves WHERE id_reserva = 1;           -- ha de fallar
--  SELECT * FROM v_reserves_detall;
--  SELECT * FROM reserves_historial;
--  SELECT * FROM v_disponibilitat_parquings;
--  SELECT nom, CAST(AES_DECRYPT(dni_xifrat, @clau_app) AS CHAR) FROM clients; -- només l'app té la clau
-- =====================================================================
