<?php

declare(strict_types=1);

namespace j45l\concurrentPhp\Coroutine;

use Closure;
use Exception;
use Fiber;
use j45l\functional\Cats\Maybe\Maybe;
use Throwable;

use function is_null as isNull;
use function j45l\functional\Cats\Either\BecauseException;
use function j45l\functional\Cats\Either\Failure;
use function j45l\functional\Cats\Maybe\None;
use function j45l\functional\Cats\Maybe\Some;
use function j45l\functional\nop;

/**
 * @template TReturn
 */
abstract class Coroutine
{
    private static int $nextId = 1;

    public readonly int $id;

    /** @var Fiber<mixed, mixed, TReturn, mixed> */
    private Fiber $fiber;

    /** @var callable */
    private $onThrowable;

    protected function __construct(callable $fn)
    {
        $this->id = self::$nextId++;

        $this->fiber = new Fiber(function () use ($fn) {
            try {
                return Some($fn());
            } catch (Exception $exception) {
                return Failure(BecauseException($exception));
            }
        });
        $this->onThrowable = nop(...);
    }

    /** @return $this */
    public function onThrowable(callable $onThrowable = null): self
    {
        $this->onThrowable = $onThrowable ?? nop(...);

        return $this;
    }

    public static function in(): bool
    {
        return !isNull(Fiber::getCurrent());
    }

    /**
     * @param mixed $value
     * @throws Throwable
     */
    public static function suspend(mixed $value = null): void
    {
        Fiber::suspend($value);
    }

    /** @throws Throwable */
    public static function waitFor(Closure $predicate, Closure $do = null): mixed
    {
        $do ??= static fn () => null;
        while (!$predicate()) {
            self::suspend();
        }

        return $do();
    }

    /**
     * @param array<mixed> $args
     * @throws Throwable
     * @return self<TReturn>
     */
    public function start(...$args): self
    {
        try {
            $this->fiber->start(...$args);
        } catch (Throwable $throwable) {
            ($this->onThrowable)($throwable);
        }

        return $this;
    }

    public function isTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    public function isSuspended(): bool
    {
        return $this->fiber->isSuspended();
    }

    /**
     * @return mixed
     * @throws Throwable
     */
    public function resume(): mixed
    {
        return $this->fiber->resume();
    }

    /**
     * @phpstan-return Maybe<TReturn>
     */
    public function returnValue(): Maybe
    {
        return match (true) {
            $this->isTerminated() => $this->fiber->getReturn(),
            default => None()
        };
    }

    public function isStarted(): bool
    {
        return $this->fiber->isStarted();
    }
}
