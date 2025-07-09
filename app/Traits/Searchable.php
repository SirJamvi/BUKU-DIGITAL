<?php

namespace App\Traits;

trait Searchable
{
    public function scopeSearch($query, $searchTerm)
    {
        if (empty($searchTerm) || !property_exists($this, 'searchable')) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm) {
            foreach ($this->searchable as $field) {
                if (strpos($field, '.') !== false) {
                    // Pencarian relasi, contoh: 'category.name'
                    [$relation, $relatedField] = explode('.', $field);
                    $q->orWhereHas($relation, function ($subQuery) use ($relatedField, $searchTerm) {
                        $subQuery->where($relatedField, 'like', "%{$searchTerm}%");
                    });
                } else {
                    // Pencarian field biasa
                    $q->orWhere($field, 'like', "%{$searchTerm}%");
                }
            }
        });
    }
}