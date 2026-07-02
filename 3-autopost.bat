@echo off
title Kahani - Auto Post Scheduler
cd /d D:\xampp\htdocs\story
echo ==========================================
echo   Auto-Post scheduler chal raha hai.
echo   Ye tabhi post karega jab:
echo     - Instagram page par Auto Post ON ho
echo     - abhi kisi time-window ke andar ho
echo   Isko chalu rakho (band mat karo).
echo ==========================================
php artisan schedule:work
pause
