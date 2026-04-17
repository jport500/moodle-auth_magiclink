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
 * Settings for auth_magiclink.
 *
 * @package    auth_magiclink
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Register the manage page as an admin external page.
$ADMIN->add('authsettings', new admin_externalpage(
    'auth_magiclink_manage',
    get_string('managelinks', 'auth_magiclink'),
    new moodle_url('/auth/magiclink/manage.php'),
    'auth/magiclink:manage'
));

if ($ADMIN->fulltree) {
    // Link to management page.
    $manageurl = new moodle_url('/auth/magiclink/manage.php');
    $managelink = html_writer::link(
        $manageurl,
        get_string('gotomanage', 'auth_magiclink'),
        ['class' => 'btn btn-primary']
    );
    $settings->add(new admin_setting_heading(
        'auth_magiclink/manage',
        get_string('managelinks', 'auth_magiclink'),
        $managelink
    ));

    // Email settings header.
    $settings->add(new admin_setting_heading(
        'auth_magiclink/emailsettings',
        get_string('emailsettings', 'auth_magiclink'),
        ''
    ));

    // Email subject.
    $settings->add(new admin_setting_configtext(
        'auth_magiclink/emailsubject',
        get_string('emailsubject', 'auth_magiclink'),
        get_string('emailsubject_desc', 'auth_magiclink'),
        get_string('emailsubject_default', 'auth_magiclink')
    ));

    // Email body.
    $settings->add(new admin_setting_configtextarea(
        'auth_magiclink/emailbody',
        get_string('emailbody', 'auth_magiclink'),
        get_string('emailbody_desc', 'auth_magiclink'),
        get_string('emailbody_default', 'auth_magiclink')
    ));

    // Restrictions header.
    $settings->add(new admin_setting_heading(
        'auth_magiclink/restrictions',
        get_string('restrictions', 'auth_magiclink'),
        ''
    ));

    // Allowed domains.
    $settings->add(new admin_setting_configtext(
        'auth_magiclink/alloweddomains',
        get_string('alloweddomains', 'auth_magiclink'),
        get_string('alloweddomains_desc', 'auth_magiclink'),
        ''
    ));

    // Security settings header.
    $settings->add(new admin_setting_heading(
        'auth_magiclink/security',
        get_string('security', 'auth_magiclink'),
        ''
    ));

    // Token TTL.
    $settings->add(new admin_setting_configduration(
        'auth_magiclink/tokenttlseconds',
        get_string('setting_tokenttl', 'auth_magiclink'),
        get_string('setting_tokenttl_desc', 'auth_magiclink'),
        900
    ));

    // Rate limit: per email.
    $settings->add(new admin_setting_configtext(
        'auth_magiclink/ratelimit_email',
        get_string('setting_ratelimit_email', 'auth_magiclink'),
        get_string('setting_ratelimit_email_desc', 'auth_magiclink'),
        '3',
        PARAM_INT
    ));

    // Rate limit: per IP.
    $settings->add(new admin_setting_configtext(
        'auth_magiclink/ratelimit_ip',
        get_string('setting_ratelimit_ip', 'auth_magiclink'),
        get_string('setting_ratelimit_ip_desc', 'auth_magiclink'),
        '10',
        PARAM_INT
    ));

    // Rate limit: window.
    $settings->add(new admin_setting_configduration(
        'auth_magiclink/ratelimit_window',
        get_string('setting_ratelimit_window', 'auth_magiclink'),
        get_string('setting_ratelimit_window_desc', 'auth_magiclink'),
        900
    ));

    // Prune days.
    $settings->add(new admin_setting_configtext(
        'auth_magiclink/prune_days',
        get_string('setting_prune_days', 'auth_magiclink'),
        get_string('setting_prune_days_desc', 'auth_magiclink'),
        '30',
        PARAM_INT
    ));
}
