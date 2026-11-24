<?php

namespace App\Support;

use App\Models\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A box is its record, not its number (Rule 7.1, Phase 8a of
 * docs/architecture-gap-plan.md).
 *
 * Every table that copies a container number (movements, surveys, estimates,
 * work orders, invoice lines) also carries `container_id`. Matching and
 * grouping by the number text treats one box as two the moment its number
 * changes: a gate-in under the old number and a gate-out under the new would
 * read as a visit that never closed beside a departure with no arrival. So:
 *
 *   - a number someone typed is turned into a container once, here ({@see find});
 *   - records are then found and grouped by `container_id`
 *     ({@see whereBelongsTo}, {@see groupByContainer}).
 *
 * A row with no `container_id` (none exist on live today) still matches its
 * container by the container's current number, so nothing that matches now
 * stops matching.
 *
 * Former numbers (renumbering, Phase 8c) are added to {@see find} and nowhere
 * else.
 */
final class ContainerNumbers
{
    /** The form a typed number is compared in. */
    public static function normalise(?string $typed): string
    {
        return strtoupper(trim((string) $typed));
    }

    /**
     * The container a typed number refers to, or null.
     *
     * Today: the container whose current number it is. Phase 8c adds the
     * number history here, so a former number finds the box too.
     */
    public static function find(?string $typed): ?Container
    {
        $no = self::normalise($typed);

        return $no === '' ? null : Container::where('container_no', $no)->first();
    }

    /** As {@see find}, but a missing container is a 404 / ModelNotFound. */
    public static function findOrFail(?string $typed): Container
    {
        return self::find($typed)
            ?? throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
                ->setModel(Container::class, [self::normalise($typed)]);
    }

    /**
     * Constrain a query to the rows that belong to these containers: by
     * `container_id`, or, for a row with no id, by the container's current
     * number.
     *
     * @param  Container|iterable<Container>  $containers
     */
    public static function whereBelongsTo(
        Builder $query,
        Container|iterable $containers,
        string $idColumn = 'container_id',
        string $noColumn = 'container_no',
    ): Builder {
        $containers = $containers instanceof Container ? collect([$containers]) : collect($containers);

        $ids = $containers->pluck('id')->filter()->unique()->values()->all();
        $nos = $containers->pluck('container_no')->filter()->unique()->values()->all();

        return $query->where(function ($q) use ($ids, $nos, $idColumn, $noColumn) {
            $q->whereIn($idColumn, $ids ?: [0])
              ->orWhere(fn ($legacy) => $legacy->whereNull($idColumn)->whereIn($noColumn, $nos ?: ['']));
        });
    }

    /**
     * Group rows by the container they belong to, keyed by container id.
     * A row with no id is placed by its number among these containers' current
     * numbers; one matching none of them is dropped.
     *
     * @param  Collection<int,Model>        $rows
     * @param  iterable<Container>          $containers
     * @return Collection<int,Collection>   keyed by container id
     */
    public static function groupByContainer(
        Collection $rows,
        iterable $containers,
        string $idColumn = 'container_id',
        string $noColumn = 'container_no',
    ): Collection {
        $idByNo = collect($containers)->mapWithKeys(fn ($c) => [$c->container_no => $c->id]);

        return $rows
            ->groupBy(fn ($row) => $row->{$idColumn} ?? $idByNo->get($row->{$noColumn}) ?? 0)
            ->forget(0);
    }
}
