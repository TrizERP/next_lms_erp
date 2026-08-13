<?php

namespace App\Http\Controllers\transportation\transport_rate;

use App\Http\Controllers\Controller;
use App\Models\transportation\transport_rate\transport_rate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

/**
 * Transport (kilometer) rate slabs.
 *
 * A slab belongs to one institute and one academic year and covers the distance
 * band [from_distance, to_distance] with a rickshaw and a van fare (old / new).
 * Slabs within the same year must not overlap, otherwise the fare for a given
 * distance is ambiguous.
 */
class transportRateController extends Controller
{
    /** Optional numeric fare columns. */
    private const RATE_COLUMNS = ['rick_old', 'rick_new', 'van_old', 'van_new'];

    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function syear(): int
    {
        return (int) session()->get('syear');
    }

    /**
     * Slabs are scoped to the logged-in institute *and* academic year. The old
     * controller scoped only index() this way, so edit / update / destroy could
     * reach another institute's row by guessing its id.
     */
    private function scoped()
    {
        return transport_rate::where('sub_institute_id', $this->tenant())
            ->where('syear', $this->syear());
    }

    private function rules(): array
    {
        return [
            'distance_from_school' => 'required|string|max:191',
            'from_distance'        => 'required|numeric|min:0',
            'to_distance'          => 'required|numeric|min:0|gte:from_distance',
            'rick_old'             => 'nullable|numeric|min:0',
            'rick_new'             => 'nullable|numeric|min:0',
            'van_old'              => 'nullable|numeric|min:0',
            'van_new'              => 'nullable|numeric|min:0',
        ];
    }

    private function messages(): array
    {
        return [
            'distance_from_school.required' => 'Distance from school is required.',
            'from_distance.required'        => 'From distance is required.',
            'to_distance.required'          => 'To distance is required.',
            'to_distance.gte'               => 'To distance must be greater than or equal to from distance.',
        ];
    }

    private function payload(Request $request): array
    {
        $values = [
            'distance_from_school' => trim((string) $request->input('distance_from_school')),
            'from_distance'        => $request->input('from_distance'),
            'to_distance'          => $request->input('to_distance'),
        ];

        // Blank fare inputs are stored as 0 rather than an empty string so the
        // fare calculation never has to guard against non-numeric values.
        foreach (self::RATE_COLUMNS as $column) {
            $value = $request->input($column);
            $values[$column] = ($value === null || $value === '') ? 0 : $value;
        }

        return $values;
    }

    /**
     * A slab clashes when its label is reused, or when its distance band
     * overlaps an existing band for the same institute and academic year.
     */
    private function duplicateMessage(Request $request, ?int $ignoreId = null): ?string
    {
        $label = trim((string) $request->input('distance_from_school'));
        $from = (float) $request->input('from_distance');
        $to = (float) $request->input('to_distance');

        $sameLabel = $this->scoped()
            ->whereRaw('UPPER(distance_from_school) = ?', [strtoupper($label)])
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists();
        if ($sameLabel) {
            return 'A rate with this distance from school already exists for the selected academic year.';
        }

        $overlapping = $this->scoped()
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->whereRaw('CAST(from_distance AS DECIMAL(12,2)) <= ?', [$to])
            ->whereRaw('CAST(to_distance AS DECIMAL(12,2)) >= ?', [$from])
            ->first();
        if ($overlapping) {
            return "Distance range {$from}-{$to} overlaps the existing range "
                . "{$overlapping->from_distance}-{$overlapping->to_distance}.";
        }

        return null;
    }

    private function isApi(Request $request): bool
    {
        return in_array($request->input('type'), ['API', 'JSON'], true);
    }

    private function respond(Request $request, int $status, string $message)
    {
        $res = ['status_code' => $status, 'message' => $message];

        if ($this->isApi($request)) {
            return is_mobile($request->input('type'), 'transport_rate.index', $res, 'redirect');
        }

        // `data` keeps the banner contract the Blade list screen already uses;
        // `success` / `fail` are what the add and edit forms read.
        return redirect()->route('transport_rate.index')
            ->with('data', $res)
            ->with($status === 1 ? 'success' : 'fail', $message);
    }

    /** Validation / duplicate failures keep the submitted values on the Blade form. */
    private function reject(Request $request, string $message, $validator = null)
    {
        if ($this->isApi($request)) {
            return $this->respond($request, 0, $message);
        }

        $redirect = back()->withInput()->with('fail', $message);

        return $validator ? $redirect->withErrors($validator) : $redirect;
    }

    public function index(Request $request)
    {
        $datas = $this->scoped()->orderBy('id', 'desc')->get();

        if ($this->isApi($request)) {
            return is_mobile($request->input('type'), '', [
                'status_code' => 1,
                'message'     => 'Success',
                'data'        => $datas,
            ], 'redirect');
        }

        return view('transportation.transport_rate.show', compact('datas'));
    }

    public function create()
    {
        return view('transportation.transport_rate.add_rate');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());
        if ($validator->fails()) {
            return $this->reject($request, $validator->messages()->first(), $validator);
        }

        if ($duplicate = $this->duplicateMessage($request)) {
            return $this->reject($request, $duplicate);
        }

        $data = new transport_rate();
        foreach ($this->payload($request) as $column => $value) {
            $data->{$column} = $value;
        }
        $data->syear = $this->syear();
        $data->sub_institute_id = $this->tenant();
        $data->created_on = now();
        $data->save();

        return $this->respond($request, 1, 'Added Successfully');
    }

    public function edit(Request $request, $id)
    {
        $data = $this->scoped()->where('id', $id)->first();
        if (! $data) {
            return $this->respond($request, 0, 'Rate not found.');
        }

        if ($this->isApi($request)) {
            return is_mobile($request->input('type'), '', [
                'status_code' => 1,
                'message'     => 'Success',
                'data'        => $data,
            ], 'redirect');
        }

        return view('transportation.transport_rate.edit_rate', compact('data'));
    }

    public function update(Request $request, $id)
    {
        if (! $this->scoped()->where('id', $id)->exists()) {
            return $this->respond($request, 0, 'Rate not found.');
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());
        if ($validator->fails()) {
            return $this->reject($request, $validator->messages()->first(), $validator);
        }

        if ($duplicate = $this->duplicateMessage($request, (int) $id)) {
            return $this->reject($request, $duplicate);
        }

        $this->scoped()->where('id', $id)->update($this->payload($request) + ['created_on' => now()]);

        return $this->respond($request, 1, 'Updated Successfully');
    }

    public function destroy(Request $request, $id)
    {
        $deleted = $this->scoped()->where('id', $id)->delete();

        return $deleted
            ? $this->respond($request, 1, 'Deleted Successfully')
            : $this->respond($request, 0, 'Rate not found.');
    }
}
