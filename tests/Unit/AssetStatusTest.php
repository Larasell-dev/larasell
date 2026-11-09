<?php

use Larasell\Larasell\Media\AssetStatus;

it('allows only forward moves, and a retry from failed', function (AssetStatus $from, AssetStatus $to, bool $allowed) {
    // Act & Assert
    expect($from->canBecome($to))->toBe($allowed);
})->with([
    'pending to processing' => [AssetStatus::Pending, AssetStatus::Processing, true],
    'pending to ready' => [AssetStatus::Pending, AssetStatus::Ready, true],
    'pending to failed' => [AssetStatus::Pending, AssetStatus::Failed, true],
    'processing to ready' => [AssetStatus::Processing, AssetStatus::Ready, true],
    'processing to failed' => [AssetStatus::Processing, AssetStatus::Failed, true],
    'processing back to pending' => [AssetStatus::Processing, AssetStatus::Pending, false],
    'ready to anything' => [AssetStatus::Ready, AssetStatus::Failed, false],
    'ready to processing' => [AssetStatus::Ready, AssetStatus::Processing, false],
    'failed to pending, a retry' => [AssetStatus::Failed, AssetStatus::Pending, true],
    'failed to ready' => [AssetStatus::Failed, AssetStatus::Ready, false],
    'to itself' => [AssetStatus::Processing, AssetStatus::Processing, false],
]);
