# WESTPRIME CONFESSIONS

WESTPRIME CONFESSIONS is a PHP and MySQL confession-sharing site. The frontend is
kept separate from the PHP backend and database setup files.

## Live deployment

- **Live website:** [https://westprimeconfessions.online/](https://westprimeconfessions.online/)
- **Domain registrar:** Hostinger
- **Hosting provider:** Hostinger (PHP website hosting)
- **Application stack:** PHP, MySQL, HTML, CSS, and JavaScript
- **Database:** MySQL
- **HTTPS:** The production SSL certificate was reported active. Confirm HTTPS
  redirection and check for mixed-content warnings before the live evaluation.
- **Source repository:** [github.com/Jandyllee/wpc](https://github.com/Jandyllee/wpc)

Never put database, hosting, email, or demo-account passwords in this README or
in a public repository. Provide evaluator demo access separately and securely.
Use a limited read-only/demo account where the application supports one.

## Project structure

```text
frontend/
  index.html
  confessions.html
  admin.html
  contact.html
  assets/
    css/style.css
    js/main.js
backend/
  api.php
  config/db.php
  database/schema.sql
```

The repository root `index.html` forwards visitors to the frontend. The site
and API are set up for local XAMPP development at `http://localhost/wpc/`.

## Database setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Create a database named `wpc` in phpMyAdmin, select it, then import
   `backend/database/schema.sql`.
   This same SQL file also adds the email column to an existing `users` table,
   enforces one account per email address, adds the anonymous-post setting to
   an existing `confessions` table, and creates the password-reset table, so
   it can be re-imported safely.
3. Open `http://localhost/wpc/` in your browser. Do not open the HTML files
   directly from the filesystem; PHP sessions and API requests require Apache.
4. Configure the SMTP and public application URL environment variables
   described below. On this XAMPP installation, these are set in
   `C:\xampp\apache\conf\extra\httpd-xampp.conf`; restart Apache from the
   XAMPP Control Panel after changing them. Do not put SMTP credentials in
   source control.
5. The default local MySQL connection in `backend/config/db.php` uses
   `127.0.0.1`, username `root`, and a blank password. Change those values if
   your local MySQL setup uses different credentials.

The schema creates the `users` and `confessions` tables but does not seed an
admin account. Create an admin account explicitly using a securely generated
password hash. For the detailed Hostinger admin-account provisioning and
launch checklist, see
[HOSTINGER-ADMIN-SETUP.md](./HOSTINGER-ADMIN-SETUP.md).

The admin dashboard can filter confessions by the last 7 days, last 30 days,
last 3 months, last 6 months, or a custom inclusive date range. The filters run
against the database; the user-facing feed remains unfiltered.

Users can choose to post a confession anonymously. Their username is hidden
from other users, while admins can still see the account name in the moderation
dashboard.

Confession text longer than 30 words is collapsed by default in both the
student feed and admin dashboard, with a control to expand or collapse it.
New confessions are limited to 1,000 characters (spaces included); the composer
shows a live character count inside the text box and the API enforces the same
limit.

New user accounts must include an email address for password recovery. The
same email address cannot be registered to multiple accounts, and email
addresses are stored in lowercase. Registration gives an existing-user error
with a link to the login and password-recovery flows. Before applying the email
uniqueness migration to a database that already has user accounts, check for
duplicate addresses and resolve them without deleting accounts unexpectedly:

```sql
SELECT LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS account_count
FROM users
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1;
```

The forgot-password flow emails a six-digit verification code through SMTP using
PHPMailer in `vendor/`. Codes expire after 10 minutes, are single-use, and
allow up to five attempts, with at least one minute between code requests.
After code verification, the user can set a new password in a modal. No reset
code is exposed in the browser. Configure these values as PHP environment
variables or add them to the private `wpc-private-config.php` file outside
`public_html` before requesting a code:

- `WPC_SMTP_HOST`: your provider's SMTP server
- `WPC_SMTP_PORT`: usually `587` for TLS or `465` for SSL
- `WPC_SMTP_USERNAME` and `WPC_SMTP_PASSWORD`: SMTP credentials or app password
- `WPC_SMTP_FROM_EMAIL`: a sender address authorized by that SMTP account
- `WPC_SMTP_FROM_NAME`: optional sender display name
- `WPC_SMTP_ENCRYPTION`: `tls` (default) or `ssl`

For production, configure these database values as PHP environment variables
or in a private PHP configuration file named `wpc-private-config.php` placed
in the directory immediately above `public_html` (never inside the website's
public directory):

- `WPC_DB_HOST`: Hostinger database host
- `WPC_DB_NAME`: production database name
- `WPC_DB_USER`: restricted production database user
- `WPC_DB_PASSWORD`: that database user's password

The private PHP file must return an associative array with those keys and
quoted string values. Keep it outside the public web root, do not commit or
upload it into `public_html`, and do not share its contents. Include the
following mail settings in the same array when using SMTP:

```php
<?php
return [
    'WPC_DB_HOST' => 'localhost',
    'WPC_DB_NAME' => 'your_full_database_name',
    'WPC_DB_USER' => 'your_full_database_username',
    'WPC_DB_PASSWORD' => 'your_database_password',
    'WPC_SMTP_HOST' => 'your_provider_smtp_hostname',
    'WPC_SMTP_PORT' => '587',
    'WPC_SMTP_USERNAME' => 'your_mailbox_address',
    'WPC_SMTP_PASSWORD' => 'your_mailbox_password',
    'WPC_SMTP_FROM_EMAIL' => 'your_mailbox_address',
    'WPC_SMTP_FROM_NAME' => 'WESTPRIME CONFESSIONS',
    'WPC_SMTP_ENCRYPTION' => 'tls',
];
```

Use the exact SMTP host, port, and encryption listed by the selected mail
provider. Create/verify the sending mailbox first; never use database or
Hostinger account credentials as the mailbox password.

The root `.htaccess` adds security headers and blocks direct web access to
configuration, schema, dependency, and screenshot directories. Keep the
project's `.htaccess` enabled on Apache hosting, and enable HTTPS/force HTTPS
in Hostinger after the certificate is active. The API uses session-bound CSRF
tokens for POST requests. New registrations and password resets require
12–72-character passwords; existing accounts can continue to sign in with
their current passwords.

SMTP credentials are intentionally not included in the repository. If the
SMTP settings are missing or email delivery fails, the API reports an error
and does not expose the verification code. Configure a working SMTP provider
to enable password recovery.

Accounts without a valid email address cannot use password recovery. Add an
email address to older accounts before relying on this feature.

## Contact form

The public contact page is linked from the site footers. It validates messages
and sends them through the configured PHPMailer SMTP connection. Contact
messages are delivered to `WPC_SMTP_USERNAME`; replies go to the visitor's
validated email address. Keep SMTP credentials outside source control and
configure the same SMTP values in the private PHP config or Hostinger's PHP
environment before deployment. The form has a hidden bot-trap field and
limits repeat submissions from the same session.

## Capstone project rubric: Website Publishing (Domain Name & Hosting)

- **Course:** Web Development 2
- **Project component:** Capstone Project - Deployment & Publishing
- **Weight:** 25% of the final grade
- **Total:** 100 points

### Instructions to students

The capstone project must be live on the internet using a custom domain name
and web hosting. Submit the live URL, hosting credentials with read-only/demo
access, and a short deployment report. Do not publish passwords in the
repository or report; share access details with the evaluator through a
separate secure channel.

### 1. Domain Name Registration & Configuration - 15 points

| Criterion | Excellent (13-15) | Good (10-12) | Satisfactory (7-9) | Needs Improvement (1-6) | No Credit (0) |
|---|---|---|---|---|---|
| Domain registration | Custom domain registered with a reputable registrar (such as Namecheap, GoDaddy, or Google Domains); valid for at least one year; meaningful, professional name relevant to the project. | Custom domain registered; name is relevant and professional. | Domain registered but name is generic or not fully relevant. | Domain registered but expired/expiring soon or unprofessional. | No custom domain registered; uses a free subdomain only. |
| DNS configuration | A/AAAA, CNAME, and MX records correctly configured; DNS propagation verified; no conflicts. | DNS records correctly configured; minor propagation issues resolved. | DNS configured but with minor errors or delays. | DNS misconfigured; site intermittently reachable. | DNS not configured. |
| SSL/HTTPS | Valid SSL certificate installed; HTTPS enforced; HTTP redirects to HTTPS; no mixed-content warnings. | Valid SSL certificate; HTTPS works; minor mixed-content issues. | SSL installed but not enforced or has warnings. | Self-signed or expired certificate. | No SSL/HTTPS. |

### 2. Hosting Setup & Deployment - 25 points

| Criterion | Excellent (22-25) | Good (17-21) | Satisfactory (12-16) | Needs Improvement (1-11) | No Credit (0) |
|---|---|---|---|---|---|
| Hosting platform | Appropriate hosting chosen (shared, VPS, cloud, or PaaS such as Heroku/Vercel/Netlify/AWS); aligns with project requirements. | Suitable hosting platform chosen. | Hosting platform works but is not ideal for the project. | Hosting platform unstable or inappropriate. | No hosting; site not deployed. |
| Deployment process | Application deployed successfully; all files, dependencies, and assets in place; deployment documented step-by-step. | Deployed successfully; minor missing assets or undocumented steps. | Deployed but with missing files or configuration issues. | Deployment attempted but site is broken or incomplete. | Not deployed. |
| Server configuration | Web server (Apache/Nginx) correctly configured; URL rewriting, permissions, and environment variables set properly. | Server configured correctly; minor issues. | Server configured but with noticeable issues. | Server misconfigured; site errors. | Not configured. |
| Database deployment | Database exported, imported, and connected to the live site; credentials secured; data integrity verified. | Database deployed and connected; minor issues. | Database deployed but with connection or data issues. | Database not deployed or not connected. | No database deployment. |
| Environment variables & security | Sensitive credentials stored in environment variables; `.env` not exposed; debug mode off in production. | Environment variables used; minor security oversights. | Some credentials hardcoded or debug mode left on. | Credentials exposed or major security flaws. | No security measures. |

### 3. Website Functionality & Performance - 20 points

| Criterion | Excellent (18-20) | Good (14-17) | Satisfactory (10-13) | Needs Improvement (1-9) | No Credit (0) |
|---|---|---|---|---|---|
| Core functionality | All features work as intended on the live site (forms, CRUD, authentication, APIs, etc.). | Most features work; minor bugs. | Core features work; some bugs. | Many features broken. | Site non-functional. |
| Responsiveness | Fully responsive on mobile, tablet, and desktop; tested on multiple browsers. | Responsive with minor issues. | Partially responsive. | Poor responsiveness. | Not responsive. |
| Load performance | Pages load in under 3 seconds; assets optimized (images, CSS, JS); caching enabled. | Loads in 3-5 seconds; mostly optimized. | Loads in 5-8 seconds; some optimization. | Slow loading (over 8 seconds); unoptimized. | Site does not load. |
| Error handling | Custom 404/500 pages; no PHP/SQL errors visible; graceful error handling. | Minor errors handled; no major visible errors. | Some visible errors. | Many visible errors. | Site crashes. |

### 4. Documentation & Deployment Report - 15 points

| Criterion | Excellent (13-15) | Good (10-12) | Satisfactory (7-9) | Needs Improvement (1-6) | No Credit (0) |
|---|---|---|---|---|---|
| Deployment report | Complete report: domain registrar, hosting provider, deployment steps, DNS settings, SSL setup, and screenshots. | Report mostly complete; minor missing details. | Report present but incomplete. | Minimal documentation. | No report. |
| Credentials & access | Read-only/demo access provided securely; instructions for evaluator. | Access provided; minor issues. | Access provided but unclear. | Access incomplete. | No access. |
| README/documentation | Professional README with setup, features, tech stack, and live URL. | Good README; minor omissions. | Basic README. | Poor README. | No README. |
| Reflection | Insightful reflection on challenges, lessons learned, and future improvements. | Good reflection. | Basic reflection. | Minimal reflection. | No reflection. |

### 5. Security & Best Practices - 15 points

| Criterion | Excellent (13-15) | Good (10-12) | Satisfactory (7-9) | Needs Improvement (1-6) | No Credit (0) |
|---|---|---|---|---|---|
| HTTPS/SSL | Enforced HTTPS; valid certificate; no mixed content. | HTTPS working; minor issues. | HTTPS present but not enforced. | Invalid/expired certificate. | No HTTPS. |
| Input validation & sanitization | All inputs validated/sanitized server-side; prepared statements used. | Most inputs validated. | Some validation. | Little validation. | No validation. |
| Authentication security | Passwords hashed (bcrypt/argon2); sessions secure; CSRF protection. | Secure auth; minor issues. | Basic auth security. | Weak auth security. | No security. |
| Security headers | HSTS, CSP, X-Frame-Options, etc. configured. | Some headers configured. | Minimal headers. | No headers. | No security headers. |
| Error disclosure | No sensitive errors displayed; debug off; logs secured. | Minor disclosures. | Some errors visible. | Many errors visible. | Sensitive data exposed. |

### 6. Presentation & Live Demonstration - 10 points

| Criterion | Excellent (9-10) | Good (7-8) | Satisfactory (5-6) | Needs Improvement (1-4) | No Credit (0) |
|---|---|---|---|---|---|
| Live demo | Site demonstrated live; all features shown; no technical issues. | Demo successful; minor issues. | Demo mostly successful. | Demo with major issues. | No demo. |
| Explanation | Student clearly explains deployment process, challenges, and solutions. | Good explanation. | Basic explanation. | Poor explanation. | Cannot explain. |
| Professionalism | Professional presentation; within time limit; engages audience. | Good presentation. | Acceptable. | Poor presentation. | Unprofessional. |
| Q&A | Answers questions confidently and accurately. | Answers most questions. | Answers some questions. | Struggles with questions. | Cannot answer. |

### Summary of weights

| Component | Points |
|---|---:|
| Domain Name Registration & Configuration | 15 |
| Hosting Setup & Deployment | 25 |
| Website Functionality & Performance | 20 |
| Documentation & Deployment Report | 15 |
| Security & Best Practices | 15 |
| Presentation & Live Demonstration | 10 |
| **Total** | **100** |

### Required submissions

1. **Live URL:** [https://westprimeconfessions.online/](https://westprimeconfessions.online/)
2. **Domain registrar and hosting provider details:** Hostinger is currently
   used for both the domain and hosting.
3. **Deployment report (PDF):** Include deployment screenshots, deployment
   steps, DNS settings, SSL setup, database deployment, and challenges/solutions.
4. **`README.md`:** Include in the project repository.
5. **Read-only/demo credentials:** Provide evaluator access securely and
   separately; never commit credentials to Git or include passwords in this file.
6. **Git repository link:** [github.com/Jandyllee/wpc](https://github.com/Jandyllee/wpc)

### Grading scale (Capstone Component)

| Grade | Percentage | Description |
|---|---:|---|
| A | 93-100% | Excellent |
| A- | 90-92% | Very Good |
| B+ | 87-89% | Good |
| B | 83-86% | Satisfactory |
| B- | 80-82% | Acceptable |
| C+ | 77-79% | Fair |
| C | 73-76% | Passing |
| C- | 70-72% | Minimum Passing |
| D | 60-69% | Below Average |
| F | Below 60% | Fail |

A minimum grade of C is required in all major courses to progress in the program.

### Penalties

- **Late submission:** 10% deduction per day, up to a maximum of 3 days.
- **No live deployment:** The capstone automatically receives a failing grade
  for the deployment component.
- **Plagiarized/AI-generated code:** Must be cited; the student must explain all
  code. Violations follow the university's academic integrity policy.
- **Broken live site at evaluation time:** 20-point deduction unless a
  documented emergency applies.

- **Prepared by:** Course Instructor
- **Date:** ____________________
