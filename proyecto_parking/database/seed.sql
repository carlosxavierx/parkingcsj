-- =====================================================================
--  CSJ Parkings · Dades inicials (seed) · Sprint 2
--  Executar DESPRÉS de schema.sql:
--     sudo mariadb < schema.sql && sudo mariadb csj_parkings < seed.sql
--  Requereix MariaDB (fa servir les taules seq_1_to_N del motor SEQUENCE).
-- =====================================================================

USE csj_parkings;

-- ---------------------------------------------------------------------
-- Tarifes generals (id_parquing NULL) segons l'enunciat
-- ---------------------------------------------------------------------
INSERT INTO tarifes (id_parquing, codi, descripcio, valor) VALUES
(NULL, 'preu_hora_inicial',  'Preu de la 1a i 2a hora (€/h)',                  1.90),
(NULL, 'hores_inicials',     'Nombre d''hores amb el preu inicial',             2.00),
(NULL, 'preu_hora_extra',    'Preu a partir de la 3a hora (€/h)',              0.20),
(NULL, 'preu_nocturn',       'Preu de l''hora nocturna (€/h)',                  0.60),
(NULL, 'inici_nocturn',      'Hora d''inici de la tarifa nocturna',             22.00),
(NULL, 'fi_nocturn',         'Hora de fi de la tarifa nocturna',               6.00),
(NULL, 'maxim_diari',        'Preu màxim per dia (€)',                         4.00),
(NULL, 'percentatge_gestio', 'Despeses de gestió (%)',                         10.00);

-- ---------------------------------------------------------------------
-- Pàrquings de Tarragona (coordenades i adreces reals, obtingudes amb Geoapify)
-- PENDENT: verificar nombre de places, horaris i telèfons (tasca T7).
-- ---------------------------------------------------------------------
INSERT INTO parquings (nom, adreca, codi_postal, latitud, longitud, tipus,
                       places_totals, places_reservables, horari, telefon, serveis) VALUES
('Aparcament Plaça de la Font', 'Carreró de la Palma', '43003', 41.1169814, 1.2552893, 'subterrani', 320, 32, '24 h', '+34 977 23 29 85', 'cobert,cctv,pmr,barrera,recarrega,24h'),
('Aparcament Saavedra', 'Carrer de Josepa Massanés', '43002', 41.1186088, 1.2538088, 'superficie', 140, 14, '06:00 - 01:00', NULL, 'cctv,pmr,barrera'),
('Aparcament la Pedrera', 'Carrer Pons d''Icart', '43004', 41.1132065, 1.2530971, 'plantes', 410, 41, '24 h', NULL, 'cobert,cctv,pmr,recarrega,ascensor,barrera'),
('Aparcament Municipal Tabacalera', 'Passeig de la Independència', '43005', 41.1140114, 1.2380653, 'superficie', 260, 26, '24 h', NULL, 'cctv,pmr,barrera,24h'),
('Aparcament Torroja', 'Passeig de Torroja', '43003', 41.1207737, 1.2621042, 'superficie', 190, 19, '06:00 - 24:00', NULL, 'cctv,pmr,barrera'),
('Disuasorio Nou Estadi', 'Camí del Nàstic', '43007', 41.1245038, 1.2730959, 'superficie', 520, 52, '24 h', NULL, 'cctv,pmr'),
('Aparcament Bastos', 'Carrer Cardenal Vidal i Barraquer', '43005', 41.1148116, 1.2414972, 'superficie', 120, 12, '07:00 - 23:00', NULL, 'cctv,moto'),
('Disuasorio Guillem Oliver', 'Carrer de Guillem Oliver', '43005', 41.1271725, 1.2376477, 'superficie', 340, 34, '24 h', NULL, 'cctv,pmr,barrera'),
('Pàrquing Pere Martell 46', 'Carrer Pere Martell', '43005', 41.1136258, 1.243876, 'superficie', 95, 10, '06:00 - 22:00', NULL, 'cctv,pmr,recarrega'),
('URV Campus Catalunya', 'Autovia de la Mediterrània', '43002', 41.1233759, 1.2503356, 'superficie', 160, 16, '07:00 - 22:00', NULL, 'cctv,pmr'),
('Parking Port Tarraco', 'Carrer Dic de Llevant', '43004', 41.1078327, 1.2490012, 'superficie', 230, 23, '24 h', NULL, 'cctv,pmr,recarrega,barrera'),
('Aparcament Joan Serra Vilaró', 'Avinguda dels Països Catalans', '43002', 41.1290729, 1.2432724, 'superficie', 210, 21, '24 h', NULL, 'cctv,moto'),
('Aparcament de l''Aqüeducte', 'Camí del Parc del Pont del Diable', '43130', 41.1469645, 1.240081, 'superficie', 130, 13, '24 h', NULL, 'cctv,recarrega'),
('Aparcament Savinosa', 'Carrer de Tomàs Forteza Segura', '43007', 41.1229468, 1.2822836, 'superficie', 85, 10, '07:00 - 23:00', NULL, 'moto');

