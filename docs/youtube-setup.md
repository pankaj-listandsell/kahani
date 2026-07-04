# YouTube Shorts Auto-Post — Setup Guide

Ye ek baar ka setup hai. Iske baad har user apna channel dashboard se connect kar sakta hai.

## 1. Google Cloud project banao
1. [Google Cloud Console](https://console.cloud.google.com/) kholo.
2. Upar project dropdown → **New Project** → naam do (e.g. "Kahani Shorts") → Create.

## 2. YouTube Data API v3 enable karo
1. Left menu → **APIs & Services → Library**.
2. "YouTube Data API v3" search karo → **Enable**.

## 3. OAuth consent screen
1. **APIs & Services → OAuth consent screen**.
2. User type: **External** → Create.
3. App name, support email, developer email bharo → Save & Continue.
4. **Scopes**: skip kar sakte ho (Continue).
5. **Test users**: apni Google email (jis channel par upload karna hai) **Add** karo. → Save.
   - ⚠️ App "Testing" mode me rahega. Testing mode me OAuth theek chalta hai, par
     **refresh token har 7 din baad expire** ho sakta hai — tab dashboard se dobara
     "Connect YouTube" dabana padega. (Permanent chahiye to app ko "Publish" / verify karao.)

## 4. OAuth client ID banao
1. **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
2. Application type: **Web application**.
3. **Authorized redirect URIs → Add URI**, aur bilkul yahi daalo (XAMPP par app `/story/public/` ke andar chalta hai):
   ```
   http://localhost/story/public/admin/youtube/callback
   ```
   ⚠️ Ye URI `.env` ke `GOOGLE_REDIRECT_URI` se **exactly** match hona chahiye (slash tak) —
   warna `Error 400: redirect_uri_mismatch` aata hai. Dashboard ke setup box me bhi exact URL dikhta hai.
4. Create → **Client ID** aur **Client secret** copy karo.

## 5. `.env` me daalo
```
GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxxxxx
GOOGLE_REDIRECT_URI="http://localhost/story/public/admin/youtube/callback"
```
Phir cache clear karo:
```
php artisan config:clear
```

## 6. Connect
Admin panel → **YouTube → Settings tab → Connect YouTube** → Google se allow karo.
Ho gaya ✅

## Auto-post chalane ke liye
Background me ye chalta rehna chahiye (Instagram ke liye bhi yahi):
```
php artisan schedule:work
```

## Quota (zaroori)
- Free quota: **10,000 units/din**.
- Har video upload: **1600 units** → yaani **~6 Shorts/din** max.
- **Slideshow mode** kam upload use karta hai (poora part = 1 Short), isliye zyada content ek upload me chala jaata hai.
- Zyada chahiye to Google Cloud Console se quota increase request karo.

## Post modes (Settings me)
- **Single Card** — har card ka apna ek Short.
- **Slideshow** — poore part ke saare cards jodkar ek Short (har card `sec/card` second dikhta hai).
