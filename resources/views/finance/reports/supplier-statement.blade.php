@extends('layouts.app')

@section('title', 'Supplier Statement')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Supplier Statement</li>
@endsection

@section('content')
    @include('finance.reports._statement', [
        'title'      => 'Supplier Statement',
        'partyLabel' => 'Supplier / Contact',
        'routeName'  => 'finance.reports.supplier-statement',
    ])
@endsection
