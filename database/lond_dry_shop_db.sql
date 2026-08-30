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

DROP DATABASE IF EXISTS lond_dry_shop_db;
CREATE DATABASE lond_dry_shop_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

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
    customer_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,       -- Unique customer ID
    full_name       VARCHAR(100)        NOT NULL,                  -- Customer's name
    phone_number    VARCHAR(20)         NOT NULL UNIQUE,           -- Unique mobile number used for tracking lookups
    address         TEXT                NULL,                      -- Optional address (for future delivery service)
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
    user_id         INT UNSIGNED        NOT NULL,                  -- FK -> users (staff who encoded the order)
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
    payment_method     ENUM('Cash','Card','Online') NOT NULL,       -- Payment channel used
    reference_number   VARCHAR(50)        NULL DEFAULT NULL,        -- External trace/approval no. for Card/Online (NULL for Cash)
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

-- Customers
-- NOTE: customer_id 3 was originally "Yeshabelle Olango" but she has since
-- been added as a system admin instead (see USERS section above). Since
-- her order LRY-2026-0003 (₱450.00, Released) is protected by
-- ON DELETE RESTRICT on orders.customer_id, her customer record was
-- renamed rather than deleted, so that order's history and receipt stay
-- intact and attributable to a real (walk-in) customer name.
INSERT INTO customers (full_name, phone_number, address) VALUES
('Juan Dela Cruz',   '09171234567', 'Brgy. Banay-banay, City of Cabuyao, Laguna'),
('Maria Santos',     '09281234567', 'Brgy. Mamatid, City of Cabuyao, Laguna'),
('Kristine Bautista','09391234567', 'Brgy. Marinig, City of Cabuyao, Laguna');

-- Orders (covers In Progress, Ready, Released, and Cancelled scenarios)
-- total_amount for each order = sum of its order_items subtotals below
INSERT INTO orders (customer_id, user_id, claim_code, order_status, total_amount, notes, created_at, completed_at, cancelled_at) VALUES
(1, 2, 'LRY-2026-0001', 'In Progress', 242.50, 'Separate whites from colored clothes.',      '2026-08-28 09:15:00', NULL, NULL),
(2, 2, 'LRY-2026-0002', 'Ready',       220.00, NULL,                                          '2026-08-27 13:40:00', NULL, NULL),
(3, 3, 'LRY-2026-0003', 'Released',    450.00, 'Handle with care, delicate fabric.',          '2026-08-25 10:05:00', '2026-08-27 16:20:00', NULL),
(1, 3, 'LRY-2026-0004', 'Cancelled',   382.50, 'Extra starch on collars.',                    '2026-08-26 11:00:00', NULL, '2026-08-26 11:30:00');

-- Order Items (line items per order: per_kg, per_load, per_piece)
INSERT INTO order_items (order_id, service_id, quantity, unit_price_at_time, subtotal) VALUES
-- Order 1: 4.5 kg Wash, Dry, & Fold (202.50) + 2 pieces Standalone Ironing (40.00) = 242.50
(1, 1, 4.50, 45.00, 202.50),
(1, 4, 2.00, 20.00, 40.00),
-- Order 2: 1 load Heavy Items / Comforters = 220.00
(2, 3, 1.00, 220.00, 220.00),
-- Order 3: 1 piece Dry Cleaning = 450.00
(3, 5, 1.00, 450.00, 450.00),
-- Order 4 (Cancelled): 4.5 kg Wash, Dry, & Press bundle = 382.50 (non-refundable per shop policy)
(4, 2, 4.50, 85.00, 382.50);

-- Payments (pay-first policy: payment recorded at drop-off/order creation)
-- amount_change = amount_paid - orders.total_amount (0.00 for exact digital/card payments)
INSERT INTO payments (order_id, payment_method, reference_number, amount_paid, amount_change) VALUES
(1, 'Cash',   NULL,               250.00, 7.50),
(2, 'Online', 'GC-2026-88213421', 220.00, 0.00),
(3, 'Card',   'CRD-773821094',    450.00, 0.00),
(4, 'Cash',   NULL,               382.50, 0.00);
