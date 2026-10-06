@echo off
echo ==================================================
echo   Zinus IT - HTTPS Deployment (Plug ^& Play)
echo ==================================================
echo.

echo [1/5] Stopping existing containers...
docker-compose down 2>nul

echo [2/5] Building fresh image with HTTPS...
docker-compose build --no-cache app

echo [3/5] Starting containers...
docker-compose up -d

echo [4/5] Waiting for container to be ready...
timeout /t 15 /nobreak >nul

echo [5/5] Verifying HTTPS setup...
echo.

docker-compose ps | findstr "zinusit-app" | findstr "Up" >nul
if %errorlevel% equ 0 (
    echo [OK] Container is running
) else (
    echo [ERROR] Container failed to start
    docker logs zinusit-app --tail 20
    exit /b 1
)

docker exec zinusit-app apache2ctl -M 2>nul | findstr "ssl_module" >nul
if %errorlevel% equ 0 (
    echo [OK] SSL module enabled
) else (
    echo [ERROR] SSL module not loaded
    exit /b 1
)

docker exec zinusit-app test -f /etc/apache2/ssl/apache-selfsigned.crt
if %errorlevel% equ 0 (
    echo [OK] SSL certificate exists
) else (
    echo [ERROR] SSL certificate missing
    exit /b 1
)

echo.
echo ==================================================
echo DEPLOYMENT SUCCESSFUL!
echo ==================================================
echo.
echo Access URLs:
echo   HTTPS: https://10.62.8.101:8443
echo   HTTP:  http://10.62.8.101:8001 (redirects to HTTPS)
echo.
echo Note: Browser will show 'Not secure' warning.
echo       Click 'Advanced' -^> 'Proceed' to access.
echo.
pause
