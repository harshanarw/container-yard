@props(['movement', 'chain' => false])

{{--
    Who is at the gate for this movement.

    One component, because ten screens were each reading `$movement->customer`
    and getting the *visit* customer — right for every ordinary movement, and
    wrong for a rental release, where the box goes out with a renter while the
    visit stays the shipping line's. Each was patched separately or not at all,
    and the eleventh would have drifted too.

    On an ordinary movement the holder and the visit customer are the same
    party, so this renders exactly what those screens rendered before.

    `:chain` adds the jobs the movement sits under — the line's stay, the yard's
    lease, the rent job — for the screens where reconstructing what happened is
    the point.
--}}
<span class="d-inline-block">
    <span class="fw-semibold">{{ $movement->holdingParty()?->name ?? '-' }}</span>

    @if($movement->heldByAnotherParty())
        <span class="badge bg-primary-subtle text-primary border ms-1" style="font-size:.66rem;">On hire</span>
        <span class="d-block text-muted" style="font-size:.72rem;">
            from {{ $movement->customer?->name }}
        </span>
    @endif

    @if($chain)
        @php $jobs = $movement->jobChain(); @endphp
        @if(count($jobs) > 1)
            <span class="d-block mt-1" style="font-size:.72rem;">
                @foreach($jobs as $job)
                    <span class="d-block text-muted">
                        {{ $job['label'] }}:
                        <span class="font-monospace">{{ $job['job_no'] }}</span>
                        @if($job['party']) · {{ $job['party'] }}@endif
                    </span>
                @endforeach
            </span>
        @endif
    @endif
</span>
