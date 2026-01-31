## 🧪 Testing Gmail OAuth2 Integration

### Before Testing:
1. ✅ `.env` file has been updated with your Google OAuth2 credentials
2. ✅ `MAIL_SERVICE=gmail_oauth2` is enabled
3. ✅ All OTP code updated to use Gmail OAuth2

---

## Step 1: First-Time Authorization

Visit this URL in your browser:
```
http://localhost/Security2/html/oauth2_setup.php
```

**What you'll see:**
- Authorization status dashboard
- "Authorize with Gmail" button

**Click "Authorize with Gmail":**
1. You'll be redirected to Google login
2. Sign in with your Gmail account
3. Grant permission: "Allow Security2 to send emails on your behalf"
4. You'll be redirected back to see "Authorization Successful" ✓

**Important:** This only needs to be done ONCE.

---

## Step 2: Test Password Reset (OTP via Email)

1. Go to login page: `http://localhost/Security2/html/login.php`
2. Click "Forgot Password?"
3. Enter an email registered in your system
4. Click "Send OTP"
5. **Check your Gmail inbox** for the OTP email

**Expected:**
- Email arrives within seconds
- Contains 6-digit OTP code
- Subject: "Your OTP Code - Security2 System"

---

## Step 3: Test OTP Verification

After receiving OTP:
1. Copy the OTP from email
2. Paste it in the "Enter OTP" field
3. Click "Verify OTP"

**Expected:**
- ✓ OTP verified successfully
- Proceed to reset password page

---

## Step 4: Test Registration (OTP)

1. Go to registration page: `http://localhost/Security2/html/register.php`
2. Fill out registration form
3. Click "Register"
4. Should receive OTP email at registered email address

---

## Troubleshooting

### ❌ "No valid Gmail token" error
**Solution:** 
- Go to `oauth2_setup.php`
- Click "Revoke Authorization"
- Then "Authorize with Gmail" again

### ❌ Email not arriving
**Check:**
1. Gmail inbox (not spam)
2. Authorization status: `oauth2_setup.php` should show ✓ Authorized
3. .env file has MAIL_SERVICE=gmail_oauth2
4. Check browser console for errors

### ❌ Authorization page shows error
**Check:**
1. GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET are correct
2. GOOGLE_REDIRECT_URI matches exactly
3. Gmail API is enabled in Google Cloud Console

### ❌ "Authorization failed" in callback
**Check:**
1. Credentials are valid in Google Cloud Console
2. OAuth2 redirect URI is whitelisted
3. Delete .gmail_oauth_token.json and re-authorize

---

## Files Updated for Testing
- ✅ resetpass.php - Uses Gmail OAuth2 for OTP
- ✅ gen_otp.php - Uses Gmail OAuth2 for OTP
- ✅ send_otp.php - Uses Gmail OAuth2 for OTP
- ✅ .env - MAIL_SERVICE=gmail_oauth2 enabled

---

## Success Indicators ✓

You'll know it's working when:
1. ✓ Authorization shows "Authorized" on oauth2_setup.php
2. ✓ OTP emails arrive in Gmail inbox within seconds
3. ✓ Emails are from janelmagda@gmail.com
4. ✓ No rate limiting errors
5. ✓ All OTP codes work for verification
