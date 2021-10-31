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

@section('navbar')
    <nav
        class="navbar navbar-expand-md navbar-light @if( \Illuminate\Support\Facades\Auth::user() && \Illuminate\Support\Facades\Auth::user()->isAdmin()) bg-warning @else bg-white @endif shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                {{ config('app.name', 'Laravel') }}
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent" aria-expanded="false"
                    aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left Side Of Navbar -->
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('modules.index') }}">{{ __('Modules') }}</a>
                    </li>
                    @can('viewAny', \App\ZipSubmission::class)
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('jobs.index') }}">{{ __('Jobs') }}</a>
                        </li>
                    @endcan
                </ul>

                <!-- Right Side Of Navbar -->
                <ul class="navbar-nav ml-auto">
                    <!-- Authentication Links -->
                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                            </li>
                        @endif
                    @else
                        <li class="nav-item dropdown">
                            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                {{ Auth::user()->name }} <span class="caret"></span>
                            </a>

                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                @hasrole(\App\User::SUPER_ADMIN_ROLE)
                                @if(\App\User::isAdminModeEnabled())
                                    <a class="dropdown-item" href="{{ route('disableAdmin') }}"
                                       onclick="event.preventDefault(); document.getElementById('disableAdmin-form').submit();">
                                        {{ __('Disable Admin Mode') }}
                                    </a>
                                    <form id="disableAdmin-form" action="{{ route('disableAdmin') }}" method="POST"
                                          style="display: none;">
                                        @csrf
                                    </form>
                                @else
                                    <a class="dropdown-item" href="{{ route('disableAdmin') }}"
                                       onclick="event.preventDefault(); document.getElementById('enableAdmin-form').submit();">
                                        {{ __('Enable Admin Mode') }}
                                    </a>
                                    <form id="enableAdmin-form" action="{{ route('enableAdmin') }}" method="POST"
                                          style="display: none;">
                                        @csrf
                                    </form>
                                @endif
                                @endrole

                                <a class="dropdown-item" href="{{ route('tokens.show') }}">{{ __('Tokens') }}</a>

                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    {{ __('Logout') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                      style="display: none;">
                                    @csrf
                                </form>
                            </div>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>
@endsection

@section('main')
    <main class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                @if (session('status'))
                    <div class="alert alert-success">
                        <button type="button" class="ml-1 close" data-dismiss="alert">x</button>
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('warning'))
                    <div class="alert alert-warning">
                        <button type="button" class="ml-1 close" data-dismiss="alert">x</button>
                        {{ session('warning') }}
                    </div>
                @endif
            </div>
            @yield('content')
        </div>
    </main>
@endsection
