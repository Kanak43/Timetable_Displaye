<?php

namespace App\Http\Controllers;

use Auth;
use Response;
use Storage;
use Illuminate\Http\Request;
use App\Services\TimetableService;
use App\Events\TimetablesRequested;

use App\Models\Day;
use App\Models\Timetable;

class TimetablesController extends Controller
{
   
    public function __construct(TimetableService $service)
    {
        $this->service = $service;
        // $this->middleware('auth');
        // $this->middleware('activated');
    }

   
    public function index()
    {
        $timetables = Timetable::orderBy('created_at', 'DESC')->paginate(10);

        return view('dashboard.timetables', compact('timetables'));
    }

    /**
     * @param Illuminate\Http\Request $request The HTTP request
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required',
            'academic_period_id' => 'required'
        ];

        $messages = [
            'academic_period_id.required' => 'An academic period must be selected'
        ];

        $this->validate($request, $rules, $messages);

        $errors = [];
        $dayIds = [];

        $days = Day::all();

        foreach ($days as $day) {
            if ($request->has('day_' . $day->id)){
                $dayIds[] = $day->id;
            }
        }

        if (!count($dayIds)) {
            $errors[] = 'At least one day should be selected';
        }

        if (count($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $otherChecks = $this->service->checkCreationConditions();

        if (count($otherChecks)) {
            return Response::json(['errors' => $otherChecks], 422);
        }

        $timetable = Timetable::create([
            'user_id' => Auth::user()->id,
            'academic_period_id' => $request->academic_period_id,
            'status' => 'IN PROGRESS',
            'name' => $request->name
        ]);

        if ($timetable) {
            $timetable->days()->sync($dayIds);
        }

        event(new TimetablesRequested($timetable));

        return Response::json(['message' => 'Timetables are being created.Please wait..'], 200);
    }

    /**
     * @param int $id
     */
    public function view($id)
    {
        $timetable = Timetable::find($id);

        if (!$timetable) {
            return redirect('/');
        } else {
            $path = $timetable->file_url;
            $timetableData =  Storage::get($path);
            $timetableName = $timetable->name;
            return view('timetables.view', compact('timetableData', 'timetableName'));
        }
    }
}
