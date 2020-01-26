<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW" />

    <title>@yield('pageTitle', $pageTitle)</title>

    <link rel="stylesheet" href="/bower_components/bootstrap/dist/css/bootstrap.min.css" />
    {{--<link rel="stylesheet" href="/bower_components/bootstrap-additions/dist/bootstrap-additions.min.css" />--}}

    <link rel="stylesheet" href="/bower_components/select2/dist/css/select2.min.css" />
    <link rel="stylesheet" href="/css/theme/select2.custom.css" />
    {{--<link rel="stylesheet" href="/bower_components/select2-bootstrap-css/select2-bootstrap.min.css" />--}}

    <link rel="stylesheet" href="/bower_components/font-awesome/web-fonts-with-css/css/fontawesome-all.min.css" />

    <link rel="stylesheet" href="{{ mix("css/dist/dashboard.css") }}" />

    @stack('stylesheets')

    <link rel="shortcut icon" href="/favicon.ico"/>

    @stack('meta')

    @include('oxygen::partials.tracking')

    <head>
<body>

<div id="dashboard-wrapper">

    <div id="dashboard-home">

        <div class="account-header">
            {{--<div class="container">--}}

                <nav class="navbar navbar-expand-md navbar-dark navbar-app">
                    {{--<div class="container-fluid">--}}
                        <span class="btn btn-header-menu js-toggle-right-sidebar">
                            <i class="fas fa-bars"></i>
                        </span>

                        <a class="navbar-brand" href="{{ url('/') }}">
                            {{ config('app.name', 'Laravel') }}
                        </a>
                        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <div class="collapse navbar-collapse" id="navbarSupportedContent">
                            <!-- Left Side Of Navbar -->
                            <ul class="navbar-nav mr-auto">

                            </ul>

                            <!-- Right Side Of Navbar -->
                            <ul class="navbar-nav ml-auto">
                                <!-- Authentication Links -->
                                @guest
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                    </li>
                                    <li class="nav-item">
                                        @if (Route::has('register'))
                                            <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                        @endif
                                    </li>
                                @else
                                    <li class="nav-item dropdown">
                                        <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                            {{ Auth::user()->name }} <span class="caret"></span>
                                        </a>

                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                            <a class="dropdown-item" href="{{ route('account.profile') }}">
                                                {{ __('My Profile') }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="{{ route('account.email') }}">Edit Email</a>
                                            <a class="dropdown-item" href="{{ route('account.password') }}">Edit Password</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="{{ route('logout') }}">
                                                {{ __('Logout') }}
                                            </a>
                                        </div>
                                    </li>
                                @endguest
                            </ul>
                        </div>
                    {{--</div>--}}
                </nav>
        </div>

        @yield('page-container')

        @include('oxygen::partials.footer')

    </div>

</div>

<script src="/bower_components/jquery/dist/jquery.min.js"></script>
<script src="/bower_components/popper.js/dist/umd/popper.min.js"></script>
<script src="/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script src="/bower_components/typeahead.js/dist/typeahead.bundle.min.js"></script>
<script src="/bower_components/select2/dist/js/select2.full.min.js"></script>
<script src="/bower_components/jquery-validation/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function() {
	    // confirmation
	    $('.js-confirm').on('submit', function (e) {
		    return confirm('Are you sure?');
	    });

	    // trigger tooltips
        $('.js-tooltip, [data-toggle="tooltip"]').tooltip();

	    // collapse sidebar
	    $('.js-toggle-right-sidebar').on('click', function () {
		    $('#sidebar').toggle('collapsed');
	    });
    });
</script>

@stack('js')

{{-- For non-PJAX type requests --}}
@if (!request()->header('X-PJAX'))
    @stack('scripts')
@endif

</body>
</html>