# 🔐 Gmail OAuth2 Setup for Security2

## Step 1: Create Google Cloud Project

1. Go to: https://console.cloud.google.com/
2. Click **"Select a Project"** → **"NEW PROJECT"**
3. Name: `Security2`
4. Click **"CREATE"**
5. Wait for project to be created (1-2 min)

## Step 2: Enable Gmail API

1. In Google Cloud Console, go to **APIs & Services** → **Library**
2. Search for: `Gmail API`
3. Click on **Gmail API**
4. Click **ENABLE**

## Step 3: Create OAuth2 Credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **"+ CREATE CREDENTIALS"** → **OAuth client ID**
3. Choose **Application type**: `Web application`
4. Under **Authorized redirect URIs**, add:
   ```
   http://localhost/Security2/html/oauth2_callback.php
   ```
5. Click **CREATE**
6. You'll see: **Client ID** and **Client Secret**
7. Click **DOWNLOAD JSON** (save as `credentials.json`)

## Step 4: Add Credentials to `.env`

Extract from `credentials.json` and add to `.env`:

```env
# Gmail OAuth2 Configuration
MAIL_SERVICE=gmail_oauth2
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT_URI=http://localhost/Security2/html/oauth2_callback.php
```

## Step 5: Initialize OAuth2 (One-time)

1. Visit: `http://localhost/Security2/html/oauth2_init.php`
2. Click **"Authorize with Gmail"**
3. Log in with your Gmail account
4. Click **"Allow"**
5. You'll be redirected back with a refresh token
6. Token is saved automatically

## Done!

OTP emails will now send via your real Gmail account using OAuth2 tokens.

---

## 📋 What You Need from Google Cloud

After creating credentials, copy these to `.env`:
- **GOOGLE_CLIENT_ID** - From JSON file
- **GOOGLE_CLIENT_SECRET** - From JSON file
- **GOOGLE_REDIRECT_URI** - `http://localhost/Security2/html/oauth2_callback.php`

Then visit: `http://localhost/Security2/html/oauth2_init.php` to authorize.
