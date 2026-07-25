<?php

declare(strict_types=1);

namespace NAP\Infrastructure\EventSourcing;

use NAP\Infrastructure\ReadModel\ProjectionInterface;

final class EventReplayEngine
{
    private SqlEventStore $eventStore;
    /** @var array<int, ProjectionInterface> */
    private array $projections = [];

    public function __construct(SqlEventStore $eventStore)
    {
        $this->eventStore = $eventStore;
    }

    public function addProjection(ProjectionInterface $projection): void
    {
        $this->projections[] = $projection;
    }

    public function rebuildAllProjections(string $aggregateId): int
    {
        foreach ($this->projections as $projection) {
            $projection->reset();
        }

        $stream = $this->eventStore->getStream($aggregateId);
        $replayedCount = 0;

        foreach ($stream as $event) {
            foreach ($this->projections as $projection) {
                $projection->project($event);
            }
            $replayedCount++;
        }

        return $replayedCount;
    }
}
