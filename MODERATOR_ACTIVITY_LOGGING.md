# Moderator Activity Logging System

## Overview
The moderator activity logging system tracks all actions performed by moderators in the moderator panel, including flag review decisions and page access.

## What Gets Logged

### 1. **Page Access Events**
- ✅ Accessed Moderator Dashboard
- ✅ Accessed Flag Review Dashboard  
- ✅ Accessed Activity Logs Page

### 2. **Flag Review Actions** (Most Critical)
When a moderator takes action on a flagged post:
- **Post ID** - Which post was reviewed
- **Action Type** - One of:
  - `approved` - Post was approved (flagging removed)
  - `removed` - Post was removed
  - `warned` - User was warned
- **Resolution Notes** - Optional notes explaining the decision (up to 100 chars logged)
- **Timestamp** - When the action was taken
- **Device Info** - Device type (Mobile/Tablet/Desktop)
- **OS Info** - Operating System
- **IP Address** - IP address of the moderator

### 3. **Session Logging**
- ✅ **Login Time** - Recorded in `login_logs` table
- ✅ **Logout Time** - Auto-updated when moderator logs out
- ✅ **Session Duration** - Calculated from login to logout
- ✅ Device and OS detection for each session

## Database Tables

### `moderator_activity_logs`
Stores all moderator activities within the panel:
| Column | Type | Purpose |
|--------|------|---------|
| id | INT PRIMARY KEY | Activity record ID |
| user_id | VARCHAR(9) | Moderator's user ID |
| activity | VARCHAR(500) | Description of the action |
| ip_address | VARCHAR(15) | Moderator's IP address |
| device | VARCHAR(50) | Device type (Desktop/Mobile/Tablet) |
| os | VARCHAR(50) | Operating system |
| time_in | TIMESTAMP | Login time of the session |
| time_out | TIMESTAMP | Logout time of the session |
| timestamp | TIMESTAMP | When this activity was recorded |

### `flag_resolutions`
Stores detailed flag resolution records:
| Column | Type | Purpose |
|--------|------|---------|
| id | INT PRIMARY KEY | Resolution record ID |
| post_id | INT | ID of the flagged post |
| moderator_id | INT | Moderator who took action |
| action_type | ENUM | 'approved', 'removed', or 'warned' |
| resolution_notes | TEXT | Moderator's notes on decision |
| resolved_at | TIMESTAMP | When action was taken |

### `login_logs`
Tracks login/logout sessions:
| Column | Type | Purpose |
|--------|------|---------|
| login_id | INT PRIMARY KEY | Login record ID |
| user_id | VARCHAR(9) | User who logged in |
| username | VARCHAR(255) | Username |
| login_time | TIMESTAMP | When logged in |
| logout_time | TIMESTAMP | When logged out (NULL if still logged in) |
| device | VARCHAR(50) | Device type |
| os | VARCHAR(50) | Operating system |
| ip_address | VARCHAR(15) | IP address |

## Example Log Records

### Flag Review Action
```
Activity: Flag Resolution: Post #42 - Action: removed - Notes: Violates community guidelines
Timestamp: 2026-02-23 14:35:22
Moderator: mod_001
Device: Desktop
OS: Windows
IP: 192.168.1.100
Session Time In: 2026-02-23 14:00:00
Session Time Out: 2026-02-23 15:45:30
```

### Page Access
```
Activity: Accessed Flag Review Dashboard
Timestamp: 2026-02-23 14:05:10
Moderator: mod_001
```

## How to View Moderator Logs

### Method 1: Moderator Dashboard
1. Login as moderator
2. Click "My Activity Logs" in sidebar
3. View your login sessions and activity

### Method 2: Database Query
```sql
-- View all activities by a moderator
SELECT * FROM moderator_activity_logs 
WHERE user_id = 'moderator_id' 
ORDER BY timestamp DESC;

-- View all flag resolutions
SELECT 
    fr.id,
    fr.post_id,
    u.username,
    fr.action_type,
    fr.resolution_notes,
    fr.resolved_at
FROM flag_resolutions fr
JOIN registered_users u ON fr.moderator_id = u.id
ORDER BY fr.resolved_at DESC;

-- View moderator session details
SELECT 
    mal.user_id,
    mal.activity,
    mal.device,
    mal.os,
    mal.time_in,
    mal.time_out,
    TIMESTAMPDIFF(MINUTE, mal.time_in, mal.time_out) as session_minutes
FROM moderator_activity_logs mal
WHERE mal.user_id = 'moderator_id'
ORDER BY mal.timestamp DESC;
```

## Implementation Details

### Files Involved
1. **moderator_dashboard.php** - Logs dashboard access
2. **moderator_flag_review.php** - Logs flag access and all flag actions
3. **moderator_activity_logs.php** - Logs activity logs page access
4. **moderator_sidebar.php** - Shared sidebar with navigation
5. **activity_logger.php** - Core logging functions
6. **logOut.php** - Updates logout time and session end time
7. **login_logs table** - Tracks login/logout sessions

### Logging Functions Used
- `logActivity($conn, $user_id, $activity, $table_name)` - Main logging function
- `detectDevice()` - Identifies device type
- `detectOS()` - Identifies operating system
- `getIPAddress()` - Gets IP address

## Audit Trail

The system creates a complete audit trail where you can:
1. ✅ See which moderator performed each action
2. ✅ See exactly what action was taken (approve/remove/warn)
3. ✅ See when the action was taken
4. ✅ See what device/location the moderator was using
5. ✅ See how long the moderator was logged in
6. ✅ See any notes the moderator added

## Compliance

This logging system supports:
- ✅ Accountability - Each action is tied to a specific moderator
- ✅ Audit Trail - Complete history of moderation decisions
- ✅ Compliance Reporting - Can generate reports of moderation activity
- ✅ Security - Detects suspicious access patterns or anomalies
- ✅ Performance Review - Tracks which moderators are active

## Future Enhancements

Possible additions:
- Flag unusual access patterns
- Generate moderation reports
- Track moderator efficiency metrics
- Add approval workflow for high-impact actions
- Send notifications of moderation activities
