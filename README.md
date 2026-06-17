# Marketly

A Customer-to-Customer (C2C) e-commerce platform built for South Africans to buy and sell second-hand goods directly with one another.

> **Module:** ITECA3-12 — Web Development and e-Commerce  
> **Institution:** Eduvos  
> **Author:** Byron Greyvenstein

---

## About

Marketly addresses common challenges faced in informal trade across South Africa — security, reliability, and the difficulty of reaching buyers across provinces. It provides a secure web-based marketplace with verified user accounts, integrated delivery options, and a built-in seller rating system to build trust between strangers.

The platform supports three types of users: **buyers**, **sellers**, and **administrators**, each with role-based access controls.

---

## Features

### Customer Features
- User registration with buyer or seller role selection
- Secure login with hashed passwords and session-based authentication
- Browse marketplace with keyword search and category filtering
- Detailed product pages with image galleries (up to 4 images per listing)
- Simulated checkout flow with General and Express delivery options
- Order history accessible via "My Orders"
- 5-star seller rating system with optional comments
- Profile management — view and edit personal details
- Fully responsive across desktop, tablet, and mobile devices

### Seller Features
- List new items for sale with title, description, category, condition, and price
- Upload up to 4 product images per listing
- Track listing status (active / sold / removed)

### Administrator Features
- Admin dashboard with platform statistics
- Full CRUD operations on users
- Block / unblock user accounts
- Delete fraudulent product listings (with automatic image file cleanup)
- Delete purchase records

---

## Technology Stack

### Frontend
- **HTML5** — semantic page structure
- **CSS3** — custom styling with CSS variables, Flexbox, Grid, and media queries
- **JavaScript** — vanilla JS for validation, dynamic filtering, and interactivity

### Backend
- **PHP 8** — server-side logic, sessions, authentication, file handling
- **Apache 2.4** — web server (via XAMPP locally, Linux on hosted production)

### Database
- **MySQL 8** — relational database with four tables linked by foreign keys
- **phpMyAdmin** — database management interface

### Tools
- **XAMPP** — local development environment
- **VS Code** — code editor
- **InfinityFree** — live hosting provider

---

## Security

Marketly implements defence-in-depth across multiple layers:

- **Password hashing** via PHP's `password_hash()` — passwords are never stored in plain text
- **Prepared statements** on every database query — prevents SQL injection
- **CSRF tokens** on every form — prevents cross-site request forgery attacks
- **Session-based authentication** — tracks logged-in users securely
- **Input sanitisation** via `htmlspecialchars()` — prevents XSS attacks
- **Role-based access control** — admin-only pages reject non-admin users
- **IDOR protection** — users cannot access other users' orders or ratings by URL manipulation

---

## Database Schema

The database consists of four tables linked through foreign keys with cascading deletions to maintain data integrity:

- `users` — user accounts (buyers, sellers, administrators)
- `products` — product listings (linked to seller via `seller_id`)
- `purchases` — order records (linked to buyer and product)
- `ratings` — seller ratings (one rating per purchase enforced via UNIQUE constraint)

The complete schema is provided in `schema.sql` with the additional ratings table in `addRatingsTable.sql`.

---

## Local Setup

To run Marketly locally:

1. Install [XAMPP](https://www.apachefriends.org/) (Windows) or equivalent (Apache + MySQL + PHP)
2. Clone or download this repository into your XAMPP `htdocs` folder:
```
   C:\xampp\htdocs\marketly\
```
3. Start Apache and MySQL via the XAMPP Control Panel
4. Open phpMyAdmin (`http://localhost/phpmyadmin`) and create a new database called `marketplace_db`
5. Import the schema by uploading `schema.sql` then `addRatingsTable.sql`
6. Open `db_connect.php` and update the credentials:
```php
   $host     = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'marketplace_db';
```
7. Visit `http://localhost/marketly/` in your browser

A default administrator account is created automatically:
- **Email:** `admin@gmail.com`
- **Password:** `123456`

---

## Project Structure

```
marketly/
├── images/                  # Static images (logo)
├── uploads/                 # Product images uploaded by sellers
├── db_connect.php           # Database connection
├── functions.php            # Helper functions (sessions, CSRF, sanitisation)
├── schema.sql               # Database structure
├── addRatingsTable.sql      # Ratings table migration
├── index.php                # Landing page
├── register.php             # User registration
├── login.php                # User login
├── option.php               # Post-login choice page
├── buying.php               # Marketplace browsing
├── viewProduct.php          # Product detail page
├── selling.php              # List a new product
├── buyerInfo.php            # Checkout
├── purchaseSuccess.php      # Order confirmation
├── myOrders.php             # Buyer's order history
├── rateOrder.php            # Submit a seller rating
├── profile.php              # View profile
├── profileEdit.php          # Edit profile
├── adminDash.php            # Admin dashboard
├── adminUserForm.php        # Admin user create/edit
├── adminUserDelete.php      # Admin action endpoint
└── style.css                # All styling
```

---

## Academic Note

This project was developed as a summative assessment for the ITECA3-12 module at Eduvos.
AI was used to assist with comments
