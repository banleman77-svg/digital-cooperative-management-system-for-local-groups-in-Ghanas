# Susu Connect — PHP + MySQL (XAMPP)

A full-stack PHP web application for managing Susu savings groups in Ghana.
Built with PHP, MySQL, Bootstrap 5, and the MTN MoMo Open API.

---

## Setup (5 steps)

### Step 1 — Copy to XAMPP
Copy the `susu_php` folder into your XAMPP `htdocs` directory:
- **Windows:** `C:\xampp\htdocs\susu_php\`
- **Linux/Mac:** `/opt/lampp/htdocs/susu_php/`

### Step 2 — Start XAMPP
Open XAMPP Control Panel and click **Start** next to **Apache** and **MySQL**.

### Step 3 — Create the database
1. Open your browser → go to `http://localhost/phpmyadmin`
2. Click **Import** in the top menu
3. Click **Choose File** → select `susu_php/sql/susu_db.sql`
4. Click **Go** at the bottom

This creates the `susu_db` database with all tables.

### Step 4 — Configure (optional)
Open `config/db.php`. Default settings match a fresh XAMPP install:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'susu_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // change if you set a MySQL password
define('APP_URL',  'http://localhost/susu_php');
```

### Step 5 — Open in browser
Go to: **http://localhost/susu_php**

You'll be redirected to the login page. Click **Create account** to register.

---

## Application Links

| Page | URL |
|---|---|
| Home | `http://localhost/susu_php` |
| Login | `http://localhost/susu_php/auth/login.php` |
| Sign Up | `http://localhost/susu_php/auth/signup.php` |
| Dashboard | `http://localhost/susu_php/dashboard/` |
| Groups | `http://localhost/susu_php/groups/` |
| USSD Simulator | `http://localhost/susu_php/ussd/simulator.php` |

---

## Features

- Phone-based sign up and login (Ghana phone numbers: 0244..., 0201..., etc.)
- Create Susu groups with name, location, contribution amount, and frequency
- Add members — auto-creates accounts for unregistered phones
- Start cycles — automatically schedules all rounds in rotation order
- Record contributions via MTN MoMo or cash
- Automatic round closing when all members have contributed
- Payout disbursement via MTN MoMo Disbursements API
- USSD simulator — shows exact feature-phone experience
- Full audit log of every MoMo API call

---

## MTN MoMo Sandbox Setup

1. Sign up at https://momodeveloper.mtn.com
2. Subscribe to **Collections** and **Disbursements** products
3. Copy the subscription keys into `config/db.php`
4. Create API users using the provisioning utility (see below)

To provision credentials, create `setup/provision.php`:
```php
<?php
require '../config/db.php';
require '../momo/MomoService.php';
$c = new MomoCollectionService();
$userId = $c->createApiUser();
$apiKey = $c->generateApiKey($userId);
echo "MOMO_COLLECTIONS_API_USER_ID=$userId\nMOMO_COLLECTIONS_API_KEY=$apiKey\n";
```

---

## Project Structure

```
susu_php/
├── index.php              → redirects to dashboard or login
├── config/db.php          → database config, helpers, constants
├── sql/susu_db.sql        → database schema (import in phpMyAdmin)
├── includes/
│   ├── header.php         → HTML head + sidebar + topbar
│   ├── footer.php         → closing HTML + Bootstrap JS
│   └── cycle_engine.php   → rotation engine (start cycle, confirm contrib, payout)
├── auth/
│   ├── login.php
│   ├── signup.php
│   └── logout.php
├── dashboard/index.php    → main dashboard with stats
├── groups/
│   ├── index.php          → group list
│   ├── create.php         → create group form
│   ├── detail.php         → group detail + rotation + rounds
│   └── add_member.php     → add member by phone
├── rounds/detail.php      → round contributions + payout controls
├── momo/
│   ├── MomoService.php    → MTN MoMo API client (Collections + Disbursements)
│   └── contribute.php     → initiate MoMo request-to-pay
├── ussd/
│   ├── endpoint.php       → Africa's Talking-compatible USSD handler
│   └── simulator.php      → browser-based USSD simulator (phone UI)
└── assets/
    ├── css/style.css      → professional UI styles
    └── js/app.js          → Bootstrap helpers
```

---

## Defense Talking Points

**"Why PHP?"**
"PHP runs natively on XAMPP, which is the standard development stack in Ghanaian universities. It requires no separate runtime configuration, and PHP + MySQL is the most widely deployed web stack in the world — supporting over 75% of websites including WordPress, Facebook (original), and Wikimedia."

**"Why this database design?"**
"The schema separates concerns cleanly: groups hold configuration, memberships capture rotation position, cycles represent a complete rotation, rounds are individual payment periods, and contributions plus payouts provide full auditability. This maps directly to how Susu works in practice."

**"Why USSD?"**
"Mobile money penetration in Ghana is over 60%, but smartphone penetration is lower. Susu's core users — market traders, artisans — often use basic phones. USSD works on any GSM device with no internet. The `/ussd/endpoint.php` is identical to what Africa's Talking calls in production."

**"How does MoMo integration work?"**
"We use the MTN MoMo Open API sandbox. The `MomoCollectionService` class sends a request-to-pay that pushes a PIN prompt to the member's phone. On confirmation, the `MomoDisbursementService` transfers the pooled amount to the recipient's wallet. Every API call is logged in `momo_transactions` for reconciliation."
