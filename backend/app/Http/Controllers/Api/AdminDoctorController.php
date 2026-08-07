<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AdminDoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $db = DB::connection('pgsql_admin');

        $doctors = $db->table('doctor_profiles')
            ->join('users', 'doctor_profiles.user_id', '=', 'users.id')
            ->select(
                'doctor_profiles.id as profile_id',
                'doctor_profiles.user_id',
                'doctor_profiles.status',
                'doctor_profiles.consultation_fee',
                'doctor_profiles.description',
                'doctor_profiles.years_experience',
                'doctor_profiles.university',
                'doctor_profiles.license_number',
                'doctor_profiles.photo_path',
                'doctor_profiles.created_at',
                'users.name',
                'users.last_name',
                'users.email',
                'users.timezone'
            )
            ->orderByRaw("CASE WHEN doctor_profiles.status = 'pending' THEN 0 WHEN doctor_profiles.status = 'approved' THEN 1 ELSE 2 END")
            ->orderBy('doctor_profiles.created_at', 'desc')
            ->get();

        // Add specialties to each doctor
        $doctors = $doctors->map(function ($doc) use ($db) {
            $doc->photo_url = $doc->photo_path ? Storage::disk('public')->url($doc->photo_path) : null;
            $doc->specialties = $db->table('doctor_specialties')
                ->join('specialties', 'specialties.id', '=', 'doctor_specialties.specialty_id')
                ->where('doctor_specialties.doctor_profile_id', $doc->profile_id)
                ->pluck('specialties.name')
                ->toArray();
            $doc->specialty_ids = $db->table('doctor_specialties')
                ->where('doctor_profile_id', $doc->profile_id)
                ->pluck('specialty_id')
                ->toArray();
            return $doc;
        });

        return response()->json(['data' => $doctors], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'email'            => ['required', 'email', 'max:255'],
            'password'         => ['required', 'string', 'min:8'],
            'timezone'         => ['nullable', 'string'],
            'license_number'   => ['required', 'string', 'max:50'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'description'      => ['nullable', 'string'],
            'years_experience' => ['nullable', 'integer', 'min:0'],
            'university'       => ['nullable', 'string', 'max:255'],
            'specialty_ids'    => ['required', 'array', 'min:1'],
            'specialty_ids.*'  => ['uuid'],
            'status'           => ['nullable', 'in:pending,approved,rejected'],
            'photo'            => ['nullable', 'image', 'max:4096'],
        ]);

        $db = DB::connection('pgsql_admin');

        // Check email uniqueness
        $exists = $db->table('users')->where('email', $validated['email'])->exists();
        if ($exists) {
            return response()->json(['message' => 'Ya existe un usuario con ese correo electrónico.'], 422);
        }

        // Se sube antes de la transacción: si la escritura a disco falla,
        // no queremos abrir una transacción de DB para nada.
        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('doctor-photos', 'public')
            : null;

        $db->transaction(function () use ($db, $validated, $photoPath) {
            $userId = Str::uuid()->toString();
            $profileId = Str::uuid()->toString();
            $now = now();

            // Create user
            $db->table('users')->insert([
                'id'         => $userId,
                'name'       => $validated['name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'password'   => Hash::make($validated['password']),
                'timezone'   => $validated['timezone'] ?? 'America/Santo_Domingo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Assign doctor role
            $doctorRole = $db->table('roles')->where('name', 'doctor')->first();
            if ($doctorRole) {
                $db->table('user_roles')->insertOrIgnore([
                    'user_id' => $userId,
                    'role_id' => $doctorRole->id,
                ]);
            }

            // Create profile
            $db->table('doctor_profiles')->insert([
                'id'               => $profileId,
                'user_id'          => $userId,
                'status'           => $validated['status'] ?? 'approved',
                'license_number'   => $validated['license_number'],
                'consultation_fee' => $validated['consultation_fee'] ?? 0,
                'description'      => $validated['description'] ?? '',
                'years_experience' => $validated['years_experience'] ?? 0,
                'university'       => $validated['university'] ?? '',
                'photo_path'       => $photoPath,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            // Assign specialties
            foreach ($validated['specialty_ids'] as $specId) {
                $db->table('doctor_specialties')->insertOrIgnore([
                    'doctor_profile_id' => $profileId,
                    'specialty_id'      => $specId,
                ]);
            }
        });

        return response()->json(['message' => 'Médico creado exitosamente.'], 201);
    }

    public function updateStatus(Request $request, string $profileId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $db = DB::connection('pgsql_admin');
        $profile = $db->table('doctor_profiles')->where('id', $profileId)->first();

        if (!$profile) {
            return response()->json(['message' => 'Perfil no encontrado.'], 404);
        }

        $db->table('doctor_profiles')
            ->where('id', $profileId)
            ->update([
                'status'     => $validated['status'],
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Estado actualizado.'], 200);
    }
}
