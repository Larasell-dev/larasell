<?php

namespace Larasell\Larasell\Media;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Exceptions\UnknownMediaProvider;

/**
 * The media providers in larasell.media.providers, each made by its driver: local and s3 on
 * Laravel disks, mux at Mux, and those added with extend(). Bound as MediaStorage, it hands each
 * file to the provider that keeps it, and stores new ones with the default provider.
 */
final class MediaProviders implements MediaStorage
{
    /** @var array<string, MediaStorage> */
    private array $providers = [];

    /** @var array<string, Closure(Application, string, array<string, mixed>): MediaStorage> */
    private array $drivers = [];

    public function __construct(private readonly Application $app, private readonly Repository $config) {}

    /**
     * Adds a driver, e.g. for another video service.
     *
     * @param  Closure(Application, string $name, array<string, mixed> $config): MediaStorage  $factory
     */
    public function extend(string $driver, Closure $factory): void
    {
        $this->drivers[$driver] = $factory;
    }

    public function provider(string $name): MediaStorage
    {
        return $this->providers[$name] ??= $this->make($name);
    }

    /** Drops the provider made from earlier settings, so the next call makes it again. */
    public function forget(string $name): void
    {
        unset($this->providers[$name]);
    }

    /**
     * The provider's config: larasell.media.providers.{name}, with what was saved in the panel on
     * top for Mux.
     *
     * @return array<string, mixed>
     */
    public function config(string $name): array
    {
        $config = $this->config->get("larasell.media.providers.{$name}");

        if (! is_array($config)) {
            throw UnknownMediaProvider::named($name);
        }

        return $name === MuxSettings::PROVIDER ? [...$config, ...$this->muxSettings()->overrides()] : $config;
    }

    /** The provider new files of the type go to: its own in provider_for, or the default one. */
    public function nameFor(?MediaType $type = null): string
    {
        $own = $type === null ? null : $this->config->get("larasell.media.provider_for.{$type->value}");

        if ($type === MediaType::Video) {
            // Turning Mux on or off in the panel wins over the config file.
            $own = match ($this->muxSettings()->useForVideos()) {
                true => MuxSettings::PROVIDER,
                false => $own === MuxSettings::PROVIDER ? null : $own,
                null => $own,
            };
        }

        return (string) ($own ?: $this->config->get('larasell.media.provider', 'local'));
    }

    /** @return list<string> the providers that use the driver, e.g. every Mux account */
    public function namesWithDriver(string $driver): array
    {
        return array_keys(array_filter(
            (array) $this->config->get('larasell.media.providers', []),
            fn (mixed $provider): bool => is_array($provider) && ($provider['driver'] ?? null) === $driver,
        ));
    }

    public function store(UploadedFile $file, AssetVisibility $visibility): StoredFile
    {
        return $this->provider($this->nameFor(MediaType::forMimeType((string) $file->getMimeType())))->store($file, $visibility);
    }

    public function locate(string $reference, AssetVisibility $visibility): StoredFile
    {
        return $this->provider($this->nameFor())->locate($reference, $visibility);
    }

    public function inspect(StoredFile $file): AssetInspection
    {
        return $this->provider($file->provider)->inspect($file);
    }

    public function copy(StoredFile $file, AssetVisibility $visibility): StoredFile
    {
        return $this->provider($file->provider)->copy($file, $visibility);
    }

    public function delete(StoredFile $file): void
    {
        $this->provider($file->provider)->delete($file);
    }

    public function url(StoredFile $file): string
    {
        return $this->provider($file->provider)->url($file);
    }

    public function temporaryUrl(StoredFile $file, DateTimeInterface $expiresAt): string
    {
        return $this->provider($file->provider)->temporaryUrl($file, $expiresAt);
    }

    /** Whether the default provider is; ask provider($name)->readable() for a file's own. */
    public function readable(): bool
    {
        return $this->provider($this->nameFor())->readable();
    }

    public function readStream(StoredFile $file)
    {
        return $this->provider($file->provider)->readStream($file);
    }

    private function make(string $name): MediaStorage
    {
        $config = $this->config($name);

        $driver = (string) ($config['driver'] ?? '');

        if (isset($this->drivers[$driver])) {
            return ($this->drivers[$driver])($this->app, $name, $config);
        }

        return match ($driver) {
            // The local provider keeps using larasell.media.disks unless it names its own.
            'local', 's3' => new DiskMediaStorage(
                $this->app->make(FilesystemManager::class),
                $name,
                $config['disks'] ?? (array) $this->config->get('larasell.media.disks'),
                (string) ($config['path'] ?? $this->config->get('larasell.media.path')),
            ),
            'mux' => new MuxMediaStorage($this->app->make(Factory::class), $name, $config),
            default => throw UnknownMediaProvider::driver($name, $driver),
        };
    }

    private function muxSettings(): MuxSettings
    {
        return $this->app->make(MuxSettings::class);
    }
}
