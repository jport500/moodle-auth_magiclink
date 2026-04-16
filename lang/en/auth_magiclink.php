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
$string['invalidemail'] = 'Invalid email address or user not found.';
$string['invaliduser'] = 'Cannot generate a login link for this user.';
$string['linkdeleted'] = 'Link deleted.';
$string['linkextended'] = 'Link extended by 15 minutes.';
$string['linkrevoked'] = 'Link revoked successfully.';
$string['linksent'] = 'We have sent a login link to <b>{$a}</b>. It will expire in 15 minutes.';
$string['linksent_uniform'] = 'If that email address is registered, a login link has been sent. Please check your inbox.';
$string['loginhere'] = 'Log in here';
$string['managelinks'] = 'Manage Links';
$string['orloginwithmagiclink'] = 'Or log in with magic link';
$string['orloginwithpassword'] = 'Or log in with password';
$string['pluginname'] = 'Magic Link';
$string['restrictions'] = 'Restrictions';
$string['revokeconfirmation'] = 'Are you sure you want to revoke this link before it expires? It will no longer be able to be used to log in to the site.';
$string['revokemodal'] = 'Revoke link';
$string['sendlink'] = 'Send Magic Link';
$string['tokenexpired'] = 'This link has expired. Please request a new one.';
$string['tokeninvalid'] = 'This link is invalid. Please request a new one.';
$string['tokenused'] = 'This link has already been used. Please request a new one.';
$string['userinactive'] = 'This account is no longer active.';
