@section('content')
    <div class="all-items">
        <h1 class="page-title">{{ $pageTitle }}</h1>

        <div class="page-main-actions">
            @yield('breadcrumbs')
            @yield('pageMainActions')
        </div>

        @if(count($allItems))
            <table class="table table-hover">
                {{ lotus()->tableHeader($tableHeader) }}
                <tbody>
                @parent
                </tbody>
            </table>
        @else
            {{ lotus()->emptyStatePanel() }}
        @endif

        {{ lotus()->pageNumbers($allItems) }}

        {{-- Display a page summary --}}
        @if (!empty($__env->yieldContent('pageSummary')))
            <div>
                @yield('pageSummary')
            </div>
        @endif
    </div>
@stop