-- Tarifa pròpia d'exemple: el disuasori del Nou Estadi és més econòmic
INSERT INTO tarifes (id_parquing, codi, descripcio, valor)
SELECT id_parquing, 'preu_hora_inicial', 'Preu de la 1a i 2a hora (€/h) · disuasori', 1.50
FROM parquings WHERE nom = 'Disuasorio Nou Estadi';

-- ---------------------------------------------------------------------
-- Places reservables: es generen automàticament (R-001, R-002...) fins a
-- places_reservables. Les 2 primeres són PMR i la 3a i 4a de recàrrega
-- elèctrica si el pàrquing té aquests serveis.
-- ---------------------------------------------------------------------
INSERT INTO places (id_parquing, codi_placa, planta, tipus)
SELECT p.id_parquing,
       CONCAT('R-', LPAD(s.seq, 3, '0')),
       IF(p.tipus = 'superficie', NULL, '-1'),
       CASE
           WHEN s.seq <= 2 AND FIND_IN_SET('pmr', p.serveis)                  THEN 'pmr'
           WHEN s.seq BETWEEN 3 AND 4 AND FIND_IN_SET('recarrega', p.serveis) THEN 'electric'
           ELSE 'general'
       END
FROM parquings p
JOIN seq_1_to_100 s ON s.seq <= p.places_reservables
ORDER BY p.id_parquing, s.seq;

-- ---------------------------------------------------------------------
-- Usuaris de demostració (un per rol). Contrasenya de tots: Demo1234!
-- ATENCIÓ: canviar o desactivar abans de posar el sistema en producció.
-- ---------------------------------------------------------------------
INSERT INTO usuaris (nom, primer_cognom, segon_cognom, email, password_hash, rol, id_parquing_assignat) VALUES
('Admin',    'CSJ',         'Parkings', 'admin@csjparkings.cat',
 '$2y$10$/O0pK/d2xqEOVORPEBwOguKdstddyHyieoQVp5pWLPztvQUaz2TSe', 'admin', NULL),
('Operador', 'Plaça',       'Font',     'operador@csjparkings.cat',
 '$2y$10$/O0pK/d2xqEOVORPEBwOguKdstddyHyieoQVp5pWLPztvQUaz2TSe', 'operador',
 (SELECT id_parquing FROM parquings WHERE nom = 'Aparcament Plaça de la Font')),
('Client',   'Demostració', NULL,       'client@csjparkings.cat',
 '$2y$10$/O0pK/d2xqEOVORPEBwOguKdstddyHyieoQVp5pWLPztvQUaz2TSe', 'client', NULL);

SET @client = (SELECT id_usuari FROM usuaris WHERE email = 'client@csjparkings.cat');

INSERT INTO vehicles (id_usuari, matricula, marca, model, color, tipus, electric) VALUES
(@client, '1234ABC', 'Seat',  'Ibiza', 'Blanc', 'cotxe', 0),
(@client, '5678DEF', 'Tesla', 'Model 3', 'Negre', 'cotxe', 1);

INSERT INTO metodes_pagament (id_usuari, tipus, titular, marca_targeta, ultims_digits, caducitat,
                              token_passarella, per_defecte) VALUES
(@client, 'targeta', 'Client Demostració', 'Visa', '4242', '12/28', MD5(UUID()), 1);

-- ---------------------------------------------------------------------
-- Reserva de demostració: demà de 10:00 a 13:00 a la Plaça de la Font
-- Preu: 1,90 + 1,90 + 0,20 = 4,00 € + 10 % (0,40 €) = 4,40 €
-- ---------------------------------------------------------------------
SET @parquing = (SELECT id_parquing FROM parquings WHERE nom = 'Aparcament Plaça de la Font');
SET @dema     = DATE_ADD(CURDATE(), INTERVAL 1 DAY);

INSERT INTO reserves (numero_reserva, id_usuari, id_vehicle, id_parquing, id_placa, id_metode_pagament,
                      entrada_prevista, sortida_prevista, preu_base, despeses_gestio, preu_total,
                      estat, condicions_acceptades, data_acceptacio)
VALUES (CONCAT('CSJ-', DATE_FORMAT(@dema, '%Y%m%d'), '-DEMO01'),
        @client,
        (SELECT id_vehicle FROM vehicles WHERE matricula = '1234ABC'),
        @parquing,
        (SELECT id_placa FROM places WHERE id_parquing = @parquing AND codi_placa = 'R-005'),
        (SELECT id_metode FROM metodes_pagament WHERE id_usuari = @client AND per_defecte = 1),
        TIMESTAMP(@dema, '10:00:00'), TIMESTAMP(@dema, '13:00:00'),
        4.00, 0.40, 4.40,
        'confirmada', 1, NOW());

SET @reserva = LAST_INSERT_ID();

INSERT INTO pagaments (id_reserva, id_metode_pagament, concepte, import, estat, referencia)
SELECT @reserva, id_metode_pagament, 'reserva', preu_total, 'completat', 'SIM-DEMO01'
FROM reserves WHERE id_reserva = @reserva;

INSERT INTO historial_reserves (id_reserva, id_usuari, accio, detall) VALUES
(@reserva, @client, 'creacio', 'Reserva creada i pagada (dades de demostració)');
