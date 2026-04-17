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
 * Magic link login form JavaScript.
 *
 * Injects the server-rendered magic link form before the standard login
 * form and provides toggle links to switch between the two modes.
 * Progressive enhancement: with JS disabled, only the standard form shows.
 *
 * @module     auth_magiclink/login
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';

export const init = async(magiclinkHtml) => {
    const loginForm = document.querySelector('#login');
    if (!loginForm) {
        return;
    }

    loginForm.insertAdjacentHTML('beforebegin', magiclinkHtml);
    const magiclinkForm = document.querySelector('#auth-magiclink-form');

    loginForm.style.display = 'none';

    const toggleHtml = await Templates.render('auth_magiclink/login_toggle', {});
    loginForm.insertAdjacentHTML('beforeend', toggleHtml);

    document.querySelector('#show-password-form').addEventListener('click', (e) => {
        e.preventDefault();
        loginForm.style.display = 'block';
        magiclinkForm.style.display = 'none';
    });

    document.querySelector('#show-magiclink-form').addEventListener('click', (e) => {
        e.preventDefault();
        loginForm.style.display = 'none';
        magiclinkForm.style.display = 'block';
    });
};
