<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Seo\Models\SeoUrl;

/** What a path in a context leads to. */
final readonly class Resolution
{
    private function __construct(
        public ResolutionKind $kind,
        public ?Model $resource = null,
        public ?SeoUrl $url = null,
        public ?SeoRedirect $redirect = null,
    ) {}

    public static function found(Model $resource, SeoUrl $url): self
    {
        return new self(ResolutionKind::Found, $resource, $url);
    }

    public static function redirect(SeoRedirect $redirect): self
    {
        return new self(ResolutionKind::Redirect, redirect: $redirect);
    }

    public static function hidden(Model $resource, SeoUrl $url): self
    {
        return new self(ResolutionKind::Hidden, $resource, $url);
    }

    public static function gone(SeoUrl $url): self
    {
        return new self(ResolutionKind::Gone, url: $url);
    }

    public static function notFound(): self
    {
        return new self(ResolutionKind::NotFound);
    }
}
