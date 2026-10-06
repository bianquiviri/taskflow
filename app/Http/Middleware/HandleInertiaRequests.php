<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Theme;
use App\Support\AuthContext;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * The auth context of the current request, resolved at most once.
     */
    private ?AuthContext $context = null;

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = fn (): array => $this->context()->resolve($request->user());

        return [
            ...parent::share($request),
            'auth' => fn (): array => Arr::only($context(), ['user', 'team']),
            'can' => fn (): array => $context()['can'],
            'theme' => fn (): string => ($request->user()?->theme ?? Theme::Light)->value,
            'flash' => [
                'success' => fn (): mixed => $request->session()->get('success'),
                'error' => fn (): mixed => $request->session()->get('error'),
                'warning' => fn (): mixed => $request->session()->get('warning'),
                'info' => fn (): mixed => $request->session()->get('info'),
            ],
        ];
    }

    private function context(): AuthContext
    {
        return $this->context ??= app(AuthContext::class);
    }
}
