CarEase Rentals - Render session / CSRF fix

Changes:
- scheme/libraries/Session.php: do not destroy new sessions when creation-rate threshold is reached; check session_start result.
- app/controllers/CarEaseApi.php: remove temporary login diagnostic logging.
- app/config/config.php: secure and HTTP-only cookies in production/Render.
- .dockerignore: keep local secrets, git data and uploads out of Docker build context.

Tested: PHP syntax lint for all PHP files under app/, scheme/, public/.
Not tested: live Render deployment, Aiven database, PayMongo, Google Maps or Calendar.

Deploy: back up current project, copy these changes into your git working tree, git add and commit, git push origin main. Wait for Render Live, open a NEW InPrivate window and try login. Check /carease/api/session response Set-Cookie and login response.

IMPORTANT: Original uploaded ZIP included .env and .git. This fixed ZIP deliberately omits them. Rotate any secrets that were exposed.
