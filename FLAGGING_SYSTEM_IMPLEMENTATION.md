# Enhanced Flagging System - Implementation Summary

## Overview
A comprehensive content moderation system has been implemented with three-tier access: **User Reporting → Moderator Resolution → Admin Audit**. This system allows artists to flag posts/items, moderators to resolve flags with decisions logged, and admins to audit all actions in read-only mode.

## Database Schema Changes

### Posts Table Enhancement
```sql
ALTER TABLE posts ADD COLUMN like_count INT DEFAULT 0;
ALTER TABLE posts ADD COLUMN report_count INT DEFAULT 0;
ALTER TABLE posts ADD COLUMN reported_status ENUM('none', 'flagged', 'resolved', 'removed') DEFAULT 'none';
ALTER TABLE posts ADD INDEX idx_reported_status (reported_status);
```

### New Tables Created

#### 1. **flag_resolutions** - Moderator actions tracking
- `id` - Primary key
- `post_id` - FK to posts table
- `moderator_id` - FK to user (moderator)
- `action_type` - ENUM: 'approved' (keep post), 'removed' (delete post), 'warned' (warn user)
- `resolution_notes` - Text notes explaining the decision
- `resolved_at` - Timestamp of resolution

Used for: Tracking every moderator decision with audit trail

## Key Features

