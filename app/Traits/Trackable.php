<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Trackable
{
    protected static function bootTrackable()
    {
        static::creating(function ($model) {
            if (Auth::check() && !isset($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !isset($model->updated_by)) {
                $model->updated_by = Auth::id();
            }
        });
    }
}