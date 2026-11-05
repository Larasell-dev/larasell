<?php

namespace Larasell\Larasell\Seo\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Reflector;
use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Larasell\Larasell\Seo\Exceptions\SeoPageGone;
use Larasell\Larasell\Seo\Exceptions\SeoPageHidden;
use Larasell\Larasell\Seo\Exceptions\SeoPageNotFound;
use Larasell\Larasell\Seo\Http\Controllers\SeoPageController;
use Larasell\Larasell\Seo\ResolutionKind;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Seo\SeoRedirect;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Storefront\StorefrontContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every route in Route::storefront(), before implicit route model binding:
 *
 * on routes with a parameter typed as an SEO model (or the Route::seo() catch-all), redirects
 *    paths that are not in normalized form, then looks up the whole request path and binds the page,
 *    redirects, or fails with SeoPageNotFound, SeoPageHidden, or SeoPageGone. Implicit binding then skips the parameter, because it is already a model.
 */
final class ResolveStorefront
{
    public function __construct(
        private readonly SeoPages $pages,
        private readonly PathNormalizer $paths,
        private readonly SeoResources $resources,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Route $route */
        $route = $request->route();
        // SetStorefrontContext set it, and RestoreStorefrontPath let only the route copy of its locale through.
        $context = StorefrontContext::current() ?? throw new SeoPageNotFound($request->path());

        $parameter = $this->pageParameter($route);

        // Other storefront routes keep their path as sent: parameters such as order numbers are case-sensitive.
        if ($parameter === null) {
            return $next($request);
        }

        $path = $this->pathBelow($request, $context);
        $normalized = $this->paths->normalize($path);

        if ($normalized !== $path) {
            return $this->redirect($request, new SeoRedirect($context->url($normalized), 301));
        }

        [$name, $class] = $parameter;
        $resolution = $this->pages->resolve($normalized, $context);

        if ($resolution->redirect !== null) {
            return $this->redirect($request, $resolution->redirect);
        }

        if ($resolution->kind === ResolutionKind::Gone && $resolution->url !== null) {
            throw new SeoPageGone($resolution->url, $context);
        }

        if ($resolution->kind === ResolutionKind::Hidden && $resolution->resource !== null) {
            throw new SeoPageHidden($resolution->resource, $context);
        }

        // Found, but a typed route only shows its own kind of page, e.g. no category at /p/{product}.
        if ($resolution->kind !== ResolutionKind::Found || ! $resolution->resource instanceof $class) {
            throw new SeoPageNotFound($normalized);
        }

        $route->setParameter($name, $resolution->resource);

        return $next($request);
    }

    /**
     * The route parameter that receives the page, with the class the page must be: the catch-all's
     * path, or the first parameter typed as a model an SEO resource type handles.
     *
     * @return array{string, class-string<Model>}|null
     */
    private function pageParameter(Route $route): ?array
    {
        if ($route->hasParameter(SeoPageController::PATH)) {
            return [SeoPageController::PATH, Model::class];
        }

        foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
            /** @var class-string<Model> $class */
            $class = Reflector::getParameterClassName($parameter);

            if ($route->hasParameter($parameter->getName()) && $this->resources->handles($class)) {
                return [$parameter->getName(), $class];
            }
        }

        return null;
    }

    /** The decoded request path below the entry point's prefix, as the client sent it. */
    private function pathBelow(Request $request, StorefrontContext $context): string
    {
        $path = rawurldecode(substr($request->getPathInfo(), 1));

        if ($context->entryPoint->prefix === '') {
            return $path;
        }

        $rest = substr($path, strlen($context->entryPoint->prefix));

        return str_starts_with($rest, '/') ? substr($rest, 1) : $rest;
    }

    private function redirect(Request $request, SeoRedirect $redirect): RedirectResponse
    {
        $query = $request->getQueryString();
        // 308 keeps the method and body of a form posted to an old URL.
        $status = $request->isMethodSafe() || $redirect->status !== 301 ? $redirect->status : 308;

        return new RedirectResponse($redirect->url.($query === null ? '' : '?'.$query), $status);
    }
}
