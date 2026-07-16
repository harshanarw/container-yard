<?php

namespace App\Http\Requests\Concerns;

trait StripsBlankDamages
{
    /**
     * Drop stray blank damage rows before validation. The survey form always
     * renders a default first row; on a washing-only (or otherwise damage-free)
     * survey that row is submitted empty and would otherwise persist as a blank
     * damage. A row counts as real if it carries any MR code or a description —
     * severity/quantity alone (which have defaults) don't make it meaningful.
     */
    protected function stripBlankDamages(): void
    {
        if (! is_array($this->damages)) {
            return;
        }

        $rows = array_values(array_filter($this->damages, fn ($d) => is_array($d) && (
            ! empty($d['location_code_id'])
            || ! empty($d['component_code_id'])
            || ! empty($d['damage_code_id'])
            || ! empty($d['repair_code_id'])
            || ! empty($d['responsibility_code_id'])
            || filled($d['description'] ?? null)
        )));

        $this->merge(['damages' => $rows]);
    }
}
