-- DRESS UP ORDERS — bagong column para ma-save ang Dress Up (Create Style) design ng bawat figure.
-- Paano gamitin: phpMyAdmin -> piliin ang "figurify_db" -> SQL tab -> i-paste ito -> Go.
-- Isang beses lang ito kailangang i-run. (Kasama na rin ito sa figurify_db.sql para sa bagong install.)

USE figurify_db;

ALTER TABLE order_figures
    ADD COLUMN dressup_data LONGTEXT NULL
    COMMENT 'JSON ng Dress Up design: gender, skin, hair, top, bottom, shoes, colors, 3D model paths'
    AFTER quoted_price;
