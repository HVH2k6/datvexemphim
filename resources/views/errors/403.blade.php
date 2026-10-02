@extends('layouts.app')
@section('title', '403 - Không có quyền truy cập')
@section('content')
<div class="text-center py-5">
    <h1>403</h1>
    <p>{{ $message ?: 'Bạn không có quyền truy cập trang này.' }}</p>
    <a href="{{ \CommonHelper::url('/') }}" class="btn btn-primary">Về trang chủ</a>
</div>
@endsection
