# Enhanced Flagging System - Testing Guide

## Quick Start

### System Components
1. **[user_artwork.php](user_artwork.php)** - Artist post creation, liking, and reporting
2. **[moderator_flag_review.php](moderator_flag_review.php)** - Moderator flag resolution dashboard
3. **[admin_flag_review.php](admin_flag_review.php)** - Admin audit-only dashboard

## Test Scenario Workflow

### Phase 1: Artist Creates and Reports Content

**Step 1: Create Account & Login as Artist**
1. Register as new user with role: `artist`
2. Login to [user_artwork.php](user_artwork.php)
3. Verify sidebar shows "Artwork" as active tab

**Step 2: Create Posts**
1. Fill in title: "Test Artwork 1"
2. Add content: "Test post content"
3. Upload image (optional)
4. Click "Publish"
5. Verify post appears in feed

**Step 3: Like Posts**
1. See "Like" button on posts (artists only)
2. Click Like button
3. Button changes to "Unlike"
4. Like count should increment
5. ✅ Verify in database: `SELECT like_count FROM posts WHERE id = ?`

**Step 4: Report Posts**
1. See "Report" button on posts
2. Select reason: "Spam" or "Inappropriate" or "Copyright" or "Other"
3. Click "Report"
4. ✅ Verify report appears in `post_reports` table
5. ✅ Verify `posts.report_count` incremented
6. ✅ Verify `posts.reported_status` changed to 'flagged'

### Phase 2: Moderator Reviews and Resolves

**Step 5: Login as Moderator**
1. Register/login as user with role: `moderator`
2. Navigate to dashboard
3. Click "Flag Review" (should now be visible in sidebar)
4. Should see [moderator_flag_review.php](moderator_flag_review.php)

**Step 6: View Flag Dashboard**
1. See stats card showing pending flags (should show our test post)
2. See list of flagged posts with:
   - Title and excerpt
   - Report count and reasons
   - Reporter list (unique reporters)
   - Status: "Flagged" (red)

**Step 7: Take Moderator Action**
1. For pending flagged post, click one of:
   - "Approve (Keep Post)" - clears flag
   - "Remove Post" - removes content
   - "Warn User" - warns user but keeps post

2. **Test Approve Action:**
   - Click "Approve (Keep Post)"
   - Modal appears asking for optional notes
   - Type note: "Post is appropriate, no action needed"
   - Click "Confirm Resolution"
   - Page refreshes
   - ✅ Verify post moves to "Resolved" (green border)
   - ✅ Verify in database: `SELECT reported_status FROM posts WHERE id = ?` = 'resolved'
   - ✅ Verify action logged: `SELECT * FROM flag_resolutions WHERE post_id = ?`

3. **Test Remove Action:**
   - Create and flag another post
   - Click "Remove Post"
   - Modal appears
   - Type note: "Violates copyright policy"
   - Click "Confirm Resolution"
   - ✅ Verify post shows as "Removed" (gray border)
   - ✅ Verify action_type = 'removed' in flag_resolutions table

4. **Test Warn Action:**
   - Create and flag another post
   - Click "Warn User"
   - Modal appears
   - Type note: "First warning about spam content"
   - Click "Confirm Resolution"
   - ✅ Verify post shows as "Resolved"
   - ✅ Verify action_type = 'warned' in flag_resolutions table

**Step 8: Verify Activity Logging**
1. Navigate to "My Activity Logs" in moderator navigation
2. Should see entries like:
   - "Flag Review Dashboard" (page view)
   - "Moderator action on post X: approved" (action taken)
3. ✅ Verify entries in `moderator_activity_logs` table

### Phase 3: Admin Audits

**Step 9: Login as Admin**
1. Register/login as user with role: `admin`
2. Navigate to admin dashboard
3. Click "Flag Audit" (should be visible in navigation)
4. Should be directed to [admin_flag_review.php](admin_flag_review.php)

**Step 10: View Admin Dashboard**
1. See overview stats:
   - Total Flags (should be sum of all)
   - Pending (should be any new flags you just created)
   - Resolved (should show our approved flags)
   - Removed (should show our removed flags)

2. See Moderator Performance Table:
   - Moderator name
   - Total resolutions
   - Breakdown: Approved count, Removed count, Warned count

**Step 11: View Detailed Flag Audit**
1. See all flagged posts sorted by:
   - Pending first (red borders)
   - Resolved/Removed after (green/gray borders)
2. For each post, see:
   - Title and content excerpt
   - Report details: count, reporters, reasons
   - Moderator decision: action taken, moderator name
   - Resolution notes
   - Resolution timestamp

**Step 12: Verify Read-Only Access**
1. ✅ Verify NO action buttons visible for any posts
2. ✅ Verify cannot click resolve buttons
3. ✅ See message: "This is a read-only audit view. Only moderators can resolve flags."

**Step 13: Verify Activity Logging**
1. Navigate to "Activity Logs"
2. Should see "Flag Review Dashboard (Admin)" entry
3. ✅ Verify entries in `admin_activity_logs` table with timestamp

