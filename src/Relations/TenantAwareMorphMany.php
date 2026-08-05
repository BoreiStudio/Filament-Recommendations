<?php

namespace BoreiStudio\FilamentRecommendations\Relations;

use BoreiStudio\FilamentRecommendations\Contracts\Recommendable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * MorphMany relation that automatically fills the tenant_id of created
 * recommendation rows from the parent (source) model, so the cached results
 * are scoped correctly when tenancy is enabled.
 *
 * @template TModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<TModel, TDeclaringModel>
 */
class TenantAwareMorphMany extends MorphMany
{
    public function save(Model $model)
    {
        $this->fillTenantId($model);

        return parent::save($model);
    }

    protected function setForeignAttributesForCreate(Model $model)
    {
        $this->fillTenantId($model);

        parent::setForeignAttributesForCreate($model);
    }

    protected function fillTenantId(Model $model): void
    {
        $parent = $this->parent;

        if (! $parent instanceof Recommendable) {
            return;
        }

        if ($parent->getRecommendationTenantId() !== null && $model->getAttribute('tenant_id') === null) {
            $model->setAttribute('tenant_id', $parent->getRecommendationTenantId());
        }
    }
}
