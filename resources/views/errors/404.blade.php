@extends('errors.layout')

@section('title', 'Page not found')

@section('content')
    <p class="code">404</p>
    <h1>Page not found</h1>
    <p>That URL is not on this Authzio site. Check the address, or head back to a known page.</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">Back to home</a>
        <a href="{{ url('/docs') }}" class="btn btn-ghost">Documentation</a>
        <a href="{{ url('/console') }}" class="btn btn-ghost">Open console</a>
    </div>
@endsection
