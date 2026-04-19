<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for auth_magiclink.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['alloweddomains'] = 'Allowed Domains';
$string['alloweddomains_desc'] = 'Comma-separated list of allowed email domains (e.g. school.edu, university.com). Leave empty to allow all.';
$string['auditlog'] = 'Audit Log';
$string['auth_magiclinkdescription'] = 'Magic Link Authentication. Users receive a secure link via email to log in.';
$string['cachedef_ratelimit'] = 'Rate limit counters for magic link requests';
$string['col_actions'] = 'Actions';
$string['col_auditaction'] = 'Action';
$string['col_created'] = 'Created';
$string['col_email'] = 'Email';
$string['col_expires'] = 'Expires';
$string['col_info'] = 'Info';
$string['col_ip'] = 'IP Address';
$string['col_status'] = 'Status';
$string['col_timestamp'] = 'Timestamp';
$string['col_user'] = 'User';
$string['deleteconfirmation'] = 'Are you sure you want to delete this link from the database?';
$string['deletemodal'] = 'Delete link';
$string['domainnotallowed'] = 'Login is restricted to specific email domains. This email is not allowed.';
$string['emailbody'] = 'Email Body';
$string['emailbody_default'] = 'Hi {$a->firstname},

Click here to log in to {$a->sitename}: {$a->loginlink}

Need the link? {$a->link}

This link will expire in {$a->expiration} minutes.

If you did not request this, please ignore this email.';
$string['emailbody_desc'] = 'The body of the email. You can use placeholders: {$a->firstname}, {$a->lastname}, {$a->link}, {$a->loginlink}, {$a->sitename}, {$a->expiration}. Use {$a->loginlink} for a clickable hyperlink.';
$string['emailsettings'] = 'Email Settings';
$string['emailsubject'] = 'Email Subject';
$string['emailsubject_default'] = 'Login to Moodle';
$string['emailsubject_desc'] = 'The subject line of the magic link email.';
$string['expired'] = 'This link has expired or has already been used.';
$string['extendconfirmation'] = 'Are you sure you want to extend the lifetime of this link? The link\'s expiration will be set 15 minutes from now, even if the expiration time already passed.';
$string['extendmodal'] = 'Extend link';
$string['gotomanage'] = 'Go to Management Page';
$string['invalidaction'] = 'Invalid action.';
$string['invalidemail'] = 'Invalid email address or user not found.';
$string['invaliduser'] = 'Cannot generate a login link for this user.';
$string['linkdeleted'] = 'Link deleted.';
$string['linkextended'] = 'Link extended by 15 minutes.';
$string['linkrevoked'] = 'Link revoked successfully.';
$string['linksent'] = 'We have sent a login link to <b>{$a}</b>. It will expire in 15 minutes.';
$string['linksent_uniform'] = 'If that email address is registered, a login link has been sent. Please check your inbox.';
$string['loginhere'] = 'Log in here';
$string['magiclink:manage'] = 'Manage magic link tokens and audit log';
$string['managelinks'] = 'Manage Links';
$string['noaudits'] = 'No audit log entries.';
$string['notokens'] = 'No tokens found.';
$string['orloginwithmagiclink'] = 'Or log in with magic link';
$string['orloginwithpassword'] = 'Or log in with password';
$string['pluginname'] = 'Magic Link';
$string['privacy:metadata:audit'] = 'Audit log of magic link authentication events.';
$string['privacy:metadata:audit:action'] = 'The action performed (e.g. send_link, login_success).';
$string['privacy:metadata:audit:email'] = 'The email address involved in the action.';
$string['privacy:metadata:audit:info'] = 'Additional information about the action.';
$string['privacy:metadata:audit:ip'] = 'The IP address of the requester.';
$string['privacy:metadata:audit:timecreated'] = 'When the audit entry was created.';
$string['privacy:metadata:audit:userid'] = 'The ID of the user.';
$string['privacy:metadata:token'] = 'Magic link login tokens.';
$string['privacy:metadata:token:expires'] = 'When the token expires.';
$string['privacy:metadata:token:timecreated'] = 'When the token was created.';
$string['privacy:metadata:token:token'] = 'SHA-256 hash of the login token (not the plaintext).';
$string['privacy:metadata:token:used'] = 'Whether the token has been used.';
$string['privacy:metadata:token:userid'] = 'The ID of the user the token belongs to.';
$string['privacy:path:audits'] = 'Magic link audit log';
$string['privacy:path:tokens'] = 'Magic link tokens';
$string['restrictions'] = 'Restrictions';
$string['revokeconfirmation'] = 'Are you sure you want to revoke this link before it expires? It will no longer be able to be used to log in to the site.';
$string['revokemodal'] = 'Revoke link';
$string['security'] = 'Security';
$string['sendlink'] = 'Send Magic Link';
$string['setting_allowedauthmethods'] = 'Allowed auth methods';
$string['setting_allowedauthmethods_desc'] = 'Users whose <code>auth</code> field matches one of these plugins may request a magic link. Administrators (users with <code>moodle/site:config</code>) are always excluded regardless of this setting. Upgrades from v3.2 default to <code>magiclink</code> only; fresh installs default to all enabled auth plugins. New auth plugins enabled after install do not auto-join — an administrator must add them explicitly.';
$string['setting_prune_days'] = 'Token retention (days)';
$string['setting_prune_days_desc'] = 'Expired tokens older than this many days are deleted by the scheduled task. Default: 30.';
$string['setting_ratelimit_email'] = 'Rate limit per email';
$string['setting_ratelimit_email_desc'] = 'Maximum magic link requests per email address within the rate limit window. Default: 3.';
$string['setting_ratelimit_ip'] = 'Rate limit per IP';
$string['setting_ratelimit_ip_desc'] = 'Maximum magic link requests per IP address within the rate limit window. Default: 10.';
$string['setting_ratelimit_window'] = 'Rate limit window';
$string['setting_ratelimit_window_desc'] = 'Time window for rate limiting. Counters reset after this period. Default: 15 minutes.';
$string['setting_tokenttl'] = 'Token lifetime';
$string['setting_tokenttl_desc'] = 'How long a magic link token remains valid after generation. Default: 15 minutes.';
$string['task_prune_expired_tokens'] = 'Prune expired magic link tokens';
$string['token_status_unused'] = 'Unused';
$string['token_status_used'] = 'Used';
$string['tokenexpired'] = 'This link has expired. Please request a new one.';
$string['tokeninvalid'] = 'This link is invalid. Please request a new one.';
$string['tokennotvalid'] = 'This login link is invalid or has expired. Please request a new one.';
$string['tokenused'] = 'This link has already been used. Please request a new one.';
$string['userinactive'] = 'This account is no longer active.';
