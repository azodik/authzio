@extends('errors.layout')

@section('title', 'Something went wrong')

@section('content')
    <p class="code">500</p>
    <h1>Something went wrong</h1>
    <p>Authzio hit an unexpected error. Try again in a moment. If it keeps happening, check the application logs or open an issue.</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">Back to home</a>
        <a href="{{ url('/console') }}" class="btn btn-ghost">Open console</a>
    </div>
@endsection
