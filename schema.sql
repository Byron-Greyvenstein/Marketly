-- ============================================================
-- schema.sql
-- ------------------------------------------------------------
-- This file defines the database structure for Marketly.
--
-- To use it:
--   1. Open phpMyAdmin
--   2. Click the 'marketplace_db' database in the left sidebar
--   3. Click the 'Import' tab at the top
--   4. Click 'Choose File' and select this schema.sql
--   5. Click 'Import' at the bottom
--
-- This will create three tables: users, products, purchases.
-- ============================================================


-- ------------------------------------------------------------
-- USERS TABLE
-- Stores everyone who registers: buyers, sellers, and admins.
-- ------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,        -- Unique ID, MySQL fills this in automatically
    name            VARCHAR(100) NOT NULL,                  -- Full name
    email           VARCHAR(150) NOT NULL UNIQUE,           -- Email — must be unique (no two accounts can share)
    phone           VARCHAR(20)  NOT NULL,                  -- Cellphone number, stored as text (keeps leading zeros)
    role            VARCHAR(20)  NOT NULL DEFAULT 'buyer',  -- 'buyer', 'seller', or 'admin'
    province        VARCHAR(50)  NOT NULL,
    city            VARCHAR(100) NOT NULL,
    address         VARCHAR(255) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,                  -- The HASHED password (never plain text)
    status          VARCHAR(20)  NOT NULL DEFAULT 'active', -- 'active' or 'blocked' (admin can block users)
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- PRODUCTS TABLE
-- Each row is one item listed for sale.
-- ------------------------------------------------------------
CREATE TABLE products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    seller_id       INT NOT NULL,                           -- Links to users.id — who listed it
    name            VARCHAR(150) NOT NULL,                  -- Item title (e.g. "iPhone 13")
    description     TEXT NOT NULL,                          -- Long description
    price           DECIMAL(10, 2) NOT NULL,                -- Price with 2 decimals, up to 99,999,999.99
    category        VARCHAR(50)  NOT NULL,                  -- 'electronics', 'clothing', etc.
    item_condition  VARCHAR(20)  NOT NULL,                  -- 'new', 'like_new', 'good', 'fair', 'poor'
    location        VARCHAR(150) NOT NULL,                  -- Where the seller is based
    status          VARCHAR(20)  NOT NULL DEFAULT 'active', -- 'active', 'sold', 'removed'

    -- Image filenames. Image 1 is required, 2–4 are optional.
    -- Only the FILENAME is stored here (e.g. 'prod_67_a8f3c2.jpg').
    -- The actual image file lives in the /uploads/ folder.
    image_path_1    VARCHAR(255) NOT NULL,
    image_path_2    VARCHAR(255) DEFAULT NULL,
    image_path_3    VARCHAR(255) DEFAULT NULL,
    image_path_4    VARCHAR(255) DEFAULT NULL,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- This says: seller_id MUST refer to a real user.
    -- If a user is deleted, their products are deleted too (CASCADE).
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- PURCHASES TABLE
-- Each row is one completed checkout/order.
-- ------------------------------------------------------------
CREATE TABLE purchases (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id            INT NOT NULL,                       -- Links to users.id — who bought it
    product_id          INT NOT NULL,                       -- Links to products.id — what they bought
    amount              DECIMAL(10, 2) NOT NULL,            -- Total paid (price + delivery)
    quantity            INT NOT NULL DEFAULT 1,
    status              VARCHAR(20) NOT NULL DEFAULT 'completed',
    delivery_address    VARCHAR(500) NOT NULL,
    delivery_type       VARCHAR(20) NOT NULL DEFAULT 'general', -- 'general' or 'express'
    purchased_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (buyer_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);