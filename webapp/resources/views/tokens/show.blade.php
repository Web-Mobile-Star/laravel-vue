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

@extends('layouts.app')

@php
    /**
     * @var \Laravel\Sanctum\PersonalAccessToken[] $userTokens
     * @var \Carbon\Carbon[] expirationDates
     */
 @endphp

@section('title'){{ __('Personal Access Tokens') }}@endsection

@section('content')
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2>{{ __('Personal Access Tokens') }}</h2>

                @if($userTokens->isEmpty())
                    <div class="alert alert-warning mt-2" role="alert">
                        {{ __('No tokens have been created for this user account yet.') }}
                    </div>
                @else
                    <table class="table table-hover af-align-middle-cells">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('Name') }}</th>
                            <th scope="col">{{ __('Expiration') }}</th>
                            <th scope="col">{{ __('Last used') }}</th>
                            <th scope="col">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($userTokens as $t)
                            <tr>
                                <td>{{ $t->name }}</td>
                                <td>{{ $expirationDates[$loop->index] }}</td>
                                <td>{{ $t->last_used_at }}</td>
                                <td>
                                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#revokeModal{{ $t->id }}">{{ __('Revoke') }}</button>
                                    <x-modal-confirm :id="'revokeModal' . $t->id" :title="__('Revoke Token?')">
                                        <x-slot name="body">
                                            {{ __('Do you want to revoke token :name?', ['name' => $t->name]) }}
                                        </x-slot>
                                        <form class="form-inline custom-control-inline mr-0"
                                              action="{{ route('tokens.destroy', $t->id) }}"
                                              method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <input class="btn btn-danger" type="submit" value="{{ __('Revoke') }}"/>
                                        </form>
                                    </x-modal-confirm>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

@endsection
