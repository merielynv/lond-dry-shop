-- =====================================================================
-- LOND DRY SHOP
-- Laundry Shop Management and Tracking System
-- ITP104 - Information Management 2 | Semestral Project
-- =====================================================================
-- This schema is based on the finalized data dictionary (Section 8,
-- Project Outline) and satisfies the Semestral Project Minimum
-- Requirements: relational MySQL DB with PK/FK relationships, no
-- unnecessary data duplication, normalized up to 3NF/4NF, and fields
-- that support authentication, transaction processing, search/
-- filtering, and reporting.
--
-- Engine: InnoDB (required for foreign key enforcement)
-- Charset: utf8mb4 (full Unicode support, e.g. peso sign / names)
-- =====================================================================

-- DROP DATABASE IF EXISTS lond_dry_shop_db;
-- CREATE DATABASE lond_dry_shop_db
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;

USE lond_dry_shop_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. USERS TABLE
-- Internal system accounts: Admin (shop owner) and Staff (cashier).
-- Supports FR: User Authentication & Authorization (Requirement #1).
-- =====================================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,       -- Unique user ID
    full_name       VARCHAR(100)        NOT NULL,                  -- Real name of admin or staff member
    username        VARCHAR(50)         NOT NULL UNIQUE,           -- Unique username for login access
    password_hash   VARCHAR(255)        NOT NULL,                  -- Securely hashed password (never plain text)
    role            ENUM('admin','staff') NOT NULL DEFAULT 'staff',-- Access control flag
    is_active       BOOLEAN             NOT NULL DEFAULT TRUE,     -- Soft-disable an account without deleting it
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP -- When the account was registered
) ENGINE=InnoDB;

-- =====================================================================
-- 2. CUSTOMERS TABLE
-- Directory of clients. Customers do not log in; this is only a
-- record for contact info, history, and public claim-code tracking.
-- =====================================================================
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
    customer_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)         NOT NULL,
    last_name       VARCHAR(50)         NOT NULL,
    middle_initial  VARCHAR(5)          NULL,
    suffix          VARCHAR(10)         NULL,
    full_name       VARCHAR(150)        NOT NULL,                          -- auto-composed from the name parts
    phone_number    VARCHAR(20)         NOT NULL UNIQUE,
    province        VARCHAR(100)        NULL,                              -- Unique mobile number used for tracking lookups
    city            VARCHAR(100)        NULL,
    barangay        VARCHAR(100)        NULL,
    street_address  VARCHAR(150)        NULL,
    address         TEXT                NULL,                              -- auto-composed from the address parts
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP -- When the customer was first recorded
) ENGINE=InnoDB;

-- =====================================================================
-- 3. SERVICES TABLE
-- Admin-managed catalog: standard services, heavy-item loads,
-- dry cleaning, and seasonal promotional bundles.
-- =====================================================================
DROP TABLE IF EXISTS services;
CREATE TABLE services (
    service_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,       -- Unique service ID
    service_name    VARCHAR(100)        NOT NULL,                  -- e.g. "Wash, Dry, & Fold", "Summer Promo Bundle"
    unit_type       ENUM('per_kg','per_piece','per_load') NOT NULL,-- How the service is measured
    current_price   DECIMAL(10,2)       NOT NULL,                  -- Current base price, editable by admin
    category_type   VARCHAR(50)         NOT NULL,                  -- 'Regular','Heavy/Comforter','Specialty/Dry Clean','Promotion'
    is_active       BOOLEAN             NOT NULL DEFAULT TRUE,     -- Soft-delete flag; hides expired/discontinued items from POS
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP -- Last time the admin modified the price/details
) ENGINE=InnoDB;

