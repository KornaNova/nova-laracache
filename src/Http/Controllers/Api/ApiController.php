<?php

namespace Mostafaznv\NovaLaraCache\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mostafaznv\LaraCache\CacheEntity;
use Mostafaznv\LaraCache\DTOs\CacheData;
use Mostafaznv\LaraCache\Jobs\UpdateLaraCacheModelsList;

class ApiController extends Controller
{
    protected string $driver;

    public function __construct()
    {
        $this->driver = config('laracache.driver') ?: config('cache.default');
    }


    protected function model(string $model): Model
    {
        return app($model);
    }

    protected function models(): array
    {
        $models = [];
        $key = UpdateLaraCacheModelsList::LARACACHE_MODELS_LIST;

        $list = Cache::driver($this->driver)->get($key);
        $list = is_array($list) ? $list : [];
        $list = Arr::sort($list);

        foreach ($list as $item) {
            $model = $this->model($item);

            if (method_exists($model, 'cacheEntities')) {
                $models[] = $model;
            }
        }

        return $models;
    }

    protected function entityToArray(CacheEntity $entity, string $model, bool $withValue = false): array
    {
        $prefix = Str::kebab(class_basename($model));

        $cache = CacheData::fromCache($entity, $prefix);
        $expiration = $cache->expiration
            ? Carbon::createFromTimestamp($cache->expiration)
            : null;

        if ($withValue) {
            $value = [
                'type'    => $this->getTypeOfCacheItem($cache->value),
                'content' => $cache->value
            ];
        }
        else {
            $value = [
                'type'    => null,
                'content' => null
            ];
        }

        return [
            'status'             => $cache->status->getValue(),
            'name'               => $entity->name,
            'default'            => $entity->default,
            'value'              => $value,
            'ttl'                => $entity->getTtl(),
            'validForRestOfDay'  => $entity->validForRestOfDay,
            'validForRestOfWeek' => $entity->validForRestOfWeek,
            'forever'            => $entity->forever,
            'isQueueable'        => $entity->isQueueable,
            'refreshAfter'       => [
                'create'  => $entity->refreshAfterCreate,
                'update'  => $entity->refreshAfterUpdate,
                'delete'  => $entity->refreshAfterDelete,
                'restore' => $entity->refreshAfterRestore,
            ],
            'expiration'         => [
                'unix'   => $cache->expiration,
                'date'   => $expiration?->toDateTimeString() ?? '',
                'diff'   => $expiration?->diffForHumans() ?? '',
                'isPast' => $expiration?->isPast() ?? true,
            ],
        ];
    }

    private function getTypeOfCacheItem(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value) and isset($value[0])) {
            $value = $value[0];
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return gettype($value);
    }
}
