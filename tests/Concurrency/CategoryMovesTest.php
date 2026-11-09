<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Catalog\Actions\MoveCategory;
use Larasell\Larasell\Catalog\Actions\MoveCategoryPayload;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Exceptions\CategoryCycle;
use Larasell\Larasell\Catalog\Models\Category;

it('lets only one of two opposite category moves through, so they cannot close a loop', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite serialises all writers, so moves cannot race.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for the concurrency test.');
    }

    // Arrange
    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    $a = Category::query()->create(['name' => ['en' => 'A']]);
    $b = Category::query()->create(['name' => ['en' => 'B']]);
    $defaultConnection = config('database.default');
    config()->set('database.connections.category_blocker', config("database.connections.{$defaultConnection}"));
    $blocker = DB::connection('category_blocker');
    // Holds both categories, so both moves start before either can finish.
    $blocker->beginTransaction();
    $blocker->table($a->getTable())->whereIn('id', [$a->id, $b->id])->lockForUpdate()->get();
    $moves = [[$a->id, $b->id], [$b->id, $a->id]];

    // Act
    $children = collect($moves)->map(function (array $move): array {
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork move process.');
        }

        if ($pid === 0) {
            fclose($parentSocket);
            DB::purge();
            fwrite($childSocket, "ready\n");

            try {
                app(MoveCategory::class)->handle(new CategoryId($move[0]), new MoveCategoryPayload(new CategoryId($move[1])));
                fwrite($childSocket, "moved\n");
            } catch (CategoryCycle) {
                fwrite($childSocket, "cycle\n");
            } catch (Throwable $exception) {
                fwrite($childSocket, 'failed:'.$exception->getMessage()."\n");
            }

            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);
        stream_set_timeout($parentSocket, 20);

        return [$pid, $parentSocket];
    });
    $children->each(fn (array $child) => expect(fgets($child[1]))->toBe("ready\n"));
    usleep(200_000);
    $blocker->commit();
    $results = $children->map(function (array $child): string {
        [$pid, $socket] = $child;
        pcntl_waitpid($pid, $status);
        $result = trim((string) stream_get_contents($socket));
        fclose($socket);

        return $result;
    });
    DB::purge();

    // Assert
    $parents = Category::query()->orderBy('id')->pluck('parent_id')->all();
    expect($results->sort()->values()->all())->toBe(['cycle', 'moved'])
        ->and($parents)->toBeIn([[$b->id, null], [null, $a->id]]);
});
