# PresentBetter — Feature 1: Authentication Foundation

Starter implementation: React + Vite, PHP REST endpoints, MySQL and XAMPP.

## Included
- Presenter self-registration
- Email/password sign-in and sign-out
- Session-backed authentication with HttpOnly cookie
- CSRF token on state-changing requests
- PDO prepared statements and password hashing
- Role field (`presenter`, `consultant`, `admin`)
- Initial responsive presenter/consultant dashboard shell
- Account lookup endpoint

## Prerequisites
- XAMPP with Apache, MySQL and PHP (PHP 8.1+ recommended)
- Node.js LTS and npm
- PHP extensions `pdo_mysql`, `mbstring`
- Modern browser

## 1. Put the project in XAMPP
Extract/copy the `presentbetter` folder to:
`C:\xampp\htdocs\presentbetter`

Ensure these exist:
- `C:\xampp\htdocs\presentbetter\api`
- `C:\xampp\htdocs\presentbetter\database`
- `C:\xampp\htdocs\presentbetter\src`

## 2. Create the database
1. Start Apache and MySQL in XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Choose **Import** and import `database/schema.sql`.
   Or open the SQL tab and run the script.
4. Confirm the `presentbetter` database and `users` table exist.

## 3. Verify PHP configuration
Open `C:\xampp\htdocs\presentbetter\api\config\config.php`.
The starter assumes local XAMPP defaults:
- host: `127.0.0.1`
- database: `presentbetter`
- user: `root`
- password: blank

If your local MySQL password differs, update `DB_PASS`.
Make sure `pdo_mysql` and `mbstring` are enabled in the active `php.ini`, then restart Apache.

## 4. Install and run the React frontend
Open Command Prompt or PowerShell:

```powershell
cd C:\xampp\htdocs\presentbetter
npm install
npm run dev
```

Open **http://localhost:5173**. Use this exact origin while testing; API CORS is restricted to it.

## 5. Smoke test
1. Create a presenter account.
2. Sign in and confirm the dashboard greets you.
3. Refresh the page; session should remain.
4. Sign out; the sign-in screen should return.
5. Try registering the same email again; it should be rejected.

## 6. Create your consultant account (local development)
For now, self-registration only creates presenter accounts. Create a consultant deliberately:
1. Register an account through the app using your consultant email.
2. In phpMyAdmin, select `presentbetter`, open SQL, and run this statement with your exact account email:

```sql
UPDATE users SET role = 'consultant'
WHERE email = 'your-actual-email@example.com';
```

Sign out and sign back in. The dashboard should show the Consultant role.
Do not expose role selection on public registration. For production, provision elevated roles through a controlled admin-only process.

## API endpoints
All endpoints are under `http://localhost/presentbetter/api`.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/auth/csrf.php` | Initialize session and return CSRF token |
| POST | `/auth/register.php` | Create presenter account |
| POST | `/auth/login.php` | Sign in |
| GET | `/auth/me.php` | Return current account |
| POST | `/auth/logout.php` | Sign out |

Mutating endpoints require `X-CSRF-Token`, acquired from `csrf.php`; frontend does this automatically.

## Important local development notes
- Keep frontend origin at `http://localhost:5173` for this configuration.
- Do not mix `localhost` and `127.0.0.1` in the browser; cookie origins differ.
- This configuration sets the session cookie `secure=false` for local HTTP. **Production requires HTTPS, `secure=true`, a strict allowlist of trusted origins, production secrets, backups, monitoring, rate limiting and deployment review.**
- This starter does not yet implement password reset, email verification, login throttling, account recovery, profile editing or full admin tooling. Add these before a public production launch.
- Do not store submitted presentation files in a public web directory. Later uploads will use private storage, validation, size limits, malware scanning and access checks.
- Review applicable privacy/data-protection requirements before handling client submissions.

## Troubleshooting
- **CORS or network error:** check Apache is running and use `http://localhost:5173`; confirm API path and `FRONTEND_ORIGIN`.
- **Database connection failed:** verify MySQL is running and `api/config/config.php` credentials match your XAMPP installation.
- **mb_strlen undefined:** enable `mbstring` in the active PHP configuration and restart Apache.
- **PDO driver missing:** enable `pdo_mysql` and restart Apache.
- **401 after successful login:** use one hostname consistently (`localhost`), enable cookies and reload.
- **403/CORS:** verify origin exactly; do not add wildcard CORS when using credentials.

## Next feature
After authentication is tested, proceed to Feature 2: secure presentation submissions (slides, audio and video), including versioning and submission metadata.
