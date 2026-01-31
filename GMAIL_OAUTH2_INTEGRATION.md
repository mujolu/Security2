# Gmail OAuth2 Implementation - Complete Integration Guide

## Overview
This document outlines the complete Gmail OAuth2 implementation for the Security2 system, enabling secure OTP email delivery via Gmail API without storing passwords.

---

## Implementation Status

### ✅ Completed Components

1. **google/auth (v1.50.0)** - Installed via Composer
   - Location: `vendor/google/auth/`
   - Purpose: Google authentication library

2. **google/apiclient (v2.19.0)** - Installed via Composer
   - Location: `vendor/google/apiclient/`
   - Purpose: Gmail API client library

3. **gmail_oauth2_helper.php** - Created ✓
   - Location: `html/gmail_oauth2_helper.php`
   - Class: `GmailOAuth2Service`
   - Functions:
     - `sendOTPEmail($recipientEmail, $otp, $expiryMinutes)` - Send OTP via Gmail API
     - `handleAuthorizationCode($code)` - Exchange auth code for tokens
     - `getAuthorizationUrl()` - Get user authorization URL
     - `saveRefreshToken($token)` - Store refresh token securely

4. **oauth2_init.php** - Created ✓
   - Location: `html/oauth2_init.php`
   - Purpose: Initiates OAuth2 authorization flow
   - User clicks this to authorize Gmail access

5. **oauth2_callback.php** - Created ✓
   - Location: `html/oauth2_callback.php`
   - Purpose: Handles Google's authorization callback
   - Exchanges code for tokens and stores refresh token
   - User is redirected here after authorizing on Google's site

6. **oauth2_setup.php** - Created ✓
   - Location: `html/oauth2_setup.php`
   - Purpose: Setup dashboard with authorization status
   - Shows if authorized, displays instructions
   - Allows revoking authorization

7. **email_config.php** - Updated ✓
   - Added support for `gmail_oauth2` service type
   - Routes to OAuth2 when `MAIL_SERVICE=gmail_oauth2`

8. **.env** - Updated ✓
   - Added comments for OAuth2 configuration variables
   - Includes GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI

---

## Pre-Implementation Setup Required

### Step 1: Create Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a Project" → "New Project"
3. Name it "Security2 OTP System"
4. Click "Create"

### Step 2: Enable Gmail API
1. In the Cloud Console, click "Enable APIs and Services"
2. Search for "Gmail API"
3. Click on it and click "Enable"
4. Wait for enablement to complete

### Step 3: Create OAuth2 Credentials
1. Go to "Credentials" in the left menu
2. Click "Create Credentials" → "OAuth client ID"
3. If prompted, set up OAuth consent screen first:
   - User Type: External
   - Required information: App name, User support email, Developer contact info
4. For Application Type, select "Web application"
5. Add Authorized redirect URIs:
   - `http://localhost/Security2/html/oauth2_callback.php`
   - If production: `https://yourdomain.com/Security2/html/oauth2_callback.php`
6. Click "Create"
7. Download JSON credentials (or copy Client ID and Secret)

### Step 4: Configure .env File
Edit `d:\xammp\htdocs\Security2\.env`:

```env
MAIL_SERVICE=gmail_oauth2
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID_HERE.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
GOOGLE_REDIRECT_URI=http://localhost/Security2/html/oauth2_callback.php
```

---

## First-Time Authorization Flow

### For Users
1. Navigate to `http://localhost/Security2/html/oauth2_setup.php`
2. Click "Authorize with Gmail"
3. You'll be redirected to Google login
4. Sign in with your Gmail account
5. Review permissions: "Send emails on your behalf"
6. Click "Allow"
7. You'll be redirected back with authorization complete

### What Happens Behind the Scenes
1. `oauth2_init.php` generates authorization URL
2. User is redirected to Google's authorization endpoint
3. User grants permission
4. Google redirects to `oauth2_callback.php` with authorization code
5. Code is exchanged for access token and refresh token
6. Refresh token is saved to `.gmail_oauth_token.json`
7. Future emails use the stored refresh token

---

## Sending OTPs

### For Password Reset (`resetpass.php`)
Update the OTP sending call to use Gmail OAuth2:

```php
<?php
require_once 'email_helper.php';
require_once 'gmail_oauth2_helper.php';

// Your existing code...

// Send OTP via Gmail OAuth2
$result = sendOTPViaGmail($user_email, $otp, 10);

if ($result['success']) {
    // OTP sent successfully
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_timestamp'] = time();
} else {
    // Handle error
    $error = $result['message'];
}
?>
```

### For Sign-Up (`gen_otp.php` and `send_otp.php`)
Similar implementation - use `sendOTPViaGmail()` function.

### Function Signature
```php
sendOTPViaGmail($email, $otp, $expiryMinutes = 2);
// Returns: ['success' => bool, 'message' => string]
```

---

## File Structure

