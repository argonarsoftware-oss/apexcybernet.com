<?php
/**
 * Moderator ACL.
 *
 * A user counts as a moderator if EITHER:
 *   - They are logged into the /admin/ console (session flag $_SESSION['admin_logged_in'])
 *     with role 'admin' or 'staff' (see admin/omni/auth.php for accounts),
 *   - OR their regular account_id is in the hardcoded promote list below.
 *
 * The admin console is its own login (username/password in admin/omni/auth.php).
 * Currently configured: kirfenia (admin), admin (admin), raffy (staff).
 */
function is_moderator(int $uid): bool {
    // Admin console session takes precedence (works regardless of regular account login)
    if (!empty($_SESSION['admin_logged_in']) && in_array(($_SESSION['admin_role'] ?? ''), ['admin', 'staff'], true)) {
        return true;
    }
    // Hardcoded fallback for known admin-owner regular accounts (so they get cross-group view
    // even when they haven't explicitly logged into /admin/ in the same browser session).
    static $ids = [8]; // kirfenia — regular account id, confirmed via debug
    return in_array($uid, $ids, true);
}
