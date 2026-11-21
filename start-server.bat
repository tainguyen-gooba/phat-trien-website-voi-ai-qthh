@echo off
title FASTFOOD - Web Server
echo ====================================================
echo        KHOI DONG HE THONG FASTFOOD
echo ====================================================

REM 1. Kiem tra MySQL (Cong 3306 hoac 3301)
netstat -ano | findstr ":3306 :3301" >nul
if errorlevel 1 (
    echo [1/2] Dang khoi dong MySQL XAMPP...
    if exist "D:\xampp\mysql\bin\mysqld.exe" (
        start "" /B "D:\xampp\mysql\bin\mysqld.exe" --defaults-file="D:\xampp\mysql\bin\my.ini" --standalone
    )
    timeout /t 2 /nobreak >nul
) else (
    echo [1/2] MySQL Database dang hoat dong san sang.
)

REM 2. Chay PHP Server
netstat -ano | findstr ":5000" >nul
if errorlevel 1 (
    echo [2/2] Dang khoi dong Web Server PHP tren http://127.0.0.1:5000...
    echo ----------------------------------------------------
    echo Website:    http://127.0.0.1:5000
    echo Dang nhap:  http://127.0.0.1:5000/login.php
    echo ----------------------------------------------------
    start http://127.0.0.1:5000
    echo Nhan Ctrl + C de dung server.
    echo.
    if exist "D:\xampp\php\php.exe" (
        "D:\xampp\php\php.exe" -S 127.0.0.1:5000 router.php
    ) else (
        php -S 127.0.0.1:5000 router.php
    )
) else (
    echo [2/2] Web Server PHP dang chay san sang tai http://127.0.0.1:5000!
    echo ----------------------------------------------------
    echo Website:    http://127.0.0.1:5000
    echo Dang nhap:  http://127.0.0.1:5000/login.php
    echo ----------------------------------------------------
    start http://127.0.0.1:5000
    echo May chu dang chay o che do nen.
)