```
Security2/
├── html/
│   ├── gmail_oauth2_helper.php        [NEW] Gmail API email service
│   ├── oauth2_init.php                [NEW] Authorization initiator
│   ├── oauth2_callback.php            [NEW] Authorization callback handler
│   ├── oauth2_setup.php               [NEW] Setup dashboard
│   ├── email_config.php               [UPDATED] Added oauth2 routing
│   ├── email_helper.php               [UNCHANGED] PHPMailer wrapper
│   ├── resetpass.php                  [UPDATE NEEDED] Use gmail_oauth2_helper
│   ├── gen_otp.php                    [UPDATE NEEDED] Use gmail_oauth2_helper
│   ├── send_otp.php                   [UPDATE NEEDED] Use gmail_oauth2_helper
│   └── ... (other files)
├── .env                               [UPDATED] Added OAuth2 vars
├── .gmail_oauth_token.json            [AUTO-CREATED] Stores refresh token
└── vendor/
    ├── google/auth/                   [NEW] Google Auth library
    ├── google/apiclient/              [NEW] Gmail API library
    └── ... (other composer packages)
```

---

## Switching Between Email Services

### To Use Gmail OAuth2
```env
MAIL_SERVICE=gmail_oauth2
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=...
```

### To Use Mailtrap (Development/Testing)
```env
MAIL_SERVICE=mailtrap
MAILTRAP_HOST=smtp.mailtrap.io
MAILTRAP_PORT=2525
MAILTRAP_USERNAME=...
MAILTRAP_PASSWORD=...
```

### To Use Gmail SMTP (App Password)
```env
MAIL_SERVICE=gmail
GMAIL_EMAIL=...@gmail.com
GMAIL_APP_PASSWORD=...
```

---

## Token Management

### Token Storage
- **Location**: `.gmail_oauth_token.json` (in project root)
- **Format**: JSON with refresh_token and created_at
- **Security**: File should not be committed to version control

### Token Refresh
- Handled automatically by `Google\Client`
- Refresh token is used to get new access tokens
- No manual intervention needed for routine operations

### Revoking Authorization
1. Visit `oauth2_setup.php`
2. Click "Revoke Authorization"
3. Token file is deleted
4. Next OTP send will fail until re-authorized
5. User can re-authorize anytime

---

## Email Sending Flow

```
User requests password reset
    ↓
resetpass.php generates OTP
    ↓
sendOTPViaGmail() called
    ↓
GmailOAuth2Service instantiated
    ↓
Loads refresh token from .gmail_oauth_token.json
    ↓
Google_Client refreshes access token
    ↓
Gmail API sends email message
    ↓
User receives OTP in inbox
```

---

## Troubleshooting

### Issue: "No valid Gmail token"
**Solution**: User must authorize first by visiting oauth2_init.php

### Issue: Authorization redirects to error page
**Solution**: Check GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI are correct

### Issue: Email sending fails with "Gmail API Error"
**Solution**: 
- Verify Gmail API is enabled in Google Cloud Console
- Check that CLIENT_ID and CLIENT_SECRET match current credentials
- Delete .gmail_oauth_token.json and re-authorize

### Issue: "Invalid JSON" when loading token
**Solution**: Delete .gmail_oauth_token.json and re-authorize

---

## Security Considerations

1. **No Password Storage**: Credentials never stored; only refresh token kept
2. **Limited Scope**: OAuth2 token only allows sending emails (not reading)
3. **File Permissions**: Ensure .gmail_oauth_token.json is not readable from web
4. **HTTPS Recommended**: Use HTTPS in production for authorization flow

---

## Production Deployment

### Changes for Production
1. Update GOOGLE_REDIRECT_URI to production domain:
   ```env
   GOOGLE_REDIRECT_URI=https://yourdomain.com/Security2/html/oauth2_callback.php
   ```

2. Update Google Cloud OAuth2 credentials:
   - Add production domain as authorized redirect URI
   - Add new credentials if needed

3. Move .env file outside web root (optional but recommended)

4. Use secure file storage for .gmail_oauth_token.json:
   ```php
   // Store in database instead of file
   // Or use encrypted storage
   ```

---

## Integration Checklist

- [ ] Step 1: Created Google Cloud Project
- [ ] Step 2: Enabled Gmail API  
- [ ] Step 3: Generated OAuth2 credentials (Client ID + Secret)
- [ ] Step 4: Updated .env with OAuth2 credentials
- [ ] Step 5: Set MAIL_SERVICE=gmail_oauth2 in .env
- [ ] Step 6: Visit oauth2_setup.php and authorize
- [ ] Step 7: See "Authorization Successful" message
- [ ] Step 8: Test OTP sending in resetpass.php / sign-up
- [ ] Step 9: Verify OTP received in user's Gmail inbox
- [ ] Step 10: Update resetpass.php, gen_otp.php, send_otp.php to use OAuth2

---

## Next Steps

1. **User Completes Setup**: Follow "Pre-Implementation Setup Required" above
2. **Authorize First Time**: Visit oauth2_setup.php
3. **Update OTP Code**: Modify resetpass.php, gen_otp.php, send_otp.php to call sendOTPViaGmail()
4. **Test Thoroughly**: Send test OTPs and verify receipt

---

## Support

For issues or questions about Gmail OAuth2 integration:
1. Check troubleshooting section above
2. Verify all .env variables are set correctly
3. Check Google Cloud Console for API enablement status
4. Check file permissions on .gmail_oauth_token.json

---

**Last Updated**: 2026
**Status**: Ready for Implementation
