<?php

// todo move namespace one dir above
namespace Stancl\Tenancy\Database\Models\Concerns;

// todo rename
trait HasADataColumn
{
    public static $priorityListeners = [];

    /**
     * We need this property, because both created & saved event listeners
     * decode the data (to take precedence before other created & saved)
     * listeners, but we don't want the dadta to be decoded twice.
     *
     * @var string
     */
    public $dataEncodingStatus = 'decoded'; // todo write tests for this

    public static function bootHasADataColumn()
    {
        $encode = function (self $model) {
            if ($model->dataEncodingStatus === 'encoded') {
                return;
            }

            foreach ($model->getAttributes() as $key => $value) {
                if (! in_array($key, static::getCustomColums())) {
                    $current = $model->getAttribute(static::getDataColumn()) ?? [];

                    $model->setAttribute(static::getDataColumn(), array_merge($current, [
                        $key => $value,
                    ]));

                    unset($model->attributes[$key]);
                }
            }

            $model->dataEncodingStatus = 'encoded';
        };

        $decode = function (self $model) {
            if ($model->dataEncodingStatus === 'decoded') {
                return;
            }

            foreach ($model->getAttribute(static::getDataColumn()) ?? [] as $key => $value) {
                $model->setAttribute($key, $value);
            }

            $model->setAttribute(static::getDataColumn(), null);

            $model->dataEncodingStatus = 'decoded';
        };

        static::registerPriorityListener('retrieved', $decode);

        static::registerPriorityListener('saving', $encode);
        static::registerPriorityListener('creating', $encode);
        static::registerPriorityListener('updating', $encode);
        static::registerPriorityListener('saved', $decode);
        static::registerPriorityListener('created', $decode);
        static::registerPriorityListener('updated', $decode);
    }

    protected function fireModelEvent($event, $halt = true)
    {
        $this->runPriorityListeners($event, $halt);

        return parent::fireModelEvent($event, $halt);
    }

    public function runPriorityListeners($event, $halt = true)
    {
        $listeners = static::$priorityListeners[$event] ?? [];

        if (! $event) {
            return;
        }

        foreach ($listeners as $listener) {
            if (is_string($listener)) {
                $listener = app($listener);
                $handle = [$listener, 'handle'];
            } else {
                $handle = $listener;
            }

            $handle($this);
        }
    }

    public static function registerPriorityListener(string $event, callable $callback)
    {
        static::$priorityListeners[$event][] = $callback;
    }

    public function getCasts()
    {
        return array_merge(parent::getCasts(), [
            static::getDataColumn() => 'array',
        ]);
    }

    /**
     * Get the name of the column that stores additional data.
     */
    public static function getDataColumn(): string
    {
        return 'data';
    }

    public static function getCustomColums(): array
    {
        return array_merge(['id'], config('tenancy.custom_columns'));
    }
}