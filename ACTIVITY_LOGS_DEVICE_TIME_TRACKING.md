# Activity Logs Enhancement - Device & Time Tracking

## Summary of Changes

Added device tracking and time in/timeout columns to activity logs for Super Admin, Admin (moderator role), and User/Artist roles.

## Database Schema Updates

### 1. Admin Activity Logs (`admin_activity_logs`)
**New Columns Added:**
- `device` VARCHAR(50) - Tracks device type (Desktop, Mobile, Tablet)
- `time_in` TIMESTAMP - Session/login start time
- `time_out` TIMESTAMP - Session/logout end time

**File:** [sql_create_admin_activity_logs.sql](sql_create_admin_activity_logs.sql)

### 2. Admin Activity Logs (moderator role) (`moderator_activity_logs`)
**New Table Created** with identical structure to admin logs
- `device` VARCHAR(50)
- `time_in` TIMESTAMP
- `time_out` TIMESTAMP

**File:** [sql_create_moderator_activity_logs.sql](sql_create_moderator_activity_logs.sql)

### 3. User Activity Logs (`user_activity_logs`)
**New Columns Added:**
- `device` VARCHAR(50)
- `time_in` TIMESTAMP
- `time_out` TIMESTAMP

**File:** [sql_create_user_activity_logs.sql](sql_create_user_activity_logs.sql)

## PHP Updates

### Core Logging Functions

**File:** [html/activity_logger.php](html/activity_logger.php)

**New Functions Added:**
- `detectDevice()` - Detects device type from User-Agent
- `getIPAddress()` - Gets IPv4 address with validation
- `logActivity()` - Updated to support device and time tracking
- `logAdminAction()` - Convenience wrapper for admin logging
- `logUserAction()` - Convenience wrapper for user logging
- `logModeratorAction()` - Convenience wrapper for admin (moderator role) logging

### Admin Activity Logging

**Files Modified:**
- [html/admin_activity.php](html/admin_activity.php) - Updated table creation and logging
- [html/admin_dashboard.php](html/admin_dashboard.php) - Updated logging functions with device detection
- [html/admin_deploy.php](html/admin_deploy.php) - Updated logging functions with device detection

**Changes:**
- Enhanced `createAdminActivityLogsTable()` to include new columns
- Updated `logAdminActivity()` to:
  - Accept optional `time_in` and `time_out` parameters
  - Detect device type from User-Agent
  - Store all three new data points in database

### Admin (Moderator Role) Activity Logging

**File:** [html/moderator_dashboard.php](html/moderator_dashboard.php)

**Changes:**
- Added `createModeratorActivityLogsTable()` function
- Added `logModeratorActivity()` function with device and time tracking
- Automatically logs page views with device info

### User Activity Logging

**File:** [html/authenticatedLogin.php](html/authenticatedLogin.php)

**Changes:**
- Updated user_activity_logs table creation to include new columns
- Updated `logUserActivity()` function to:
  - Accept optional `time_in` and `time_out` parameters
  - Detect device using improved logic
  - Store device and time data

## Display Pages Updated

### Admin Activity Logs Page

**File:** [html/admin_activity.php](html/admin_activity.php)

**Columns Displayed:**
1. Admin Username
2. Activity
3. **Device** (NEW) - Shows device type in orange badge
4. IP Address
5. **Time In** (NEW) - Session start time
6. **Time Out** (NEW) - Session end time
7. Timestamp

### Admin Activity Logs Page (moderator role)

**File:** [html/moderator_activity_logs.php](html/moderator_activity_logs.php)

**Columns Displayed:**
1. Activity (NEW) - Shows what action was performed
2. **Device** (NEW) - Orange badge showing device type
3. IP Address
4. **Time In** (NEW) - Session start time
5. **Time Out** (NEW) - Session end time
6. Timestamp

### User Activity Logs Page

**File:** [html/user_activity_logs.php](html/user_activity_logs.php)

**Columns Displayed:**
1. Activity (NEW) - Shows user actions
2. **Device** (NEW) - Blue badge showing device type
3. IP Address
4. **Time In** (NEW) - Session start time
5. **Time Out** (NEW) - Session end time
6. Timestamp

## Device Detection Logic

The device detection uses the following logic:
```
If User-Agent contains: Mobile, Android, iPhone, iPod
  - Check if contains Tablet or iPad → 'Tablet'
  - Otherwise → 'Mobile'
Else
  - 'Desktop'
```

## Features

### Multiple Activity Log Tables
- **admin_activity_logs** - For super admin activities
- **moderator_activity_logs** - For admin (moderator role) oversight activities  
- **user_activity_logs** - For user/artist activities

### Device Tracking
- Automatically detects device type from User-Agent header
- Supports: Desktop, Mobile, Tablet
- Displayed with color-coded badges in UI

### Time Tracking
- `time_in` - When user started session/activity
- `time_out` - When user ended session/activity
- Optional fields allowing NULL values for ongoing sessions

### IP Address Logging
- IPv4 only (VARCHAR 15)
- Validated using filter_var() with FILTER_FLAG_IPV4
- Defaults to 127.0.0.1 if invalid or IPv6

## Usage Examples

### Logging Admin Activity
```php
logAdminActivity($conn, 'view', 'Dashboard');
logAdminActivity($conn, 'delete_user', 'user123', $login_time, $logout_time);
```

### Logging Admin (Moderator Role) Activity
```php
logModeratorActivity($conn, 'review_content', 'post456');
logModeratorActivity($conn, 'approve_content', 'artwork789', $time_in, $time_out);
```

### Logging User Activity
```php
logUserAction($conn, '12345', 'uploaded artwork', 'MyArtwork.jpg');
logUserAction($conn, '12345', 'marketplace action', 'Listed item', $login_time, $logout_time);
```

## Migration Notes

To apply these changes to an existing database:

1. Run the SQL migration scripts:
   ```sql
   ALTER TABLE admin_activity_logs ADD COLUMN device VARCHAR(50) NULL;
   ALTER TABLE admin_activity_logs ADD COLUMN time_in TIMESTAMP NULL;
   ALTER TABLE admin_activity_logs ADD COLUMN time_out TIMESTAMP NULL;
   ```

2. Create new moderator_activity_logs table (if not exists):
   ```sql
   [Run sql_create_moderator_activity_logs.sql]
   ```

3. Update user_activity_logs table (if not exists):
   ```sql
   ALTER TABLE user_activity_logs ADD COLUMN device VARCHAR(50) NULL;
   ALTER TABLE user_activity_logs ADD COLUMN time_in TIMESTAMP NULL;
   ALTER TABLE user_activity_logs ADD COLUMN time_out TIMESTAMP NULL;
   ```

## Backward Compatibility

All changes are backward compatible:
- New columns are nullable
- Old records will show NULL for device, time_in, time_out
- Existing queries still work (use SELECT * or explicitly list columns)
- No breaking changes to logging functions

## Performance Considerations

- Added indexes on user_id and timestamp for faster queries
- Foreign key constraints enforced for data integrity
- Minimal overhead for device detection (simple string matching)

