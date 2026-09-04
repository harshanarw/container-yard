<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Looked at this finding, and there is nothing to correct."
 *
 * See the migration for why findings need this at all, and why it is keyed on
 * the movement *and* the check rather than on the movement alone.
 */
class GateCheckReview extends Model
{
    protected $fillable = ['gate_movement_id', 'check', 'note', 'reviewed_by'];

    public function movement()
    {
        return $this->belongsTo(GateMovement::class, 'gate_movement_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The key a finding is looked up by: one movement, one check. */
    public function key(): string
    {
        return $this->gate_movement_id . ':' . $this->check;
    }
}
