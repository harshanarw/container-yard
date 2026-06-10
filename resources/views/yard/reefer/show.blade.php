@extends('layouts.app')
@section('title', 'Reefer Session')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('yard.reefer.index') }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-semibold">
            <span class="font-monospace">{{ $session->container?->container_no }}</span>
            <span class="ms-2 badge {{ $session->status_badge_class }}">{{ ucfirst($session->status) }}</span>
        </h4>
        <p class="text-muted small mb-0">{{ $session->customer?->name }}</p>
    </div>
    <div class="ms-auto d-flex gap-2">
        @if($session->isPending())
            <a href="{{ route('yard.reefer.plug-in', $session) }}" class="btn btn-success btn-sm">
                <i class="bi bi-plug-fill me-1"></i>Record Plug-In
            </a>
        @elseif($session->isActive())
            <a href="{{ route('yard.reefer.plug-out', $session) }}" class="btn btn-danger btn-sm">
                <i class="bi bi-plug me-1"></i>Record Plug-Out
            </a>
        @endif
    </div>
</div>


<div class="row g-4">
    {{-- Session details --}}
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Session Details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-5 text-muted">Container</dt>
                    <dd class="col-sm-7 font-monospace fw-bold">{{ $session->container?->container_no }}</dd>
                    <dt class="col-sm-5 text-muted">Equipment Type</dt>
                    <dd class="col-sm-7">{{ $session->container?->equipmentType?->dropdown_label ?? '—' }}</dd>
                    <dt class="col-sm-5 text-muted">Customer</dt>
                    <dd class="col-sm-7">{{ $session->customer?->name }}</dd>
                    <dt class="col-sm-5 text-muted">Gate-In Movement</dt>
                    <dd class="col-sm-7">#{{ $session->gate_movement_id }}</dd>
                    <dt class="col-sm-5 text-muted">Plug-In</dt>
                    <dd class="col-sm-7">{{ $session->plug_in_at?->format('d M Y H:i') ?? '—' }}</dd>
                    <dt class="col-sm-5 text-muted">Plug-Out</dt>
                    <dd class="col-sm-7">{{ $session->plug_out_at?->format('d M Y H:i') ?? '—' }}</dd>
                    @if($session->plug_in_at)
                    <dt class="col-sm-5 text-muted">Duration</dt>
                    <dd class="col-sm-7">
                        {{ round($session->totalHours(), 1) }}h / {{ $session->totalDays() }} day(s)
                        @if(!$session->plug_out_at)
                            <span class="text-muted">(live)</span>
                        @endif
                    </dd>
                    @endif
                    @if($session->set_temperature)
                    <dt class="col-sm-5 text-muted">Set Temp</dt>
                    <dd class="col-sm-7">{{ $session->set_temperature }}°C</dd>
                    @endif
                    <dt class="col-sm-5 text-muted">Status</dt>
                    <dd class="col-sm-7"><span class="badge {{ $session->status_badge_class }}">{{ ucfirst($session->status) }}</span></dd>
                    @if($session->notes)
                    <dt class="col-sm-5 text-muted">Notes</dt>
                    <dd class="col-sm-7">{{ $session->notes }}</dd>
                    @endif
                    <dt class="col-sm-5 text-muted">Created by</dt>
                    <dd class="col-sm-7">{{ $session->createdBy?->name ?? '—' }}</dd>
                    <dt class="col-sm-5 text-muted">Created at</dt>
                    <dd class="col-sm-7">{{ $session->created_at?->format('d M Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Temperature log --}}
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Temperature Log ({{ $session->tempLogs->count() }})</span>
                @if($session->isActive())
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addTempLogModal">
                    <i class="bi bi-plus-lg"></i> Add Log
                </button>
                @endif
            </div>
            @if($session->tempLogs->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Logged At</th>
                            <th>Set °C</th>
                            <th>Return °C</th>
                            <th>Supply °C</th>
                            <th>Humidity %</th>
                            <th>By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($session->tempLogs as $log)
                        <tr>
                            <td>{{ $log->logged_at->format('d M H:i') }}</td>
                            <td>{{ $log->set_temperature ?? '—' }}</td>
                            <td>{{ $log->return_temperature ?? '—' }}</td>
                            <td>{{ $log->supply_temperature ?? '—' }}</td>
                            <td>{{ $log->humidity_pct ? $log->humidity_pct.'%' : '—' }}</td>
                            <td>{{ $log->loggedBy?->name ?? '—' }}</td>
                            <td>
                                <form action="{{ route('yard.reefer.temp-log.destroy', [$session, $log]) }}" method="POST"
                                      onsubmit="return confirm('Remove this log entry?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body text-center text-muted py-3">
                <i class="bi bi-thermometer fs-3 d-block mb-1 opacity-25"></i>
                No temperature logs yet.
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Temp Log Modal --}}
@if($session->isActive())
<div class="modal fade" id="addTempLogModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('yard.reefer.temp-log.store', $session) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Temperature Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Logged At <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="logged_at" class="form-control" required
                                   value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Set Temperature (°C)</label>
                            <input type="number" name="set_temperature" class="form-control" step="0.1" min="-50" max="40">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Return Temperature (°C)</label>
                            <input type="number" name="return_temperature" class="form-control" step="0.1" min="-50" max="40">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Supply Temperature (°C)</label>
                            <input type="number" name="supply_temperature" class="form-control" step="0.1" min="-50" max="40">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Humidity (%)</label>
                            <input type="number" name="humidity_pct" class="form-control" step="0.1" min="0" max="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Log</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
