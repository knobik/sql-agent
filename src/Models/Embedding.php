<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * @property int $id
 * @property string $embeddable_type
 * @property int $embeddable_id
 * @property Vector|null $embedding
 * @property string $content_hash
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Embedding extends EloquentModel
{
    use HasNeighbors;

    protected $table = 'sql_agent_embeddings';

    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'embedding',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('sql-agent.search.drivers.pgvector.connection')
            ?? parent::getConnectionName();
    }

    /**
     * @return MorphTo<EloquentModel, $this>
     */
    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to find the embedding for a specific model.
     *
     * @param  Builder<Embedding>  $query
     * @return Builder<Embedding>
     */
    public function scopeForModel(Builder $query, EloquentModel $model): Builder
    {
        return $query
            ->where('embeddable_type', $model->getMorphClass())
            ->where('embeddable_id', $model->getKey());
    }
}
