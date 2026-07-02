@echo off
title Kahani - Laravel Server
cd /d D:\xampp\htdocs\story
echo ==========================================
echo   Kahani app chal rahi hai
echo   Admin:  http://127.0.0.1:8000
echo   (Isko band mat karo jab tak kaam kar rahe ho)
echo ==========================================
php artisan serve --port=8000
pause