-- =====================================================================
-- 4. ORDERS TABLE (Transaction Header)
-- Master record of a customer drop-off / laundry job. Tracks the
-- operational lifecycle from drop-off to release or cancellation.
-- =====================================================================
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
    order_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,       -- Unique order ID (internal)
    customer_id     INT UNSIGNED        NOT NULL,                  -- FK -> customers
    user_id         INT UNSIGNED        NOT NULL,                  -- FK -> users (staff who encoded the order; 'staff' role only, never an admin)
    claim_code      VARCHAR(30)         NOT NULL UNIQUE,           -- Customer-facing alpha-numeric tracking code
    order_status    ENUM('Received','In Progress','Ready','Released','Cancelled')
                                        NOT NULL DEFAULT 'Received', -- Current processing state
    total_amount    DECIMAL(10,2)       NOT NULL DEFAULT 0.00,     -- Final computed sum for the transaction
    notes           TEXT                NULL DEFAULT NULL,         -- Optional special instructions (e.g. "separate whites", "extra starch on collars")
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Drop-off / payment timestamp (pay-first rule)
    completed_at    TIMESTAMP           NULL DEFAULT NULL,         -- When the laundry was claimed/picked up (Released)
    cancelled_at    TIMESTAMP           NULL DEFAULT NULL,         -- When the order was cancelled, if applicable

    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 5. ORDER_ITEMS TABLE (Transaction Detail / Line Items)
