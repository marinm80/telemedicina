<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id'        => $request->user()->id,
                    'name'      => $request->user()->name,
                    'last_name' => $request->user()->last_name,
                    'email'     => $request->user()->email,
                    'role'      => $request->user()->role,
                    'timezone'  => $request->user()->timezone,
                ] : null,
            ],
            'booking' => fn () => $request->user() ? [
                'specialties' => \DB::connection('pgsql_admin')->table('specialties')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'doctors' => \DB::connection('pgsql_admin')->table('v_doctor_directory')
                    ->get(['doctor_profile_id', 'user_id', 'name', 'last_name'])
                    ->map(fn ($d) => [
                        'doctor_profile_id' => $d->doctor_profile_id,
                        'user_id'           => $d->user_id,
                        'full_name'         => trim("{$d->name} {$d->last_name}"),
                        'specialties'       => \DB::connection('pgsql_admin')->table('doctor_specialties')
                            ->join('specialties', 'specialties.id', '=', 'doctor_specialties.specialty_id')
                            ->where('doctor_specialties.doctor_profile_id', $d->doctor_profile_id)
                            ->pluck('specialties.name')
                            ->toArray(),
                    ]),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
