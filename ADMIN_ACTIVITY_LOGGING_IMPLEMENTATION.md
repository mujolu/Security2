# Admin Activity Logging System - Implementation Summary

## Overview
The admin activity logging system has been implemented to track all super admin operations including page views, user management actions, and admin deployments. All activities are logged to the `admin_activity_logs` table and stored in the `registered_users` table with merged credentials.

## Files Modified

### 1. **admin_activity.php** ✅
**Changes Made:**
- Added `createAdminActivityLogsTable()` function to auto-create the `admin_activity_logs` table if it doesn't exist
- Enhanced `logAdminActivity()` function with proper exception handling and PDO prepared statements
- Added action types: `delete_moderator`, `add_moderator`, `ban_user` (used for Admin role records)
- Logs page views when admin visits the Activity Logs page
- Fetches and displays all activity logs in a formatted HTML table with columns:
  - Admin Username
  - Activity Description
  - IP Address
  - Timestamp
- Shows last 100 logs ordered by timestamp (descending)

**Key Features:**
- Auto-creates table with proper schema
- Stores admin user_id, activity description, and IP address
- Displays logs in a professional table format
- Error handling for database operations

---

### 2. **admin_deploy.php** ✅
**Changes Made:**
- Added logging functions (identical to admin_activity.php)
 - Logs page views when super admin visits Deploy Admins page
 - **When adding an admin:** Logs the action with format:
  ```
  Added admin: FirstName LastName (Username: username, Email: email)
  ```
  - Captures full admin details from the form
  - Records to `registered_users` table with role='moderator' (Admin role)
  - Falls back to `moderators` table if schema mismatch

- **When deleting an admin:** Logs the action with format:
  ```
  Deleted admin ID: FirstName LastName (ID:123, Username: username)
  ```
  - Fetches admin info before deletion
  - Records deletion with admin identification details
  - Falls back to `moderators` table if needed

**Credentials Merged:**
- Admin (moderator role) credentials (first_name, middle_initial, last_name, username, email, password, role) are directly inserted into the `registered_users` table
- Activity log includes usernames and emails for audit trail
- All actions are timestamped and IP-tracked

---

### 3. **admin_dashboard.php** ✅
**Changes Made:**
- Added logging functions (identical to other admin files)
- Logs page views when admin visits User Management Dashboard
- **When deleting a user:** Logs the action with format:
  ```
  Deleted user ID: FirstName LastName (ID:123, Username: username)
  ```
  - Captures user details before deletion
  - Prevents self-deletion and logs the action

- **When banning a user:** Logs the action with format:
  ```
  Banned user ID: FirstName LastName (ID:123, Username: username)
  ```
  - Captures user details before banning
  - Prevents self-banning and logs the action

---

## Database Schema

### admin_activity_logs Table
```sql
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(9) NOT NULL,
  `activity` VARCHAR(500) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `registered_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields:**
- `id`: Unique identifier for each log entry
- `user_id`: The admin who performed the action (VARCHAR(9) - matches registered_users.id)
- `activity`: Description of what was done (up to 500 characters)
- `ip_address`: IP address from which the action was performed
- `timestamp`: When the action occurred (auto-set to current time)

---

## Activity Types Logged

| Action | Description | Example |
|--------|-------------|---------|
| view | Page views | "Viewed page: User Management Dashboard" |
| add_moderator | Admin creation (moderator role) | "Added admin: John Doe (Username: johndoe, Email: john@example.com)" |
| delete_moderator | Admin deletion (moderator role) | "Deleted admin ID: John Doe (ID:123, Username: johndoe)" |
| delete_user | User account deletion | "Deleted user ID: Jane Smith (ID:456, Username: janesmith)" |
| ban_user | User account banning | "Banned user ID: Jane Smith (ID:456, Username: janesmith)" |
| approve_artwork | Artwork approval (reserved) | "Approved artwork ID: 789" |
| edit_user | User account edit (reserved) | "Edited user ID: 101" |
| logout | Admin logout (reserved) | "Logged out" |

---

## How It Works

### 1. Table Creation
When any admin page loads, the system checks if `admin_activity_logs` table exists. If not, it creates it automatically with proper schema.

### 2. Activity Logging
Each admin action that modifies data:
1. Retrieves the admin's user_id from session
2. Captures relevant details (user info, admin info, etc.)
3. Gets the admin's IP address
4. Inserts a record into `admin_activity_logs` with formatted activity message

### 3. Activity Display
The `admin_activity.php` page:
1. Queries the `admin_activity_logs` table
2. Joins with `registered_users` to get admin usernames
3. Displays logs in a table sorted by timestamp (newest first)
4. Limits to last 100 entries

---

## Admin Credentials Integration

When a super admin adds an admin (moderator role) via `admin_deploy.php`:

**Data Flow:**
1. Admin fills form with: firstname, middlename, lastname, email, username, password
2. Password hashed using `PASSWORD_DEFAULT` algorithm
3. Record inserted into `registered_users` table with:
   - `first_name`, `middle_initial`, `last_name`, `username`, `email`, `password`, `role='moderator'`
4. Activity logged with all admin details (except password for security)
5. If registered_users unavailable, falls back to `moderators` table
6. Activity is retrievable from `admin_activity_logs` table

**Logged Information:**
- Full admin name
- Username
- Email
- Admin who created the admin
- Exact timestamp and IP address

---

## Security Features

✅ **Prepared Statements:** All queries use PDO prepared statements to prevent SQL injection
✅ **Session Validation:** All logging functions require valid admin session
✅ **IP Tracking:** Records IP address of admin performing actions
✅ **Audit Trail:** Complete history of who did what, when, and from where
✅ **Self-Action Prevention:** Prevents admins from deleting or banning themselves
✅ **Foreign Key Constraint:** Logs deleted when admin user is deleted
✅ **Data Validation:** HTML special characters escaped in display
✅ **Error Handling:** Graceful fallback for database errors

---

## Testing Checklist

- [ ] Database table created successfully
- [ ] Admin can view Activity Logs page
- [ ] Page view is logged when accessing admin pages
- [ ] Adding a moderator is logged with full details
- [ ] Deleting a moderator is logged with details
- [ ] Deleting a user is logged with details
- [ ] Banning a user is logged with details
- [ ] Logs display in reverse chronological order
- [ ] IP addresses are captured correctly
- [ ] Formatting is readable and complete
- [ ] Moderator credentials saved in registered_users table
- [ ] Logs accessible and searchable

---

## Files Added

- `sql_create_admin_activity_logs.sql` - SQL schema for manual table creation (optional)

---

## Implementation Status

✅ **COMPLETE** - All features implemented and ready for testing

The system is fully integrated and automatically creates tables as needed. No additional manual SQL execution is required unless you prefer to pre-create the table.
