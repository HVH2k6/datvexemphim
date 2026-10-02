@extends('layouts.app')
@section('title', '404 - Không tìm thấy trang')
@section('content')
<div class="text-center py-5">
    <h1>404</h1>
    <p>{{ $message ?: 'Không tìm thấy trang bạn yêu cầu.' }}</p>
    <a href="{{ \CommonHelper::url('/') }}" class="btn btn-primary">Về trang chủ</a>
</div>
@endsection