## Expected Database State After Testing

### posts table
```
id | title | report_count | reported_status
1  | Test Artwork 1 | 3 | 'resolved'
2  | Test Artwork 2 | 1 | 'removed'
3  | Test Artwork 3 | 1 | 'flagged'
```

### post_reports table
```
post_id | reporter_id | reason | created_at
1 | 101 | 'Spam' | 2024-XX-XX...
1 | 102 | 'Inappropriate' | 2024-XX-XX...
1 | 103 | 'Copyright' | 2024-XX-XX...
2 | 104 | 'Spam' | 2024-XX-XX...
3 | 105 | 'Inappropriate' | 2024-XX-XX...
```

### flag_resolutions table
```
post_id | moderator_id | action_type | resolution_notes | resolved_at
1 | 201 | 'approved' | 'Post is appropriate...' | 2024-XX-XX...
2 | 201 | 'removed' | 'Violates copyright...' | 2024-XX-XX...
3 | (NULL until resolved)
```

## Common Issues & Solutions

### Issue: Can't see Flag Review link in Moderator Dashboard
**Solution:** 
1. Verify you're logged in as role='moderator'
2. Check that moderator_dashboard.php was updated with the flag review link
3. Clear browser cache (Ctrl+Shift+Delete)

### Issue: Flag Review page shows blank/no posts
**Possible Causes:**
1. No posts have been reported yet
   - Solution: Go back to artist account and create/report posts
2. Database connection issue
   - Check error logs
3. Wrong user role
   - Verify $_SESSION['role'] === 'moderator'

### Issue: Action buttons don't appear on flagged posts
**Check:**
1. Post status must be 'flagged' (not 'resolved' or 'removed')
2. Only posts with reported_status = 'flagged' show action buttons

### Issue: Moderator action isn't creating flag_resolutions record
**Debugging:**
1. Check browser console for JavaScript errors
2. Verify form submitting with POST method
3. Check flag_resolutions table was created: `DESCRIBE flag_resolutions;`
4. Review PHP error logs

### Issue: Admin sees action buttons (should be read-only)
**Solution:**
1. Verify user role is truly 'admin' in $_SESSION
2. Check that admin_flag_review.php is the file being used (not moderator_flag_review.php)
3. Action buttons should be completely absent in admin view

## Database Verification Commands

```sql
-- Check if tables exist
SHOW TABLES LIKE 'post_%';
SHOW TABLES LIKE 'flag_%';
SHOW TABLES LIKE '%_activity_logs%';

-- View posts with flags
SELECT id, title, report_count, reported_status FROM posts WHERE reported_status != 'none';

-- View flagging details for specific post
SELECT * FROM post_reports WHERE post_id = 1;
SELECT * FROM flag_resolutions WHERE post_id = 1;

-- View moderator activity
SELECT * FROM moderator_activity_logs ORDER BY timestamp DESC LIMIT 10;

-- View admin audit activity
SELECT * FROM admin_activity_logs ORDER BY timestamp DESC LIMIT 10;

-- Count stats
SELECT 
    (SELECT COUNT(*) FROM posts WHERE reported_status = 'flagged') as pending_flags,
    (SELECT COUNT(*) FROM posts WHERE reported_status = 'resolved') as resolved_flags,
    (SELECT COUNT(*) FROM posts WHERE reported_status = 'removed') as removed_posts;
```

## Success Checklist

- [ ] Artists can create posts
- [ ] Artists can like posts (like_count updates)
- [ ] Artists can report posts with reason (report_count updates, status changes to 'flagged')
- [ ] Moderators can access Flag Review dashboard
- [ ] Moderators see correct flagged posts count
- [ ] Moderators can approve, remove, or warn on flagged posts
- [ ] Moderator actions are logged with timestamp and notes
- [ ] Post status updates after moderator action (resolved/removed)
- [ ] Admins can access Flag Audit dashboard (read-only)
- [ ] Admin view shows moderator performance stats
- [ ] Admin view shows correct flag counts and status breakdown
- [ ] Admin CANNOT take any actions (buttons not visible)
- [ ] Activity logs record all page views and actions
- [ ] Database records created for all tables and relationships

## Performance Notes

- Queries use proper indexes on frequently filtered columns
- Post_reports uses UNIQUE constraint on (post_id, reporter_id) - prevents duplicate reports
- Post_likes uses UNIQUE constraint on (post_id, user_id) - prevents duplicate likes
- Activity logs include timestamps and indexes for easy audit trail queries
- Moderator performance queries use GROUP BY with aggregate functions for efficiency

## Security Notes

- Session validation on every page (verified role)
- Prepared statements used for all database queries (prevents SQL injection)
- Input sanitization with htmlspecialchars() for output
- Type casting (int) for numeric inputs
- POST method used for state-changing operations
- Modal confirmation before taking actions
- Read-only view enforced for admins (no update permissions)
