@php
    /**
     *  Copyright 2020-2021 Aston University
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

@extends('layouts.app')

@php
/**
 * @var \App\ZipSubmission[]|Illuminate\Pagination\LengthAwarePaginator $jobs
 * @var integer $page
 */
@endphp

@section('title', __('Jobs'))

@section('content')
        <div class="row justify-content-center">
            <div class="col-md-8">
                @can('create', \App\ZipSubmission::class)
                    <a role="button" class="btn btn-primary" href="{{ route('jobs.create') }}">{{ __('Create Raw Job') }}</a>
                @endcan

                @if($jobs->count() > 0)
                    @can(\App\Policies\ZipSubmissionPolicy::DELETE_ANY_PERMISSION)
                            <button id="deleteManyModalButton" type="button" class="btn btn-danger" data-toggle="modal"
                                    data-target="#deleteManyModal" data-af-row-select-table="#jobsTable">
                                {{ __('Delete selected') }}
                            </button>

                            <x-modal-confirm :id="'deleteManyModal'" :title="'Delete checked submissions?'">
                                <x-slot name="body">
                                    {{ __('Do you wish to delete the checked submissions?') }}
                                </x-slot>
                                <form class="form-inline custom-control-inline mr-0"
                                      action="{{ route('jobs.destroyMany') }}"
                                      method="POST" id="deleteManyForm">
                                    @csrf
                                    @method('DELETE')
                                    <input class="btn btn-danger" type="submit" value="{{ __('Delete') }}"/>
                                </form>
                            </x-modal-confirm>
                        @endcan

                    <table class="table table-hover mt-4 af-row-select-table" id="jobsTable">
                        <thead>
                            <th scope="col"><input type="checkbox" class="af-row-select-all" /></th>
                            <th scope="col">{{ __('Filename') }}</th>
                            <th scope="col">{{ __('Created') }}</th>
                            <th scope="col">{{ __('Author') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                        </thead>
                        <tbody>
                        @foreach ($jobs as $job)
                            <tr>
                                <td><input type="checkbox" class="af-row-select" value="{{ $job->id }}"/></td>
                                <td><a href="{{ route('jobs.show', ['job' => $job->id, 'backPage' => $page]) }}">{{ $job->filename }}</a></td>
                                <td>{{ $job->created_at }}</td>
                                <td>{{ $job->user->name }}</td>
                                <x-job-status-cell :job="$job"/>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    {{ $jobs->links() }}
                @else
                    <div class="alert alert-warning mt-2" role="alert">
                        {{ __('No jobs have been submitted yet!') }}
                    </div>
                @endif
            </div>
        </div>
@endsection

@section('extrajs')
    <script type="application/javascript">
        $('#deleteManyForm').on('submit', () => {
            $('#jobsTable input.af-row-select:checked').each((i, e) => {
                $('<input type="hidden">').attr({
                    name: 'jobIDs[]',
                    value: e.value
                }).appendTo('#deleteManyForm');
            });
            return true;
        });
    </script>
@endsection
