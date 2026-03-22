<?php

namespace App\Models\Traits;

use App\Models\StructureIncrementor;

trait BelongsToStructure
{
    protected static function bootBelongsToStructure(): void
    {
        static::creating(function ($model) {
            // 1. Auto-fill structure_id from the authenticated user (skip if model opts out)
            $skip = property_exists($model, 'skipStructureId') && $model->skipStructureId;
            if (!$skip && empty($model->structure_id) && $user = auth()->user()) {
                $model->structure_id = $user->structure_id;
            }

            // 2. Auto-generate code from the StructureIncrementor for this model type
            //    Each model declares: public string $codeField = 'ctg_code';
            $codeField  = $model->codeField ?? null;
            $modelClass = class_basename($model);

            if ($codeField && empty($model->{$codeField})) {
                // Allow a model to dynamically resolve its incrementor key
                // by defining: public function resolveIncrementorModel(): string
                $incrementorKey = method_exists($model, 'resolveIncrementorModel')
                    ? $model->resolveIncrementorModel()
                    : $modelClass;

                $incrementor = StructureIncrementor::where('si_model', $incrementorKey)->first();
                if ($incrementor) {
                    $model->{$codeField} = $incrementor->generateCode();
                }
            }
        });
    }
}
