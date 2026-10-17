# WESTPRIME CONFESSIONS

WESTPRIME CONFESSIONS is a responsive confession-sharing website. Students can
register, sign in, browse and search posts, and publish with an optional
anonymous display name. An administrator can review, filter, and delete posts.
The application has a static HTML frontend that talks to a PHP JSON API; it
does not use a JavaScript framework or a frontend build step.

## Live deployment and repository

- **Website:** [https://westprimeconfessions.online/](https://westprimeconfessions.online/)
- **Domain and hosting provider:** Hostinger (as reported by the project owner)
- **Source repository:** [github.com/Jandyllee/wpc](https://github.com/Jandyllee/wpc)
- **Deployment guide:** [HOSTINGER.md](./HOSTINGER.md)
- **Admin provisioning guide:** [HOSTINGER-ADMIN-SETUP.md](./HOSTINGER-ADMIN-SETUP.md)

The live URL and provider details are project deployment information, not a
claim that every production health, DNS, email, or performance check has
recently passed. See the deployment guide for recorded and still-pending checks.

## Technology stack

| Layer | Technologies used |
|---|---|
| Frontend markup | HTML5 |
| Styling | Custom responsive CSS; no CSS framework is used. |
| Browser logic | Vanilla JavaScript using `fetch`, `URLSearchParams`, and the browser DOM; no frontend package manager or build/bundling step is configured. |
| PHP application | PHP JSON API in `backend/api.php`; PHP sessions handle login state. Database access uses the `mysqli` extension and prepared statements. |
| Database | MySQL-compatible database (MySQL/MariaDB on XAMPP/Hostinger), InnoDB tables, and `utf8mb4` character encoding/collation. |
| Email | PHPMailer 6.9.1, bundled in `vendor/PHPMailer`; SMTP settings are supplied by server environment or private configuration. |
| UI helpers loaded from CDNs | SweetAlert2 11, Font Awesome 6.4.0, and Google Fonts Poppins. A network connection is required for these externally hosted assets. |
| Local environment | XAMPP on Windows (the local PHP CLI used during development was PHP 8.2.12). |
| Production hosting | Hostinger PHP hosting with Apache `.htaccess` rules. Confirm that the selected plan supports the required PHP extensions and Apache directives. |

The application does not use Laravel, React, Vue, Bootstrap, Tailwind, npm, or
Composer as a project build/install workflow. PHPMailer is included directly
and loaded by PHP; Composer is not needed to run the current application.

## Features

- Student registration and login. User passwords are stored as hashes using
  PHP's `password_hash()` and checked with `password_verify()`.
- Required, lowercased email addresses with a unique database index. Duplicate
  registration attempts return an existing-account error.
- Session-based authentication for students and an administrator. The current
  admin login expects the username `admin`; see the admin setup guide.
- Confession feed, text/tag search, optional anonymous display, and admin view
  of the original account name for anonymous posts.
- Confessions are limited to 1,000 Unicode characters by both the browser
  composer and API. The live counter includes spaces; server-side validation
  is authoritative.
- Posts longer than 30 words are collapsed in the student and admin feeds and
  can be expanded.
- Admin moderation: delete posts and filter by all posts, recent time periods,
  or a custom inclusive date range.
- Email password recovery with a six-digit code, ten-minute expiry, single use,
  a five-attempt limit, and a one-minute interval between requests for the same
  account. SMTP must be configured and tested on the target host for delivery
  to work.
- Contact form with server-side validation, a honeypot field, per-session
  repeat-submission delay, and SMTP delivery to the configured mailbox.
- POST API requests require a session-bound CSRF token. Session cookies are
  configured as HTTP-only and SameSite=Lax; Secure is enabled when the request
  is recognized as HTTPS.

The publish form also ignores repeated submit events while its current request
is pending and disables its button. This is a browser-side duplicate-click
guard, not a server-wide idempotency or anti-spam guarantee.

## Project layout

```text
.
├── index.html                   # Root redirect to the frontend
├── .htaccess                    # Apache access restrictions and headers
├── frontend/
│   ├── index.html               # Student login and registration
│   ├── confessions.html         # Authenticated student feed and composer
│   ├── admin.html               # Admin login and moderation dashboard
│   ├── contact.html             # Contact form
│   ├── forgot_password.html     # Password recovery entry point
│   └── assets/
│       ├── css/style.css        # Responsive application styles
│       └── js/main.js           # Frontend behavior and API client
├── backend/
│   ├── api.php                  # JSON API actions
│   ├── config/db.php            # Database and private-config loader
│   └── database/schema.sql      # Tables and schema migrations
├── vendor/PHPMailer/            # Bundled mail library (required for SMTP)
├── screenshot/                  # Redacted Hostinger documentation images
├── HOSTINGER.md                 # Deployment guide and status log
├── HOSTINGER-ADMIN-SETUP.md     # Safe admin provisioning guide
└── README.md
```

## Requirements

- Apache with PHP and `.htaccess` support for local XAMPP use or deployment.
- PHP with `mysqli`, `mbstring`, sessions, and standard password/security
  functions enabled. PHP's `mysqli` must support `get_result()` (commonly
  provided by mysqlnd).
- MySQL or compatible MariaDB with InnoDB and `utf8mb4` support.
- A browser with JavaScript enabled.
- SMTP credentials and network access to the mail provider only if contact
  delivery and password-recovery email are to be used.

The project was developed locally on XAMPP with PHP 8.2.12. No formal minimum
PHP or MySQL version is declared by this project; use a currently supported
host version that provides the required extensions and test it before launch.

## Local setup with XAMPP

1. Place the project folder at `C:\xampp\htdocs\wpc`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin, create a database named `wpc`, select it, and import
   `backend/database/schema.sql`.
4. The local defaults in `backend/config/db.php` are host `127.0.0.1`,
   username `root`, blank password, and database `wpc`. Override them with
   `WPC_DB_HOST`, `WPC_DB_USER`, `WPC_DB_PASSWORD`, and `WPC_DB_NAME` if the
   local database uses different settings.
5. Visit [http://localhost/wpc/](http://localhost/wpc/). Do not open HTML files
   directly from disk: the application needs Apache to serve the PHP API and
   maintain PHP sessions.
6. SMTP is optional for other local development. To exercise contact delivery
    or password recovery locally, configure the `WPC_SMTP_*` settings in the
    Apache environment used by XAMPP. The private PHP config is loaded only for
    non-local web hosts. Never put real credentials in a tracked file.

The checked-in SQL creates or updates the `users`, `confessions`, and
`password_resets` structures; it also normalizes existing emails and adds
unique constraints/migration columns when absent. **Before running it against
an existing database**, back up the database and check for duplicate normalized
emails using the query below. The unique-index migration will fail if duplicate
non-NULL email values remain; resolve them deliberately rather than deleting
accounts unexpectedly.

```sql
SELECT LOWER(TRIM(email)) AS normalized_email, COUNT(*) AS account_count
FROM users
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1;
```

The schema intentionally does not seed an administrator account. Provision one
explicitly with a locally generated password hash by following
[HOSTINGER-ADMIN-SETUP.md](./HOSTINGER-ADMIN-SETUP.md).

## Configuration

The database loader first checks process environment variables. On a named
production host it can also load `wpc-private-config.php` from the directory immediately above the web document
root (for example, beside `public_html`).
Production database settings are required; they are not embedded in this
repository.

Supported settings:

| Setting | Required | Purpose |
|---|---:|---|
| `WPC_DB_HOST` | Yes | MySQL/MariaDB host |
| `WPC_DB_NAME` | Yes | Application database |
| `WPC_DB_USER` | Yes | Database account |
| `WPC_DB_PASSWORD` | Yes | Database account password |
| `WPC_SMTP_HOST` | For email | SMTP server hostname |
| `WPC_SMTP_PORT` | For email | SMTP port; defaults to `587` |
| `WPC_SMTP_USERNAME` | For email | SMTP login and contact recipient |
| `WPC_SMTP_PASSWORD` | For email | SMTP password/app password |
| `WPC_SMTP_FROM_EMAIL` | For email | Authorized sender address |
| `WPC_SMTP_FROM_NAME` | Optional | Sender display name; defaults to `WESTPRIME CONFESSIONS` |
| `WPC_SMTP_ENCRYPTION` | For email | `tls` or `ssl`; defaults to `tls` |

When using SMTP, use the exact host, port, sender, and encryption method
provided by the mail provider. Store these values in the host's environment or
private PHP config. Do not use database/hosting credentials as a mailbox
password. No public application URL setting is currently read by the code.

Example format with placeholders only—replace these locally, and never commit
the real values:

```php
<?php
return [
    'WPC_DB_HOST' => 'your_database_host',
    'WPC_DB_NAME' => 'your_database_name',
    'WPC_DB_USER' => 'your_database_username',
    'WPC_DB_PASSWORD' => 'your_database_password',
    'WPC_SMTP_HOST' => 'your_smtp_host',
    'WPC_SMTP_PORT' => '587',
    'WPC_SMTP_USERNAME' => 'your_mailbox_username',
    'WPC_SMTP_PASSWORD' => 'your_mailbox_password',
    'WPC_SMTP_FROM_EMAIL' => 'your_verified_sender_address',
    'WPC_SMTP_FROM_NAME' => 'WESTPRIME CONFESSIONS',
    'WPC_SMTP_ENCRYPTION' => 'tls',
];
```

Keep `wpc-private-config.php` outside `public_html`. The repository `.gitignore`
excludes that filename, environment files, and deployment ZIPs, but still
review `git status` before committing. If SMTP settings are missing or invalid,
email endpoints return an error; password recovery does not reveal the code in
the browser. Existing accounts without a valid email address need their email
updated by an administrator before recovery can be used.

## Security and deployment notes

- API write actions require POST and a matching session CSRF token.
- Database writes use prepared statements; form values are validated by the
  server. Rendered user-controlled text is escaped by the frontend.
- The registration interface requests 12-72-character passwords; the API
  enforces a length of 12-72 bytes. Existing accounts can still log in with
  their existing password.
- `.htaccess` blocks direct access to selected sensitive directories and
  document/config/archive extensions and sets headers including CSP,
  X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
  Permissions-Policy, and conditional HSTS. These Apache rules must be
  supported and verified on the production host.
- Enable/verify HTTPS in Hostinger. Do not assume SSL status alone proves
  HTTP-to-HTTPS redirection or absence of mixed-content warnings.
- Errors returned to API clients are generic; server-side errors are written
  to the PHP error log. Protect those logs and do not publish them without
  redacting private details.
- The project does not provide a separate read-only student role. Evaluator
  access should use an approved demo student account and be shared securely;
  the student role can publish posts.

## Verification

There is no automated test runner or build command configured in this
repository. For changes, use PHP lint on changed PHP files and perform focused
browser/API checks. Before a capstone demo, verify login/logout, registration,
feed and post behavior, anonymous display, admin filters/deletion, password
recovery and contact email if configured, responsive layouts, and HTTPS on the
live site. Record results rather than claiming tests that were not performed.

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
