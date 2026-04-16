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
 * @module     auth_magiclink/login
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

export const init = async magiclink_html => {
    const loginForm = document.querySelector("#login");
    loginForm.insertAdjacentHTML('beforebegin', magiclink_html);

    const magiclinkForm = document.querySelector("#auth-magiclink-form");
    loginForm.style.display = 'none';
    loginForm.insertAdjacentHTML(
        'beforeend',
        `<div class='text-center mt-3'>
            <a href="#" id="show-magiclink-form" class="text-decoration-underline">
                ${await getString('orloginwithmagiclink', 'auth_magiclink')}
            </a>
        </div>`
    );

    document.querySelector("#show-password-form").addEventListener('click', () => {
        loginForm.style.display = 'block';
        magiclinkForm.style.display = 'none';
    });

    document.querySelector("#show-magiclink-form").addEventListener('click', () => {
        loginForm.style.display = 'none';
        magiclinkForm.style.display = 'block';
    });
};
