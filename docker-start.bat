@echo off
echo ========================================================
echo  Starting WordPress Docker Test Environment...
echo ========================================================

set HTTP_PROXY=
set HTTPS_PROXY=
set http_proxy=
set https_proxy=

docker compose up -d

echo.
echo Waiting for WordPress setup container to finish provisioning...
docker compose logs -f wpcli

echo.
echo ========================================================
echo  WordPress Environment Ready!
echo  Site URL:  http://localhost:8080
echo  Admin URL: http://localhost:8080/wp-admin
echo  Admin User: admin
echo  Admin Pass: password123
echo ========================================================
pause
