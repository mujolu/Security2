# Admin Activity Logging - Testing Guide

## Quick Start

After making these changes, follow these steps to test the activity logging system:

### Step 1: Clear Your Browser Cache (Important!)
- Press **Ctrl+F5** (or **Cmd+Shift+R** on Mac) to do a hard refresh
- This ensures you're getting the latest PHP code

### Step 2: Navigate to Deploy Moderators
1. Go to: `admin_dashboard.php` → Click "Deploy Moderators"
2. You should see a **Refresh button** in the top right and a **Status indicator** showing:
   - ✓ Logging table active | Total logs: X
3. Create a new moderator with test data

### Step 3: Check Activity Logs
1. After creating a moderator, click "Activity Logs" in the sidebar
2. Click the **Refresh button** in the top right
3. You should now see:
   - ✓ Logging table active
   - **Added moderator: [FirstName] [LastName] (Username: xxx, Email: xxx)**
   - Your admin username
   - Your IP address
   - The exact timestamp

### Step 4: Verify Different Actions
Try these actions and check if they appear in the activity logs:

| Action | Where | Expected Log |
|--------|-------|--------------|
| View Activity Page | Click Activity Logs | "Viewed page: Activity Logs Page" |
| Add Moderator | Deploy Moderators | "Added moderator: John Doe (Username: johndoe, Email: john@ex.com)" |
| Delete Moderator | Deploy Moderators | "Deleted moderator ID: John Doe (ID:1, Username: johndoe)" |
| Delete User | User Management | "Deleted user ID: Jane Smith (ID:2, Username: janesmith)" |
| Ban User | User Management | "Banned user ID: Jane Smith (ID:2, Username: janesmith)" |

---

## Status Indicator Guide

The blue status bar at the top of Activity Logs shows:

### ✓ Logging table active
**What it means:** The database table is working correctly and ready to log activities.

**Total logs: 10**
**What it means:** There are 10 activities logged in the database.

### Common Issues

#### ✗ Logging table not ready
**Problem:** The table couldn't be created or queried.
**Solution:** 
- Check database connection in `connection.php`
- Manually run SQL from `sql_create_admin_activity_logs.sql`
- Check database user permissions

#### Total logs: 0 (with status ✓)
**Problem:** Table exists but is empty.
**Solution:**
- Perform some admin actions (add/delete moderator, ban user)
- Click Refresh button
- Logs should appear

---

## Refresh Button

After performing any admin action:
1. Click the **Refresh** button or press F5
2. The page will reload and show the latest logs
3. New activities should appear at the top (newest first)

---

## Troubleshooting

### Logs Still Not Appearing?

1. **Check Browser Cache**
   - Hard refresh: **Ctrl+F5** (Windows) or **Cmd+Shift+R** (Mac)

2. **Verify Admin Role**
   - Only users with `role = 'platform_admin'` can log activities
   - Check in browser Developer Tools → Application → Cookies → session

3. **Check Database**
   - Use phpMyAdmin to verify `admin_activity_logs` table exists
   - Check if records are being inserted when actions are performed

4. **Check Connection**
   - Verify `connection.php` is working correctly
   - Test by visiting any admin page

5. **Enable Error Display** (Temporary - Development Only)
   You can temporarily see errors by modifying `admin_activity.php`:
   ```php
   } catch (Exception $e) {
       $error_msg = 'Error fetching logs: ' . $e->getMessage(); // <- This now shows errors
   ```

---

## What Gets Logged

✅ **Automatically Logged:**
- Page views (every time admin visits a page)
- Moderator additions with full details
- Moderator deletions with ID and username
- User deletions with ID and username
- User bans with ID and username

**Logged Information Includes:**
- Admin who performed the action
- What was done (action type and details)
- When it happened (timestamp)
- From where (IP address)

❌ **Not Logged (by design for performance):**
- Page loads without state changes
- Form fields viewing (only actual actions)

---

## Data Retention

- **Logs are stored indefinitely** in the database
- **Last 100 logs** are displayed on the Activity Logs page
- To see older logs, use database queries or analytics tools

---

## Security Notes

✓ All queries use **prepared statements** (SQL injection protection)
✓ All output is **HTML-escaped** (XSS protection)
✓ Admin sessions are **validated** before logging
✓ IP addresses are **recorded** for audit trails
✓ Passwords are **never logged** (only usernames and emails)

---

## Success Criteria

You'll know everything is working when:

- [ ] Status indicator shows "✓ Logging table active"
- [ ] Total logs count increases after performing actions
- [ ] Activity table displays with username, activity, IP, and timestamp
- [ ] All moderator details are captured when added
- [ ] Delete/ban activities show user details
- [ ] Refresh button works and shows latest logs

---

## Database Schema (FYI)

```sql
CREATE TABLE admin_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(9) NOT NULL,
  activity VARCHAR(500) NOT NULL,
  ip_address VARCHAR(45) NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES registered_users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_timestamp (timestamp)
);
```

---

## Next Steps

After testing and verifying everything works:
1. ✅ Monitor activity logs regularly
2. ✅ Use logs for security audits
3. ✅ Archive old logs for compliance (if needed)
4. ✅ Add additional logging for other admin pages if needed

---

**Questions or issues?** Check that all three files have been updated:
- `admin_activity.php` ✓
- `admin_deploy.php` ✓
- `admin_dashboard.php` ✓

All three should have the updated `logAdminActivity()` function with table creation logic.
