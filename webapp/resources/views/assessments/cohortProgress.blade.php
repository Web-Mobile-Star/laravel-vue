@php
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
@endphp

@extends('layouts.base')

@section('title', __('Cohort Progress - :title', ['title' => $assessment->usage->title]))

@php
    /**
     * @var \App\TeachingModule $module
     * @var \App\Assessment $assessment
     */
@endphp

@section('main')
    <div id="chart" class="cohort-progress-chart"></div>

    <div class="modal fade cohort-progress-chart" id="studentModal" tabindex="-1" role="dialog" aria-labelledby="studentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title overflow-auto">Class::test::status = count</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="application/javascript">
        function ChartComponent(url) {
            let data = null;

            let redraw = function() {
                let chart = new CohortChart("#chart", "#studentModal", 1280);
                chart.draw(data);
            };

            let fetchData = function(callback) {
                axios.get(url)
                     .then(callback)
                     .catch(err => { console.log(err); });
            };

            $(window).on('load', function() {
                fetchData((res) => {
                    data = res.data;
                    redraw();
                });
            });

            $(window).on('resize', function() {
                if (data !== null) {
                    $("#chart").empty();
                    redraw();
                }
            });
        }

        $('#studentModal').on('show.bs.modal', function (event) {
            let clickedData   = event.relatedTarget.parentElement.dataset;
            let statusURLTemplate = '{{ route('modules.assessments.jsonClassTestStatus', ['module' => $module->id, 'assessment' => $assessment->id, 'className' => 'CLASS_NAME', 'testName' => 'TEST_NAME', 'status' => 'TEST_STATUS' ]) }}';
            let urlRequest = statusURLTemplate
                .replace('CLASS_NAME', clickedData.class)
                .replace('TEST_NAME', clickedData.test)
                .replace('TEST_STATUS', clickedData.result);

            let shortClassParts = clickedData.class.split('.');
            let shortClassName = shortClassParts[shortClassParts.length - 1];
            let jqModalTitle = $(this).find('.modal-title');
            jqModalTitle.text(`${shortClassName}::${clickedData.test}::${clickedData.result}`);

            let jqModalBody = $(this).find('.modal-body');
            jqModalBody.empty();
            jqModalBody.html('Loading data...');

            axios.get(urlRequest)
                 .then((res) => {
                     let jobURLTemplate = '{{ route('modules.submissions.show', ['module' => $module->id, 'submission' => 'SUB_ID']) }}';

                     jqModalBody.empty();
                     let ulStudents = document.createElement('ul');
                     jqModalBody.append(ulStudents);

                     res.data.forEach(item => {
                         let liStudent = document.createElement('li');
                         ulStudents.appendChild(liStudent);

                         if (item.submission_id) {
                             let aStudent = document.createElement('a');
                             aStudent.href = jobURLTemplate.replace('SUB_ID', item.submission_id);
                             aStudent.text = item.user_name;
                             aStudent.target = '_blank';
                             liStudent.appendChild(aStudent);
                         } else {
                             liStudent.innerText = item.user_name;
                         }
                     });
                 })
                 .catch(console.log);
        });

        ChartComponent('{{ route('modules.assessments.jsonProgressChart', ['module' => $module->id, 'assessment' => $assessment->id]) }}');
    </script>
@endpush
