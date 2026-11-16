<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Room;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Weekly timetable of the branch (rooms × days).
     */
    public function __invoke(): Response
    {
        $branchIds = $this->branchIds();

        return Inertia::render('schedule', [
            'rooms' => Room::query()->whereIn('branch_id', $branchIds)->orderBy('name')->get(['id', 'name']),
            'groups' => Group::query()->whereIn('branch_id', $branchIds)->where('status', 'active')
                ->with('teacher:id,name', 'room:id,name')->orderBy('start_time')->get()
                ->map(fn (Group $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'days' => array_map('intval', $group->days),
                    'start_time' => substr($group->start_time, 0, 5),
                    'end_time' => substr($group->end_time, 0, 5),
                    'room_id' => $group->room_id,
                    'room' => $group->room?->name,
                    'teacher_id' => $group->teacher_id,
                    'teacher' => $group->teacher?->name,
                ]),
        ]);
    }
}
