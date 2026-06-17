-- ============================================================
-- addRatingsTable.sql
-- ------------------------------------------------------------
-- Adds a new "ratings" table to the database for the seller
-- rating system. Buyers can rate the seller of each item they
-- have purchased — once per purchase, 1 to 5 stars, with an
-- optional written comment.
--
-- To use this:
--   1. Open phpMyAdmin
--   2. Click the 'marketplace_db' database in the left sidebar
--   3. Click the 'Import' tab at the top
--   4. Choose this file and click Import
--
-- This is an ADDITIVE migration — it doesn't change any
-- existing tables, just adds a new one.
-- ============================================================

CREATE TABLE ratings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    seller_id    INT NOT NULL,                       -- the seller being rated
    buyer_id     INT NOT NULL,                       -- the buyer giving the rating
    purchase_id  INT NOT NULL,                       -- which purchase this rating is for
    stars        INT NOT NULL,                       -- 1 to 5
    comment      TEXT,                               -- optional written feedback
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys link this row to the relevant users and purchase.
    -- CASCADE means: if a user or purchase is deleted, their ratings
    -- are deleted too (no orphaned rows).
    FOREIGN KEY (seller_id)   REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (buyer_id)    REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,

    -- A buyer can only rate each purchase ONCE. The unique key on
    -- purchase_id enforces this at the database level — even if our
    -- PHP somehow let two ratings through, MySQL would reject the
    -- second one with an error.
    UNIQUE KEY one_rating_per_purchase (purchase_id)
);