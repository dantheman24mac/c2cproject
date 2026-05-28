# C2C Marketplace

Lightweight PHP 8 and MySQL 8 customer-to-customer e-commerce platform using Bootstrap 5.

## Setup

1. Update database credentials in `config.php` if needed.
2. Start a PHP server from this directory: `php -S localhost:8000`.
3. Open `http://localhost:8000/setup.php` once to create the database, tables, default categories, and admin user.
4. Open `http://localhost:8000/index.php`.

Default admin account after setup:

```text
Email: admin@c2c.local
Password: Admin123!
```

## Notes

- Currency is fixed to South African Rand (`R`).
- Guest carts are stored in the PHP session and require login at checkout.
- Checkout simulates EFT and Cash on Collection payments.
- Uploaded product images are stored in `uploads/`.
- Delete or restrict `setup.php` after installation on any shared environment.
