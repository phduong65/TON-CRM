@extends('layouts.admin')

@section('title', 'Xác nhận công — ' . $employee->name)
@section('page-title', 'Xác nhận công')
@section('page-subtitle', 'Chi tiết bảng công và trạng thái xác nhận của nhân viên')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    @include('timesheet-confirmations.partials.overview', ['confirmationMode' => 'admin'])
@endsection
