{{--
    Horizontal scrolling help for the wide report grids.

    Both weekly reports are as wide as the range is long, and the only scrollbar
    is at the very bottom of a sheet that can run to two hundred rows. A reader
    at the top of the page has no way to reach week five without scrolling down,
    dragging sideways, and scrolling back up.

    Two additions, both on any element marked `data-xscroll`:

      1. A second scrollbar directly above the grid, kept in sync with it.
      2. Click-and-drag panning, so the grid can be pushed sideways by hand.

    Applies to whatever the page marks; the views need no other markup.
--}}
@once
@push('styles')
<style>
    /* The mirror bar. Height is enough to render a scrollbar and nothing else. */
    .xscroll-bar { overflow-x: auto; overflow-y: hidden; }
    .xscroll-bar > div { height: 1px; }

    /* Only offer the grab cursor once the JS has confirmed there is somewhere to
       drag to — a grabbable-looking grid that does not move is worse than one
       that never invited the gesture. */
    .xscroll-drag { cursor: grab; }
    .xscroll-drag.is-dragging { cursor: grabbing; user-select: none; }

    @media print {
        .xscroll-bar { display: none !important; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /** Movement, in px, before a press becomes a drag rather than a click. */
    var DRAG_THRESHOLD = 4;

    function enhance(box) {
        if (box.dataset.xscrollReady) return;
        box.dataset.xscrollReady = '1';

        // ── 1. The mirror scrollbar above the grid ──────────────────────────
        var bar   = document.createElement('div');
        var inner = document.createElement('div');
        bar.className = 'xscroll-bar';
        bar.appendChild(inner);
        box.parentNode.insertBefore(bar, box);

        // Guard against the echo: setting scrollLeft on one fires the other's
        // scroll event, which would set it back and fight the user's drag.
        var syncing = false;
        function mirror(from, to) {
            return function () {
                if (syncing) return;
                syncing = true;
                to.scrollLeft = from.scrollLeft;
                requestAnimationFrame(function () { syncing = false; });
            };
        }
        bar.addEventListener('scroll', mirror(bar, box));
        box.addEventListener('scroll', mirror(box, bar));

        function measure() {
            inner.style.width = box.scrollWidth + 'px';
            var overflows = box.scrollWidth > box.clientWidth + 1;
            bar.style.display = overflows ? '' : 'none';
            box.classList.toggle('xscroll-drag', overflows);
        }
        measure();

        if (window.ResizeObserver) {
            new ResizeObserver(measure).observe(box);
            if (box.firstElementChild) new ResizeObserver(measure).observe(box.firstElementChild);
        }
        window.addEventListener('resize', measure);

        // ── 2. Drag to pan ──────────────────────────────────────────────────
        var startX = 0, startLeft = 0, pressed = false, dragging = false;

        box.addEventListener('pointerdown', function (e) {
            // Primary button only, and never on something the reader is trying
            // to use: a link, a button, or a form control inside the grid.
            if (e.button !== 0) return;
            if (e.target.closest('a, button, input, select, textarea, label')) return;
            if (box.scrollWidth <= box.clientWidth + 1) return;

            pressed   = true;
            dragging  = false;
            startX    = e.clientX;
            startLeft = box.scrollLeft;
        });

        box.addEventListener('pointermove', function (e) {
            if (!pressed) return;

            var dx = e.clientX - startX;

            // Below the threshold this is still a click or the start of a text
            // selection, and stealing it would make the grid unselectable.
            if (!dragging && Math.abs(dx) < DRAG_THRESHOLD) return;

            if (!dragging) {
                dragging = true;
                box.classList.add('is-dragging');
                if (box.setPointerCapture) { try { box.setPointerCapture(e.pointerId); } catch (err) {} }
            }

            box.scrollLeft = startLeft - dx;
            e.preventDefault();
        });

        function release(e) {
            if (!pressed) return;
            pressed = false;

            if (dragging) {
                dragging = false;
                box.classList.remove('is-dragging');
                if (box.releasePointerCapture && e && e.pointerId !== undefined) {
                    try { box.releasePointerCapture(e.pointerId); } catch (err) {}
                }
                // Swallow the click that ends a drag, so releasing over a cell
                // does not also activate whatever is under the pointer.
                box.addEventListener('click', function swallow(ev) {
                    ev.stopPropagation();
                    ev.preventDefault();
                    box.removeEventListener('click', swallow, true);
                }, true);
            }
        }

        box.addEventListener('pointerup', release);
        box.addEventListener('pointercancel', release);
        box.addEventListener('pointerleave', release);
    }

    function init() {
        document.querySelectorAll('[data-xscroll]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush
@endonce
