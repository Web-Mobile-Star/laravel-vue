/**
 *  Copyright 2020 Aston University
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * First we will load all of this project's JavaScript dependencies.
 */

require('./bootstrap');

window.CohortChart = require('./cohort-progress-chart.js').default;
window.DownloadColumn = require('./download-table-column.js').default;

// .af-job-status - updates the inner text of the element to reflect the job status

$(".af-job-status").each((index, element) => {
    const id = element.dataset.afJobId;
    getEcho().private(`job.${id}`).listen('MavenBuildJobStatusUpdated', (e) => {
        if (e.status === -2) {
            // Running: add spinner as well
            element.innerHTML = e.statusString + ' <div class="fa fa-spinner fa-spin"></div>';
            element.classList.add('highlightChange');
        } else {
            element.innerHTML = e.statusString;
            element.classList.add('highlightChange');
        }
        setTimeout(function () {
            element.classList.remove('highlightChange');
        }, 4000);
    });
});

// .af-aborted-job-reload - refreshes the page if the job in data-af-job-id is aborted

$(".af-aborted-job-reload").each((index, element) => {
    const id = element.dataset.afJobId;
    getEcho().private(`job.${id}`).listen('MavenBuildJobStatusUpdated', (e) => {
        if (e.status === -3) {
            location.reload();
        }
    });
});

// .af-finished-job-reload - refreshes the page if the job in data-af-job-id is aborted, completed, or fails

$(".af-finished-job-reload").each((index, element) => {
    const id = element.dataset.afJobId;
    getEcho().private(`job.${id}`).listen('MavenBuildJobStatusUpdated', (e) => {
        if (e.status >= 0 || e.status === -3) {
            location.reload();
        }
    });
});

// .af-marks-reload - refreshes the page when the marking of the assessment submission in data-af-asub-id completes.

$(".af-marks-reload").each((index, element) => {
    const id = element.dataset.afAsubId;
    getEcho().private(`asub.${id}`).listen('SubmissionMarksUpdated', (e) => {
        location.reload();
    });
});

// .af-row-select-table: for the <table>
// .af-row-select-all:   for the checkbox in the thead
// .af-row-select:       for the checkbox in each tbody.tr

$(".af-row-select-table").each((i, e) => {
    let jqSelectAll = $(e).find('input.af-row-select-all');
    let jqSelectRow = $(e).find('input.af-row-select');

    jqSelectAll.on('change', (e) => {
        let checked = e.target.checked;
        jqSelectRow.each((i, e) => {
            let jqE = $(e);
            if (jqE.prop('checked') !== checked) {
                jqE.prop('checked', checked).change();
            }
        });
    });

    jqSelectRow.on('change', (e) => {
        let countUnchecked = jqSelectRow.filter(':not(:checked)').length;
        jqSelectAll.prop('checked', countUnchecked === 0);
    });

    $('tbody > tr').on('click', (e) => {
        if (e.target.tagName === 'TD' || e.target.tagName === 'TR') {
            $(e.currentTarget).find('.af-row-select').each((i, e) => {
                let jqE = $(e);
                jqE.prop('checked', !jqE.prop('checked')).change();
            });
        }
    });
});

// data-af-row-select-table: this <button> should only be enabled if at least
// one row in the table referenced by the included JQuery selector is selected

$("button[data-af-row-select-table]").each((i, e) => {
    let jqButton = $(e);
    let jqTable  = $(e.dataset.afRowSelectTable);

    function _updateButton() {
        let disabled = jqTable.find('input.af-row-select:checked').length === 0;
        if (disabled) {
            jqButton.attr('disabled', 'true');
        } else {
            jqButton.removeAttr('disabled');
        }
    }

    jqTable.find('input.af-row-select').on('change', _updateButton);
    $(window).on('pageshow', _updateButton);
});

// .af-check-enable-button: class for a checkbox which will enable the button selected by data-af-button
// when checked, and disable it otherwise. This is good for confirmation checkboxes about understanding
// certain conditions.

$("input.af-check-enable-button").each((i, e) => {
    let jqCheck = $(e);
    let jqButton = $(e.dataset.afButton);

    function _updateButton() {
        if (jqCheck[0].checked) {
            jqButton.removeAttr('disabled');
        } else {
            jqButton.attr('disabled', true);
        }
    }

    jqCheck.on('change', _updateButton);
});
