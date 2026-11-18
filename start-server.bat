@echo off
title FASTFOOD - Web Server
echo ====================================================
echo        KHOI DONG HE THONG FASTFOOD
echo ====================================================

REM 1. Kiem tra va khoi dong MySQL (Cong 3301)
netstat -ano | findstr :3301 >nul
if errorlevel 1 (
    echo [1/2] Dang khoi dong MySQL XAMPP...
    start "" /B "D:\xampp\mysql\bin\mysqld.exe" --defaults-file="D:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 2 /nobreak >nul
) else (
    echo [1/2] MySQL (cong 3301) dang hoat dong.
)

REM 2. Chay PHP Server
echo [2/2] Dang khoi dong Web Server PHP tren http://127.0.0.1:5000...
echo ----------------------------------------------------
echo Website:    http://127.0.0.1:5000
echo Dang nhap:  http://127.0.0.1:5000/login.php
echo ----------------------------------------------------
echo Nhan Ctrl + C de dung server.
echo.

if exist "D:\xampp\php\php.exe" (
    "D:\xampp\php\php.exe" -S 127.0.0.1:5000 router.php
) else (
    php -S 127.0.0.1:5000 router.php
)
