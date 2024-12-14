/*
 * This file is part of Contao Multifile Download.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-multifile-download
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    let lang = {};

    (async () => {
        if (Object.keys(lang).length === 0) {
            const contentElementId = document.querySelector('.content-downloads[data-contentelementid]')
                ?.dataset['contentelementid']
            ;

            try {
                if (!contentElementId > 0) {
                    throw new Error('Content element id not found. It should be set as a data-attribute in the download link container.');
                }

                const href = `${window.location.href}?load_language_data=true&content_downloads=true&ce_id=${contentElementId}`;

                const response = await fetch(href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }

                lang = await response.json();

            } catch (error) {
                console.error(error.message);
            }
        }
    })();

    // Init file download
    (() => {
        const buttons = document.querySelectorAll('.multifile-download--button-container .multifile-download--submit-button');
        for (const button of buttons) {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const button = event.target;
                const list = button.closest('.content-downloads[data-contentelementid]').querySelector('.multifile-download--list');
                let files = [];
                const checkBoxes = list.querySelectorAll('input.multifile-download--checkbox');

                for (const checkBox of checkBoxes) {
                    if (checkBox.checked) {
                        files.push(checkBox.value);
                    }
                }

                const contentElementId = button
                    .closest('.content-downloads[data-contentelementid]')
                        ?.dataset['contentelementid']
                ;

                if (files.length > 0 && contentElementId > 0) {
                    window.location.href = `${window.location.href}?multifile_download=true&files=${files.join().trim()}&el_id=${contentElementId}`;
                } else {
                    alert(lang['pleaseSelectOneFile'])
                }
            })
        }
    })();

    // Toggle checkboxes, select-all link and download button
    (() => {
        const links = document.querySelectorAll('.content-downloads[data-ismultifiledownload="true"] .multifile-download--appear-checkbox-link-container a');

        for (const link of links) {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const link = event.target;
                link.closest('.content-downloads').querySelector('.multifile-download--list')?.classList.toggle('show-checkbox');
                link.closest('.content-downloads').querySelector('.multifile-download--button-container')?.classList.toggle('display-none');
                link.closest('.content-downloads').querySelector('.multifile-download--select-all-container')?.classList.toggle('display-none');
            })
        }
    })();

    // Disable links if checkboxes are visible
    (() => {
        const links = document.querySelectorAll('.content-downloads[data-ismultifiledownload="true"] .multifile-download--list a');

        for (const link of links) {
            link.addEventListener('click', (event) => {
                if (event.target.closest('.multifile-download--list').classList.contains('show-checkbox')) {
                    if (typeof event.target === 'object' && true === event.target.classList?.contains('multifile-download--checkbox')) {
                        return;
                    }

                    event.preventDefault();
                }
            })
        }
    })();

    // Select or unselect all checkboxes at once
    (() => {
        const selectAllLinks = document.querySelectorAll('.multifile-download--select-all-container a');

        for (const selectAllLink of selectAllLinks) {
            selectAllLink.addEventListener('click', (event) => {
                event.preventDefault();
                const toggler = event.target;
                toggler.classList.toggle('selected');
                const checkBoxes = toggler.closest('.content-downloads').querySelectorAll('.multifile-download--list input.multifile-download--checkbox');

                for (const checkBox of checkBoxes) {
                    checkBox.checked = toggler.classList.contains('selected');
                }
            })
        }
    })();
});
