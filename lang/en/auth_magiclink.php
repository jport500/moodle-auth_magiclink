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

$string['auth_magiclinkdescription'] = 'Magic Link Authentication. Users receive a secure link via email to log in.';
$string['pluginname'] = 'Magic Link';

$string['sendlink'] = 'Send Magic Link';
$string['linksent'] = 'We have sent a login link to <b>{$a}</b>. It will expire in 15 minutes.';
$string['invalidemail'] = 'Invalid email address or user not found.';
$string['expired'] = 'This link has expired or has already been used.';
$string['orloginwithpassword'] = 'Or log in with password';
$string['orloginwithmagiclink'] = 'Or log in with magic link';

// Management strings.
$string['managelinks'] = 'Manage Links';
$string['gotomanage'] = 'Go to Management Page';
$string['linkrevoked'] = 'Link revoked successfully.';
$string['linkextended'] = 'Link extended by 15 minutes.';
$string['linkdeleted'] = 'Link deleted.';
$string['auditlog'] = 'Audit Log';
$string['extendmodal'] = 'Extend link';
$string['extendconfirmation'] = 'Are you sure you want to extend the lifetime of this link? The link\'s expiration will be set 15 minutes from now, even if the expiration time already passed.';
$string['revokemodal'] = 'Revoke link';
$string['revokeconfirmation'] = 'Are you sure you want to revoke this link before it expires? It will no longer be able to be used to log in to the site.';
$string['deletemodal'] = 'Delete link';
$string['deleteconfirmation'] = 'Are you sure you want to delete this link from the database?';

// Settings strings.
$string['emailsettings'] = 'Email Settings';
$string['emailsubject'] = 'Email Subject';
$string['emailsubject_desc'] = 'The subject line of the magic link email.';
$string['emailsubject_default'] = 'Login to Moodle';
$string['emailbody'] = 'Email Body';
$string['emailbody_desc'] = 'The body of the email. You can use placeholders: {$a->firstname}, {$a->lastname}, {$a->link}, {$a->loginlink}, {$a->sitename}, {$a->expiration}. Use {$a->loginlink} for a clickable hyperlink.';
$string['emailbody_default'] = 'Hi {$a->firstname},

Click here to log in to {$a->sitename}: {$a->loginlink}

Need the link? {$a->link}

This link will expire in {$a->expiration} minutes.

If you did not request this, please ignore this email.';

$string['restrictions'] = 'Restrictions';
$string['alloweddomains'] = 'Allowed Domains';
$string['alloweddomains_desc'] = 'Comma-separated list of allowed email domains (e.g. school.edu, university.com). Leave empty to allow all.';
$string['domainnotallowed'] = 'Login is restricted to specific email domains. This email is not allowed.';

// Token manager error strings.
$string['tokeninvalid'] = 'This link is invalid. Please request a new one.';
$string['tokenexpired'] = 'This link has expired. Please request a new one.';
$string['tokenused'] = 'This link has already been used. Please request a new one.';
$string['userinactive'] = 'This account is no longer active.';
$string['invaliduser'] = 'Cannot generate a login link for this user.';