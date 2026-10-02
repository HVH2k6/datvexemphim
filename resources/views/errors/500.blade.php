@extends('layouts.app')
@section('title', '500 - Lỗi máy chủ')
@section('content')
<div class="text-center py-5">
    <h1>500</h1>
    <p>{{ $message ?: 'Đã có lỗi xảy ra. Vui lòng thử lại sau.' }}</p>
    <a href="{{ \CommonHelper::url('/') }}" class="btn btn-primary">Về trang chủ</a>
</div>
@endsection
