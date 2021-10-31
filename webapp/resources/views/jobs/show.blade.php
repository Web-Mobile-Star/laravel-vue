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
 * @var \App\ZipSubmission $job
 * @var \App\BuildResultFile[]|Illuminate\Pagination\LengthAwarePaginator $results
 * @var integer $backPage
 */
@endphp

@section('title'){{ __('Job :id', ['id' => $job->id]) }}@endsection

@section('content')
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2>{{ __('Details of Job #:jobid', ['jobid' => $job->id]) }}</h2>

                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                        <tr>
                            <th scope="row">{{ __('File') }}</th>
                            <td><a href="{{ route('jobs.download', $job->id) }}">{{ $job->filename }}</a></td>
                        </tr>
                        <tr>
                            <th scope="row">{{ __('Author') }}</th>
                            <td>{{ $job->user->name }} - <a href="mailto:{{ $job->user->email }}">{{ $job->user->email }}</a></td>
                        </tr>
                        @if($job->user_id != $job->submitter_user_id)
                            <tr>
                                <th scope="row">{{ __('Submitter') }}</th>
                                <td>{{ $job->submitter->name }} - <a href="mailto:{{ $job->submitter->email }}">{{ $job->submitter->email }}</a></td>
                            </tr>
                        @endif
                        <tr>
                            <th scope="row">{{ __('Status') }}</th>
                            <x-job-status-cell :job="$job" :reloadOnFinish="true"/>
                        </tr>
                        <tr>
                            <th scope="row">{{ __('Created at') }}</th>
                            <td>{{ $job->created_at }}</td>
                        </tr>
                        <tr>
                            <th scope="row">{{ __('Updated at') }}</th>
                            <td>{{ $job->updated_at }}</td>
                        </tr>
                        @if($job->sha256 != \App\ZipSubmission::SHA256_PENDING)
                            <tr>
                                <th scope="row">{{ __('SHA-256') }}</th>
                                <td>{{ $job->sha256 }}</td>
                            </tr>
                        @endif
                        @if($job->modelSolution)
                            <tr>
                                <th scope="row">{{ __('Model solution for') }}</th>
                                <td>
                                    <x-teaching-module-item-link :item="$job->modelSolution->assessment->usage"/>
                                    - <x-model-solution-version-description :modelSolution="$job->modelSolution"/>
                                </td>
                            </tr>
                        @endif
                        @if($job->assessment)
                            <tr>
                                <th scope="row">{{ __('Submission for') }}</th>
                                <td><x-teaching-module-item-link :item="$job->assessment->assessment->usage"/></td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <div class="text-center">
                    @can('delete', $job)
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">{{ __('Delete') }}</button>
                    @endcan
                    @if($backPage)
                        <a href="{{ route('jobs.index', ['page' => $backPage]) }}" class="btn btn-secondary">{{ __('Back') }}</a>
                    @endif
                </div>

                @can('delete', $job)
                    <x-modal-confirm :id="'deleteModal'" :title="__('Delete Job?')">
                        <x-slot name="body">
                            {{ __('Do you want to delete Job #:jobid and all its files?', ['jobid' => $job->id]) }}
                        </x-slot>
                        <form class="form-inline custom-control-inline mr-0"
                              action="{{ route('jobs.destroy', $job->id) }}"
                              method="POST">
                            @csrf
                            @method('DELETE')
                            <input class="btn btn-danger" type="submit" value="{{ __('Delete') }}"/>
                        </form>
                    </x-modal-confirm>
                @endcan

                @if($results->isEmpty())
                    <div class="alert alert-warning mt-2" role="alert">
                        {{ __('This job has not produced any result files yet') }}
                    </div>
                @else
                    <h3>{{ __('Result files') }}</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th scope="col">{{ __('Source') }}</th>
                                <th scope="col">{{ __('Filename') }}</th>
                                <th scope="col">{{ __('MIME type') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($results as $r)
                                <tr>
                                    <td>{{ $r->source }}</td>
                                    <td><a href="{{ $r->url($job) }}">{{ $r->originalPath }}</a></td>
                                    <td>{{ $r->mimeType }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $results->links() }}
                @endif
            </div>
        </div>
@endsection
