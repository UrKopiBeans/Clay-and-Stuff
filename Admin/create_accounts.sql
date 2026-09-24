-- default login accounts — hiwalay ito sa figurify_db.sql (schema lang yun, walang laman)
-- paano gamitin: patakbuhin muna ang figurify_db.sql, tapos i-run ito sa phpMyAdmin (SQL tab, paste, Go)
-- ligtas paulit-ulit patakbuhin (ON DUPLICATE KEY UPDATE na lang, hindi mag-eerror)
--
-- default accounts (password nilang lahat: password):
--   owner@gmail.com    -> admin
--   staff@gmail.com    -> staff
--   customer@gmail.com -> customer

USE figurify_db;

INSERT INTO users (full_name, email, password, role)
VALUES
(
    'Figurify Owner',
    'owner@gmail.com',
    '$2y$10$QBeMig9UVZV8QVCXFddiZuX8adHkbR1rBk.jmNVyWgfp5PQoRZU1S',
    'admin'
),
(
    'Figurify Staff',
    'staff@gmail.com',
    '$2y$10$QBeMig9UVZV8QVCXFddiZuX8adHkbR1rBk.jmNVyWgfp5PQoRZU1S',
    'staff'
),
(
    'Test Customer',
    'customer@gmail.com',
    '$2y$10$QBeMig9UVZV8QVCXFddiZuX8adHkbR1rBk.jmNVyWgfp5PQoRZU1S',
    'customer'
)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password  = VALUES(password),
    role      = VALUES(role);