<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryHighlightItem extends Model
{
    protected $guarded = [];

    public function highlight(): BelongsTo
    {
        return $this->belongsTo(StoryHighlight::class, 'story_highlight_id');
    }
}
