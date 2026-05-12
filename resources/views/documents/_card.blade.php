<div class="col-6 col-md-4 col-lg-3" id="doc_col_{{ $doc->id }}">
    <div class="card shadow-sm h-100">
        @if($doc->isImage())
            <img src="{{ route('documents.preview', $doc) }}"
                 class="card-img-top object-fit-cover"
                 style="height:100px;" loading="lazy"
                 alt="{{ $doc->original_name }}">
        @else
            <div class="d-flex align-items-center justify-content-center bg-light" style="height:100px;">
                <i class="bi {{ $doc->icon() }} fs-1 {{ $doc->iconColor() }}"></i>
            </div>
        @endif
        <div class="card-body p-2">
            <div class="small fw-semibold text-truncate" title="{{ $doc->original_name }}">
                {{ $doc->original_name }}
            </div>
            <div class="text-muted" style="font-size:11px;">
                {{ $doc->formattedSize() }}
                @if($doc->label)
                    · <em>{{ $doc->label }}</em>
                @endif
            </div>
            <div class="text-muted" style="font-size:10px;">
                {{ $doc->created_at?->format('d M Y H:i') }}
                @if($doc->uploadedBy)
                    · {{ $doc->uploadedBy->name }}
                @endif
            </div>
        </div>
        <div class="card-footer p-1 d-flex gap-1">
            @if($doc->isPreviewable())
            <button class="btn btn-outline-primary btn-sm flex-fill dm-preview-btn"
                    data-url="{{ route('documents.preview', $doc) }}"
                    data-download="{{ route('documents.download', $doc) }}"
                    data-name="{{ $doc->original_name }}"
                    data-mime="{{ $doc->mime_type }}"
                    data-is-image="{{ $doc->isImage() ? '1' : '0' }}"
                    data-is-office="{{ $doc->isOffice() ? '1' : '0' }}">
                <i class="bi bi-eye"></i>
            </button>
            @endif
            <a href="{{ route('documents.download', $doc) }}"
               class="btn btn-outline-secondary btn-sm flex-fill" download>
                <i class="bi bi-download"></i>
            </a>
            <button class="btn btn-outline-danger btn-sm dm-delete-btn"
                    data-url="{{ route('documents.destroy', $doc) }}"
                    data-col="doc_col_{{ $doc->id }}">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</div>