### 1. **Like System** 
- **Table**: `post_likes` with UNIQUE constraint (post_id, user_id)
- **Counter**: `like_count` automatically updated on [post_likes](post_likes) table
- **Access**: Artists only
- Location: [user_artwork.php](user_artwork.php#L74-L100)

### 2. **Flagging/Reporting System**
- **Table**: `post_reports` with UNIQUE constraint (post_id, reporter_id)
- **Counter**: `report_count` automatically updated after each report
- **Status Tracking**: 
  - `none` - No reports
  - `flagged` - Has 1+ reports and awaiting moderator review
  - `resolved` - Moderator approved/determined acceptable
  - `removed` - Moderator removed content
- **Threshold**: Posts become flagged after first report
- Location: [user_artwork.php](user_artwork.php#L107-L134)

### 3. **Moderator Flag Review Panel**
**File**: [moderator_flag_review.php](moderator_flag_review.php)

**Features**:
- Dashboard showing stats: Pending flags, Resolved, Removed posts
- List of all flagged content with:
  - Report count and unique reporters
  - Report reasons (Spam, Inappropriate, Copyright, Other)
  - Previous resolution history
- **Action Buttons** (for pending flags only):
  - ✅ **Approve (Keep Post)** - Flag removed, post remains
  - ❌ **Remove Post** - Content deleted from system
  - ⚠️ **Warn User** - User warned about policy violation
- Modal form for each action with:
  - Optional resolution notes for documentation
  - Confirmation before submission
- **Activity Logging**: All moderator actions logged to `moderator_activity_logs`
- **Access Control**: Moderator-only (role === 'moderator')

### 4. **Admin Flag Audit Dashboard**
**File**: [admin_flag_review.php](admin_flag_review.php)

**Features**:
- **Read-Only View**: Admins can view but cannot resolve flags
- **Overall Statistics**:
  - Total flags processed
  - Pending flags (awaiting moderator action)
  - Resolved flags
  - Removed posts
- **Moderator Performance Table**:
  - List of all moderators
  - Total resolutions per moderator
  - Breakdown: Approved, Removed, Warned counts
  - Quick performance metrics
- **Flag Details Display**:
  - Each flagged post shown with:
    - Report count and reporter count
    - Report reasons
    - Current status (Pending/Resolved/Removed)
    - Moderator decision (if resolved)
    - Moderator notes
    - Timestamp of resolution
- **Color-Coded Display**:
  - 🔴 Red border - Pending flags
  - 🟢 Green border - Resolved
  - ⚫ Gray border - Removed
- **Access Control**: Admin-only (role === 'admin')
- **Activity Logging**: Page views logged to `admin_activity_logs`

## Data Flow

### When a User Reports a Post
1. Report submitted via [user_artwork.php](user_artwork.php) form
2. Inserted into `post_reports` table (UNIQUE constraint prevents duplicates)
3. `report_count` updated on `posts` table
4. `reported_status` set to 'flagged' if report_count ≥ 1
5. Post appears in moderator dashboard

### When Moderator Reviews
1. Views post in [moderator_flag_review.php](moderator_flag_review.php)
2. Chooses action: Approve, Remove, or Warn
3. Optionally adds notes explaining decision
4. Action inserted into `flag_resolutions` table
5. `posts.reported_status` updated accordingly:
   - 'approved' action → status = 'resolved' (flag cleared)
   - 'removed' action → status = 'removed' (post removed)
   - 'warned' action → status = 'resolved' (flag cleared, user warned)
6. Moderator activity logged with timestamp and details

### When Admin Audits
1. Views [admin_flag_review.php](admin_flag_review.php)
2. Sees all flagged content with full history
3. Reviews moderator performance metrics
4. Cannot take any actions (read-only)
5. Page view logged to `admin_activity_logs`

## Navigation Updates

### Moderator Dashboard
Added "Flag Review" link to [moderator_dashboard.php](moderator_dashboard.php)
- Direct access to [moderator_flag_review.php](moderator_flag_review.php)

### Admin Dashboard
Added "Flag Audit" link to [admin_dashboard.php](admin_dashboard.php)
- Direct access to [admin_flag_review.php](admin_flag_review.php)

## Activity Logging Integration

### Moderator Actions Logged
Each moderator action recorded in `moderator_activity_logs`:
```
Activity: "Moderator action on post {id}: {action} - Notes: {excerpt}"
```

### Admin Page Views Logged
Admin flag review page views recorded in `admin_activity_logs`:
```
Activity: "Flag Review Dashboard (Admin)"
```

## Security & Constraints

### Database Constraints
- `UNIQUE KEY (post_id, reporter_id)` - One report per user per post
- `UNIQUE KEY (post_id, user_id)` - One like per user per post

### Access Control
- Moderators: Can only access `moderator_flag_review.php`
- Admins: Can only access `admin_flag_review.php`
- Session check: `$_SESSION['role']` validation on each page

### Role-Based Permissions
| Feature | User | Moderator | Admin |
|---------|------|-----------|-------|
| Report posts | ✅ | ✅ | ❌ |
| Like posts | ✅ | ✅ | ❌ |
| View flags | ❌ | ✅ | ✅ |
| Resolve flags | ❌ | ✅ | ❌ |
| Audit flags | ❌ | ❌ | ✅ |

## Testing Workflow

### As an Artist
1. Create post
2. See like/report buttons on posts
3. Like a post → like_count increases
4. Report a post → report_count increases, post gets flagged
5. Post appears in moderator dashboard

### As a Moderator
1. Login and navigate to Flag Review
2. See pending flagged posts
3. Click action button (Approve/Remove/Warn)
4. Add optional notes
5. Confirm - action logged, post status updated
6. Check My Activity Logs to see action recorded

### As an Admin
1. Login and navigate to Flag Audit
2. View all flagged content history
3. See moderator performance stats
4. Review specific moderator's decisions
5. See resolved and removed posts
6. Cannot make any changes (read-only)

## File Locations & Changes
- **[user_artwork.php](user_artwork.php)** - Updated like/report handlers, added count tracking
- **[moderator_flag_review.php](moderator_flag_review.php)** - New moderator review panel
- **[admin_flag_review.php](admin_flag_review.php)** - New admin audit dashboard
- **[moderator_dashboard.php](moderator_dashboard.php)** - Added flag review link
- **[admin_dashboard.php](admin_dashboard.php)** - Added flag audit link

## Summary
The system creates a clear separation of concerns:
- ✅ **Artists** report inappropriate content
- ✅ **Moderators** review and take action
- ✅ **Admins** audit decisions for oversight and training purposes

All actions are timestamped, logged, and attributed to specific users for full accountability.
