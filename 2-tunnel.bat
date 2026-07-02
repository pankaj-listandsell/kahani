@echo off
title Kahani - Cloudflare Tunnel (Instagram ke liye)
echo ==========================================
echo   Public tunnel ban raha hai...
echo   Neeche "https://....trycloudflare.com" URL aayega.
echo   USE COPY karke Admin -^> Instagram -^> Settings me
echo   "Public Base URL" me paste karo, phir Save karo.
echo.
echo   (Har baar naya URL banta hai - restart par update karna)
echo ==========================================
echo.
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://localhost:8000
pause
