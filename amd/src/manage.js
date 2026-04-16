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
 * Magic link management page JavaScript.
 *
 * @module     auth_magiclink/manage
 * @copyright  2026 LMS Light
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

export const init = async () => {
    document.querySelectorAll('#auth-magiclink-manage button')
    .forEach(elem => elem.addEventListener('click', async e => {
        e.preventDefault();
        var userId = e.target.dataset.id;
        var action = e.target.dataset.action;

        if (action === 'extend') {
            const modal = await ModalSaveCancel.create({
                title: await getString('extendmodal', 'auth_magiclink'),
                body: await getString('extendconfirmation', 'auth_magiclink'),
                show: true,
                removeOnClose: true,
            });
            modal.getRoot().on(ModalEvents.save, () => {
                window.location.href = `?action=extend&id=${userId}&sesskey=${M.cfg.sesskey}`;
            });
        } else {
            const modal = await ModalDeleteCancel.create({
                title: await getString(`${action}modal`, 'auth_magiclink'),
                body: await getString(`${action}confirmation`, 'auth_magiclink'),
                show: true,
                removeOnClose: true,
            });
            modal.getRoot().on(ModalEvents.delete, () => {
                window.location.href = `?action=${action}&id=${userId}&sesskey=${M.cfg.sesskey}`;
            });
        }
    }));
};