-- Line items per order: handles kilograms, item counts, or loads,
-- and locks in the price at the time of the transaction.
-- =====================================================================
DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
    order_item_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,   -- Unique order item ID
    order_id             INT UNSIGNED       NOT NULL,               -- FK -> orders (parent order)
    service_id           INT UNSIGNED       NOT NULL,               -- FK -> services (chosen service/bundle)
    quantity              DECIMAL(10,2)      NOT NULL,               -- Kilograms (3.5), item count (5), or loads (2)
    unit_price_at_time   DECIMAL(10,2)      NOT NULL,               -- Price locked in at time of transaction
    subtotal              DECIMAL(10,2)      NOT NULL,               -- quantity * unit_price_at_time

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_service
        FOREIGN KEY (service_id) REFERENCES services(service_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_order_items_qty_positive CHECK (quantity > 0)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. PAYMENTS TABLE (Financial Ledger)
-- Records the payment made under the shop's pay-first policy.
--
-- Note on amount_change: MySQL's GENERATED ALWAYS AS columns can only
-- reference other columns in the SAME row/table. Since the change is
-- amount_paid (this table) minus orders.total_amount (a different
-- table), it cannot be a true generated column. It's stored instead
-- as a plain column that the application sets at the moment of
-- payment (amount_paid - total_amount), so historical receipts stay
-- accurate even if total_amount logic changes later.
-- =====================================================================
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    payment_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,     -- Unique payment ID
    order_id           INT UNSIGNED       NOT NULL,                 -- FK -> orders (order being paid for)
    payment_method     ENUM('Cash', 'Online') NOT NULL,       -- Payment channel used
    reference_number   VARCHAR(50)        NULL DEFAULT NULL,        -- External trace/reference no. for Online payments (NULL for Cash)
    amount_paid        DECIMAL(10,2)      NOT NULL,                 -- Total cash/digital amount tendered by the customer
    amount_change       DECIMAL(10,2)      NOT NULL DEFAULT 0.00,     -- Change given back (amount_paid - orders.total_amount), stored for historical receipt reprints

    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- INDEXES
-- Supports Requirement #5 (Search, Filtering, and Record Retrieval):
-- e.g. staff search orders by claim code / customer name / status,
-- admin filters order history and reports by date.
-- =====================================================================
CREATE INDEX idx_customers_last_name   ON customers(last_name);
CREATE INDEX idx_customers_full_name   ON customers(full_name);
CREATE INDEX idx_orders_claim_code     ON orders(claim_code);
CREATE INDEX idx_orders_status         ON orders(order_status);
CREATE INDEX idx_orders_created_at     ON orders(created_at);
CREATE INDEX idx_orders_customer_id    ON orders(customer_id);
CREATE INDEX idx_services_category     ON services(category_type);
CREATE INDEX idx_payments_method       ON payments(payment_method);

-- =====================================================================
-- SAMPLE / SEED DATA
-- Reflects the pricing decided in the project outline (Section 2).
-- =====================================================================

-- Users: 2 Admin (shop owner + co-admin) + 2 Staff/Cashiers
-- NOTE: password_hash values below are REAL bcrypt hashes (generated with
-- PHP's password_hash(), PASSWORD_DEFAULT) so these accounts work out of
-- the box for development/testing. The plain-text passwords they were
-- generated from are:
--   admin_meri    -> Admin@123
--   staff_leslie  -> Staff@123
--   staff_april   -> Staff@123
--   admin_yesha   -> Admin@123
-- Change these (via tools/generate_hash.php + an UPDATE statement, or once
-- the admin's "Add Staff" feature is built) before using real data.
INSERT INTO users (full_name, username, password_hash, role) VALUES
('Merielyn Navea',  	'admin_meri', '$2y$10$hVHpr1haXWf3doi6/.bDbuS7RrQUL98kZ9OXjW6MEj.4OyPVcx3/S', 'admin'),
('Leslie Mae Dejayco',  'staff_leslie',  '$2y$10$HPHuE..rCCjGTFCqrrrrpu4QYNR2jNJh/LDLxX9WMvs9je/nqnJ2.', 'staff'),
('April Joy Liro',      'staff_april',   '$2y$10$1GnOqkStx753b0xlHhAQIOr2sEgIUPISjrwzY0iGlqqyphryWL5su', 'staff'),
-- Added: Yeshabelle Olango was previously seeded only as a customer
-- (customer_id 3). She is now also a system admin. Her original customer
-- record was kept (renamed below, see CUSTOMERS section) rather than
-- deleted, because orders.customer_id uses ON DELETE RESTRICT -- deleting
-- her would have required deleting order LRY-2026-0003 and its payment,
-- destroying financial history. A person can validly be both a customer
-- and a system user; these are two separate identities in the schema
-- (customers vs. users) on purpose.
('Yeshabelle Olango',   'admin_yesha',   '$2y$10$VWrtIilxYERYmjOa7oZTjO4KaWQW7wbqyEvmbvo/z5lU.CFMkuOqa', 'admin');

-- Services: base catalog and pricing from the project outline
INSERT INTO services (service_name, unit_type, current_price, category_type) VALUES
('Wash, Dry, & Fold (Regular)',      'per_kg',   45.00, 'Regular'),
('Wash, Dry, & Press (Bundle)',      'per_kg',   85.00, 'Regular'),
('Heavy Items / Comforters',         'per_load', 220.00,'Heavy/Comforter'),
('Standalone Ironing (Press Only)',  'per_piece',20.00, 'Regular'),
('Dry Cleaning',                     'per_piece',450.00,'Specialty/Dry Clean');

-- 1. ADD MORE CUSTOMERS SO IT'S REALISTIC
INSERT INTO customers (first_name, last_name, full_name, phone_number, province, city, barangay, address) VALUES
('Juan',     'Dela Cruz', 'Juan Dela Cruz',      '09171234567', 'Laguna', 'City of Cabuyao', 'Banay-banay', 'Brgy. Banay-banay, City of Cabuyao, Laguna'),
('Maria',    'Santos',    'Maria Santos',        '09281234567', 'Laguna', 'City of Cabuyao', 'Mamatid',     'Brgy. Mamatid, City of Cabuyao, Laguna'),
('Kristine', 'Bautista',  'Kristine Bautista',   '09391234567', 'Laguna', 'City of Cabuyao', 'Marinig',     'Brgy. Marinig, City of Cabuyao, Laguna'),
('Roberto',  'Garcia',     'Roberto Garcia',     '09491234567', 'Laguna', 'City of Cabuyao', 'Pulo',        'Brgy. Pulo, City of Cabuyao, Laguna'),
('Angelica', 'Reyes',      'Angelica Reyes',     '09181234567', 'Laguna', 'City of Cabuyao', 'Banlic',      'Brgy. Banlic, City of Cabuyao, Laguna'),
('Michael',  'Villanueva', 'Michael Villanueva', '09291234567', 'Laguna', 'City of Cabuyao', 'Gulod',       'Brgy. Gulod, City of Cabuyao, Laguna'),
('Sarah',    'Aquino',     'Sarah Aquino',       '09381234567', 'Laguna', 'City of Cabuyao', 'San Isidro',  'Brgy. San Isidro, City of Cabuyao, Laguna'),
('Carlo',    'Mendoza',    'Carlo Mendoza',      '09501234567', 'Laguna', 'City of Cabuyao', 'Diezmo',      'Brgy. Diezmo, City of Cabuyao, Laguna'),
('Bea',      'Alonzo',     'Bea Alonzo',         '09191234567', 'Laguna', 'City of Cabuyao', 'Niugan',      'Brgy. Niugan, City of Cabuyao, Laguna'),
('Kevin',    'Santos',     'Kevin Santos',       '09351234567', 'Laguna', 'City of Cabuyao', 'Pittland',    'Brgy. Pittland, City of Cabuyao, Laguna');

-- 2. PROCEDURE TO GENERATE 1-YEAR BACKDATED HISTORY (CASH & ONLINE ONLY)
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_generate_laundry_history $$

CREATE PROCEDURE sp_generate_laundry_history()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE target_records INT DEFAULT 250;
    DECLARE rand_days INT;
    DECLARE rand_hours INT;
    DECLARE rand_minutes INT;
    DECLARE order_datetime DATETIME;
    DECLARE comp_datetime DATETIME;
    DECLARE canc_datetime DATETIME;
    DECLARE v_customer_id INT;
    DECLARE v_user_id INT;
    DECLARE v_staff_count INT DEFAULT 0;
    DECLARE v_status VARCHAR(20);
    DECLARE v_claim_code VARCHAR(30);
    DECLARE v_service_id INT;
    DECLARE v_qty DECIMAL(10,2);
    DECLARE v_price DECIMAL(10,2);
    DECLARE v_subtotal DECIMAL(10,2);
    DECLARE v_order_id INT;
    DECLARE v_pay_method VARCHAR(20);
    DECLARE v_ref_no VARCHAR(50);
    DECLARE v_amount_paid DECIMAL(10,2);
    DECLARE v_amount_change DECIMAL(10,2);

    -- Orders are encoded at the counter, so they may only be assigned to
    -- STAFF accounts. Admins (shop owners) oversee the business and never
    -- encode orders. Stop early if no active staff account exists.
    SELECT COUNT(*) INTO v_staff_count
      FROM users
     WHERE role = 'staff' AND is_active = TRUE;

    IF v_staff_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No active staff account found: add at least one staff user before generating order history.';
    END IF;

    WHILE i <= target_records DO
        -- Random date between 365 days ago and today
        SET rand_days = FLOOR(RAND() * 365);
        SET rand_hours = FLOOR(RAND() * 24);
        SET rand_minutes = FLOOR(RAND() * 60);
        
        -- Subtract random days from the current timestamp (Now)
        SET order_datetime = DATE_SUB(NOW(), INTERVAL rand_days DAY);
        SET order_datetime = DATE_ADD(order_datetime, INTERVAL rand_hours HOUR);
        SET order_datetime = DATE_ADD(order_datetime, INTERVAL rand_minutes MINUTE);

        -- Determine status
        IF rand_days > 7 THEN
            IF RAND() < 0.90 THEN
                SET v_status = 'Released';
                SET comp_datetime = DATE_ADD(order_datetime, INTERVAL FLOOR(1 + RAND() * 3) DAY);
                SET canc_datetime = NULL;
            ELSE
                SET v_status = 'Cancelled';
                SET comp_datetime = NULL;
                SET canc_datetime = DATE_ADD(order_datetime, INTERVAL FLOOR(1 + RAND() * 5) HOUR);
            END IF;
        ELSE
            SET v_status = ELT(1 + FLOOR(RAND() * 5), 'Received', 'In Progress', 'Ready', 'Released', 'Cancelled');
            IF v_status = 'Released' THEN
                SET comp_datetime = DATE_ADD(order_datetime, INTERVAL 1 DAY);
                SET canc_datetime = NULL;
            ELSEIF v_status = 'Cancelled' THEN
                SET comp_datetime = NULL;
                SET canc_datetime = DATE_ADD(order_datetime, INTERVAL 2 HOUR);
            ELSE
                SET comp_datetime = NULL;
                SET canc_datetime = NULL;
            END IF;
        END IF;

        -- Random customer from ID 1 to 10
        SET v_customer_id = 1 + FLOOR(RAND() * 10);
        -- Random ACTIVE STAFF user only (admins are never assigned to orders)
        SELECT user_id INTO v_user_id
          FROM users
         WHERE role = 'staff' AND is_active = TRUE
         ORDER BY RAND()
         LIMIT 1;
        SET v_claim_code = CONCAT('LRY-', YEAR(order_datetime), '-', LPAD(i + 500, 4, '0'));

        -- Random service (1 to 5)
        SET v_service_id = 1 + FLOOR(RAND() * 5);
        
        IF v_service_id = 1 THEN SET v_price = 45.00; SET v_qty = ROUND(2.0 + (RAND() * 5.0), 2);
        ELSEIF v_service_id = 2 THEN SET v_price = 85.00; SET v_qty = ROUND(2.0 + (RAND() * 5.0), 2);
        ELSEIF v_service_id = 3 THEN SET v_price = 220.00; SET v_qty = 1.00;
        ELSEIF v_service_id = 4 THEN SET v_price = 20.00; SET v_qty = FLOOR(1 + RAND() * 4);
        ELSE SET v_price = 450.00; SET v_qty = 1.00;
        END IF;

        SET v_subtotal = ROUND(v_qty * v_price, 2);

        -- Insert Order Header
        INSERT INTO orders (customer_id, user_id, claim_code, order_status, total_amount, created_at, completed_at, cancelled_at)
        VALUES (v_customer_id, v_user_id, v_claim_code, v_status, v_subtotal, order_datetime, comp_datetime, canc_datetime);

        SET v_order_id = LAST_INSERT_ID();

        -- Insert Order Line Item
        INSERT INTO order_items (order_id, service_id, quantity, unit_price_at_time, subtotal)
        VALUES (v_order_id, v_service_id, v_qty, v_price, v_subtotal);

        -- Insert Payment Ledger Entry (Cash or Online only)
        SET v_pay_method = ELT(1 + FLOOR(RAND() * 2), 'Cash', 'Online');
        IF v_pay_method = 'Cash' THEN
            SET v_ref_no = NULL;
            SET v_amount_paid = v_subtotal + ELT(1 + FLOOR(RAND() * 4), 0.00, 10.00, 20.00, 50.00);
            SET v_amount_change = ROUND(v_amount_paid - v_subtotal, 2);
        ELSE
            SET v_ref_no = CONCAT('GC-', FLOOR(100000 + RAND() * 900000));
            SET v_amount_paid = v_subtotal;
            SET v_amount_change = 0.00;
        END IF;

        INSERT INTO payments (order_id, payment_method, reference_number, amount_paid, amount_change)
        VALUES (v_order_id, v_pay_method, v_ref_no, v_amount_paid, v_amount_change);

        SET i = i + 1;
    END WHILE;
END $$

DELIMITER ;

-- Execute the generator
CALL sp_generate_laundry_history();

-- Clean up procedure
DROP PROCEDURE sp_generate_laundry_history;