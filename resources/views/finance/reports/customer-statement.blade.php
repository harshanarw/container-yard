@extends('layouts.app')

@section('title', 'Customer Statement')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Customer Statement</li>
@endsection

@section('content')
    @include('finance.reports._statement', [
        'title'      => 'Customer Statement',
        'partyLabel' => 'Customer',
        'routeName'  => 'finance.reports.customer-statement',
    ])
@endsection
