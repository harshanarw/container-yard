<?php

namespace Tests\Unit\Services;

use App\Services\ContainerCustodyService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The custody precedence rule.
 *
 * A container has two parties: the **owner**, which belongs to the box, and the
 * **customer**, who brought it in this visit and takes it out again. The
 * customer belongs to the visit.
 *
 * Gate-out used to read the container's cached customer — a field gate-in
 * overwrites every visit and the master screen could change at any time — so a
 * box could leave under a different party than it arrived under. The ordering
 * below is what replaced that, and it is the whole design: most authoritative
 * first, container last.
 *
 * Pure logic, so no database and no application: the resolution is the thing
 * worth pinning down, separately from the queries that feed it.
 */
class ContainerCustodyServiceTest extends TestCase
{
    #[DataProvider('precedenceCases')]
    public function test_precedence(?int $job, ?int $gateIn, ?int $container, ?int $expected, string $because): void
    {
        $this->assertSame(
            $expected,
            ContainerCustodyService::resolveCustomerId($job, $gateIn, $container),
            $because
        );
    }

    public static function precedenceCases(): array
    {
        return [
            'the visit job wins over everything' => [
                10, 20, 30, 10,
                'One value shared by both gates is what makes them agree by construction.',
            ],
            'the job wins even when the container disagrees' => [
                10, null, 30, 10,
                'The container is a cache of the last gate-in, not evidence about this visit.',
            ],
            'the gate-in is used when the visit has no job' => [
                null, 20, 30, 20,
                'Gate-in creates the job in a try/catch that only logs, and old movements predate it — '
                . 'the movement is still a per-visit snapshot.',
            ],
            'the container is the last resort only' => [
                null, null, 30, 30,
                'Kept so a container with no movement history can still be gated out rather than blocking the gate.',
            ],
            'nothing known yields null' => [
                null, null, null, null,
                'Better to record no customer than to invent one.',
            ],

            // A zero would otherwise read as "set" and stop the chain early.
            'a zero job id falls through' => [
                0, 20, 30, 20,
                'Zero is not a valid key; a bad row must not shadow a good one.',
            ],
            'a zero gate-in id falls through' => [
                null, 0, 30, 30,
                'Same at the second rung.',
            ],
            'all zeros yield null' => [
                0, 0, 0, null,
                'No valid candidate anywhere.',
            ],
        ];
    }

    /**
     * The ordering itself, stated as an invariant rather than as examples.
     *
     * If someone reorders the chain, this is the test that should stop them.
     */
    public function test_the_container_never_wins_while_a_visit_value_exists(): void
    {
        $container = 999;

        $this->assertNotSame($container, ContainerCustodyService::resolveCustomerId(10, null, $container),
            'A job customer must outrank the container.');

        $this->assertNotSame($container, ContainerCustodyService::resolveCustomerId(null, 20, $container),
            'A gate-in customer must outrank the container.');

        $this->assertNotSame($container, ContainerCustodyService::resolveCustomerId(10, 20, $container),
            'And both together, obviously.');

        $this->assertSame($container, ContainerCustodyService::resolveCustomerId(null, null, $container),
            'It is reached only when the visit knows nothing.');
    }

    /** The repair command passes null for the container on purpose. */
    public function test_omitting_the_container_cannot_resurrect_it(): void
    {
        $this->assertNull(ContainerCustodyService::resolveCustomerId(null, null, null),
            'containers:fix-gate-custody passes null so the value it exists to distrust cannot leak back in as a repair.');

        $this->assertSame(20, ContainerCustodyService::resolveCustomerId(null, 20, null));
    }
}
