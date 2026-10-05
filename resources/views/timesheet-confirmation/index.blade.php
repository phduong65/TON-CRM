@extends('layouts.admin')

@section('title', 'Xác nhận công — ' . $employee->name)
@section('page-title', 'Xác nhận công')
@section('page-subtitle', 'Xác nhận bảng công của bạn theo kỳ')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    @include('timesheet-confirmations.partials.overview', ['confirmationMode' => 'employee'])
@endsection
