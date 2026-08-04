<?php
declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class DirectoryController extends Controller
{
    /**
     * Catálogo público / autenticado de médicos especialistas.
     */
    public function index(Request $request): Response
    {
        $specialties = DB::table('specialties')
            ->select(['id', 'name', 'description'])
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $query = DB::table('v_doctor_directory');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('last_name', 'ILIKE', "%{$search}%")
                  ->orWhere('university', 'ILIKE', "%{$search}%");
            });
        }

        if ($specialtyId = $request->input('specialty_id')) {
            $query->whereIn('doctor_profile_id', function ($q) use ($specialtyId) {
                $q->select('doctor_profile_id')
                  ->from('doctor_specialties')
                  ->where('specialty_id', $specialtyId);
            });
        }

        $doctors = $query->paginate(12)->through(function ($doc) {
            $specialtiesList = DB::table('doctor_specialties')
                ->join('specialties', 'specialties.id', '=', 'doctor_specialties.specialty_id')
                ->where('doctor_specialties.doctor_profile_id', $doc->doctor_profile_id)
                ->pluck('specialties.name')
                ->toArray();

            return [
                'user_id'           => $doc->user_id,
                'doctor_profile_id' => $doc->doctor_profile_id,
                'name'              => $doc->name,
                'last_name'         => $doc->last_name,
                'timezone'          => $doc->timezone,
                'consultation_fee'  => (float) $doc->consultation_fee,
                'description'       => $doc->description,
                'years_experience'  => $doc->years_experience,
                'university'        => $doc->university,
                'specialties'       => $specialtiesList,
            ];
        });

        return Inertia::render('Directory', [
            'specialties' => $specialties,
            'doctors'     => $doctors,
            'filters'     => $request->only(['specialty_id', 'search']),
        ]);
    }
}
