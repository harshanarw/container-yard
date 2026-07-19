{{--
    Guard Post Verification panel — the guard's captured photos and details for a
    single GuardCapture, shown on the gate form so the officer can verify against
    the box before recording the movement.

    Used two ways:
      • server-side @include on the queue-promote path ($guardCapture is loaded,
        $rescan = true so the OCR re-scan buttons show), and
      • rendered to HTML by YardController@guardPostCheck and injected over AJAX on
        the type / OCR-scan path ($rescan = false — the officer keyed the number
        themselves, so re-scanning the guard photo isn't offered there).

    Params: $guardCapture (required), $rescan (bool, default true).
--}}
@php
    /** @var \App\Models\GuardCapture $guardCapture */
    $rescan  = $rescan ?? true;
    $dir     = $guardCapture->direction;
    $suffix  = $dir === 'gate_out' ? 'Out' : '';
    $panelId = 'gpVerifyPanel' . $suffix;
    $bodyId  = 'gpPanelBody' . $suffix;

    $gpPhotos = [];
    if ($guardCapture->container_image_url) $gpPhotos[] = ['label' => 'Container', 'url' => $guardCapture->container_image_url, 'icon' => 'bi-box-seam',          'rescan' => 'container'];
    if ($guardCapture->plate_image_url)     $gpPhotos[] = ['label' => 'Plate',     'url' => $guardCapture->plate_image_url,     'icon' => 'bi-truck',             'rescan' => 'plate'];
    if ($guardCapture->nic_front_url)       $gpPhotos[] = ['label' => 'NIC Front', 'url' => $guardCapture->nic_front_url,       'icon' => 'bi-person-vcard',      'rescan' => null];
    if ($guardCapture->nic_back_url)        $gpPhotos[] = ['label' => 'NIC Back',  'url' => $guardCapture->nic_back_url,        'icon' => 'bi-person-vcard-fill', 'rescan' => null];
    if ($guardCapture->license_front_url)   $gpPhotos[] = ['label' => 'License',   'url' => $guardCapture->license_front_url,   'icon' => 'bi-card-text',         'rescan' => null];

    // Header accent follows the clearance state — the panel now surfaces
    // pending/hold/rejected captures too, not just cleared ones.
    $statusIcon = match($guardCapture->status) {
        'cleared'  => 'bi-shield-check text-success',
        'pending'  => 'bi-hourglass-split text-warning',
        'hold'     => 'bi-pause-circle text-warning',
        'rejected' => 'bi-x-octagon text-danger',
        default    => 'bi-shield text-secondary',
    };
@endphp
<div class="gp-panel mb-3" id="{{ $panelId }}">
    <div class="gp-panel-hdr" data-bs-toggle="collapse" data-bs-target="#{{ $bodyId }}"
         aria-expanded="true" aria-controls="{{ $bodyId }}" style="cursor:pointer;">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <i class="bi {{ $statusIcon }}" style="font-size:1rem;"></i>
            <span class="fw-semibold" style="font-size:.82rem;">Guard Post Verification</span>
            <span class="gp-ref-badge">{{ $guardCapture->reference_no }}</span>
            <span class="badge {{ $guardCapture->status_badge_class }}" style="font-size:.68rem;">{{ $guardCapture->status_label }}</span>
            <span class="gp-dir-badge {{ $dir === 'gate_in' ? 'gp-dir-in' : 'gp-dir-out' }}">
                <i class="bi {{ $dir === 'gate_in' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }}"></i>
                {{ $guardCapture->direction_label }}
            </span>
            <span class="text-muted ms-auto" style="font-size:.72rem;">
                Captured {{ $guardCapture->captured_at?->format('d M H:i') }}
                @if($guardCapture->capturedBy) · by {{ $guardCapture->capturedBy->full_name }}@endif
                @if($guardCapture->clearedBy) · Cleared by {{ $guardCapture->clearedBy->full_name }}@endif
            </span>
        </div>
        <i class="bi bi-chevron-down gp-panel-chevron"></i>
    </div>

    <div class="collapse show" id="{{ $bodyId }}">
        <div class="gp-panel-body">

            @if(count($gpPhotos))
            <div class="gp-photos-row">
                @foreach($gpPhotos as $photo)
                <div class="gp-thumb" onclick="gpOpenLightbox(this)"
                     data-gp-url="{{ $photo['url'] }}" data-gp-label="{{ $photo['label'] }}"
                     title="View {{ $photo['label'] }}">
                    <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}" loading="lazy">
                    <div class="gp-thumb-label">
                        <span><i class="bi {{ $photo['icon'] }} me-1"></i>{{ $photo['label'] }}</span>
                        @if($rescan && !empty($photo['rescan']))
                        <button type="button" class="gp-rescan-btn" title="Re-scan with OCR"
                                onclick="event.stopPropagation();gpRescan(this,'{{ $photo['url'] }}','{{ $photo['rescan'] }}')">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <div class="gp-data-row">
                @if($guardCapture->container_number)
                <div class="gp-data-cell">
                    <div class="gp-data-lbl"><i class="bi bi-box-seam me-1"></i>Container</div>
                    <div class="gp-data-val font-monospace">{{ $guardCapture->container_number }}</div>
                </div>
                @endif
                @if($guardCapture->iso_code)
                <div class="gp-data-cell">
                    <div class="gp-data-lbl"><i class="bi bi-tag me-1"></i>ISO Code</div>
                    <div class="gp-data-val font-monospace">{{ $guardCapture->iso_code }}</div>
                </div>
                @endif
                @if($guardCapture->vehicle_number)
                <div class="gp-data-cell">
                    <div class="gp-data-lbl"><i class="bi bi-truck me-1"></i>Vehicle</div>
                    <div class="gp-data-val">{{ $guardCapture->vehicle_number }}</div>
                </div>
                @endif
                @if($guardCapture->driver_name)
                <div class="gp-data-cell">
                    <div class="gp-data-lbl"><i class="bi bi-person me-1"></i>Driver</div>
                    <div class="gp-data-val">{{ $guardCapture->driver_name }}</div>
                </div>
                @endif
                @if($guardCapture->nic_number)
                <div class="gp-data-cell">
                    <div class="gp-data-lbl"><i class="bi bi-person-vcard me-1"></i>NIC</div>
                    <div class="gp-data-val font-monospace">{{ $guardCapture->nic_number }}</div>
                </div>
                @endif
                @if($guardCapture->driver_phone)
                <div class="gp-data-cell">
                    <div class="gp-data-lbl"><i class="bi bi-telephone me-1"></i>Phone</div>
                    <div class="gp-data-val">{{ $guardCapture->driver_phone }}</div>
                </div>
                @endif
            </div>

            @if($guardCapture->notes)
            <div class="gp-notes-row">
                <i class="bi bi-chat-left-text me-1 text-muted"></i>
                <span class="text-muted" style="font-size:.78rem;">Ops note:</span>
                <span style="font-size:.78rem;">{{ $guardCapture->notes }}</span>
            </div>
            @endif

        </div>
    </div>
</div>
