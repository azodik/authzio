@extends('errors.layout')

@section('title', 'Forbidden')

@section('content')
    <p class="code">403</p>
    <h1>You don’t have access</h1>
    <p>This page or action isn’t available for your account. Sign in with a different user, or return home.</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">Back to home</a>
        <a href="{{ url('/console/login') }}" class="btn btn-ghost">Console sign in</a>
    </div>
@endsection
