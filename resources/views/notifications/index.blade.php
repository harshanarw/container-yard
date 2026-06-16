@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h5>
    @if(auth()->user()->unreadNotifications()->exists())
    <form method="POST" action="{{ route('notifications.readAll') }}">
        @csrf
        <button class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-check2-all me-1"></i>Mark all as read
        </button>
    </form>
    @endif
</div>

<div class="card shadow-sm">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
        @php
            $type  = $n->data['type']  ?? 'info';
            $title = $n->data['title'] ?? 'Notification';
            $body  = $n->data['body']  ?? '';
            $url   = $n->data['url']   ?? null;
            $actor = $n->data['actor'] ?? null;
            $icons = ['info'=>'bi-info-circle-fill text-primary','success'=>'bi-check-circle-fill text-success',
                      'warning'=>'bi-exclamation-triangle-fill text-warning','danger'=>'bi-exclamation-circle-fill text-danger'];
            $bgs   = ['info'=>'bg-primary-subtle','success'=>'bg-success-subtle',
                      'warning'=>'bg-warning-subtle','danger'=>'bg-danger-subtle'];
            $read  = $n->read_at !== null;
        @endphp
        <div class="list-group-item list-group-item-action {{ $read ? '' : 'fw-semibold' }}"
             style="{{ $read ? '' : 'background:#f8f9ff;' }}">
            <div class="d-flex gap-3 align-items-start">
                <span class="avatar-sm {{ $bgs[$type] ?? 'bg-primary-subtle' }} flex-shrink-0" style="margin-top:2px;">
                    <i class="bi {{ $icons[$type] ?? 'bi-info-circle-fill text-primary' }}"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <span style="font-size:.9rem;">{{ $title }}</span>
                        <span class="text-muted ms-3 flex-shrink-0" style="font-size:.75rem;">{{ $n->created_at->diffForHumans() }}</span>
                    </div>
                    @if($body)
                    <div class="text-muted fw-normal mt-1" style="font-size:.82rem;">{{ $body }}</div>
                    @endif
                    @if($actor)
                    <div class="fw-semibold mt-1" style="font-size:.75rem;color:#6366f1;">
                        <i class="bi bi-person-fill me-1"></i>{{ $actor }}
                    </div>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-shrink-0 align-items-start" style="margin-top:2px;">
                    @if($url)
                    <a href="{{ $url }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem;">View</a>
                    @endif
                    @if(!$read)
                    <form method="POST" action="{{ route('notifications.markRead', $n->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-link p-0 text-muted" title="Mark read" style="font-size:.75rem;">
                            <i class="bi bi-check2"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="list-group-item text-center py-5 text-muted">
            <i class="bi bi-bell-slash d-block fs-2 mb-2"></i>
            No notifications yet.
        </div>
        @endforelse
    </div>
</div>

@if($notifications->hasPages())
<div class="mt-3">{{ $notifications->links() }}</div>
@endif
@endsection
