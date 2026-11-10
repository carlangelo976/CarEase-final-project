CarEase Rentals - Render backend fix (November 2026)

Confirmed error from Render logs:
  Session security requires an encryption key (scheme/libraries/Session.php:167)

Changes:
- app/config/config.php reads SESSION_HMAC_SECRET first, falling back to APP_KEY.
- scheme/kernel/Routine.php logs exceptions and returns HTTP 500 JSON in production.
- Dockerfile makes runtime/session writable by the Apache user.
- Public debug endpoints removed from this package.
- Existing Vue source, backend controllers/models/routes and database logic retained.

IMPORTANT: Set the following in Render > Environment (never commit secrets):
  APP_ENV=production
  APP_KEY=<secure random hex string>
  SESSION_HMAC_SECRET=<secure random hex string>
  DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD (your existing Aiven settings)
  JWT_SECRET, REFRESH_TOKEN_KEY (your existing application settings)

Generate each secret in PowerShell with:
  php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

After deployment, test:
  https://dela-cruz-carl-angelo-lavalust-1.onrender.com/carease/api/session
Expected: JSON with user and csrf. If HTTP 500, read Render Logs for
[CarEase PHP Exception] and address the next reported issue.

For deployment from your existing Git repository, copy changed files into
its working folder, then run:
  php -l app/config/config.php
  php -l scheme/kernel/Routine.php
  php -l scheme/kernel/LavaLust.php
  git add app/config/config.php scheme/kernel/Routine.php Dockerfile
  git commit -m "Fix Render session secret and production logging"
  git push origin main

The ZIP is NOT deployed automatically. It does NOT contain your .env,
Git history, saved sessions, user-uploaded IDs, or other private files.
No live end-to-end test against your authenticated Render/Aiven system was run.
