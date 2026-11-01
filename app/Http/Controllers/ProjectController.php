<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $selected = trim((string) $request->query('departement', ''));

        $categories = ProjectCategory::query()
            ->active()
            ->ordered()
            ->withCount(['projects' => fn (Builder $query): Builder => $query->where('is_published', true)])
            ->get();

        $projects = Project::query()
            ->published()
            ->ordered()
            ->with(['category', 'images'])
            ->when($selected !== '', fn (Builder $query): Builder => $query->whereHas(
                'category',
                fn (Builder $query): Builder => $query->where('slug', $selected),
            ))
            ->get();

        return view('pages.projects', [
            'site' => config('site'),
            'categories' => $categories,
            'projects' => $projects,
            'selected' => $selected,
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless($project->is_published, 404);

        $project->load(['category', 'images']);

        $related = Project::query()
            ->published()
            ->whereKeyNot($project->getKey())
            ->when(
                $project->project_category_id !== null,
                fn (Builder $query): Builder => $query->where('project_category_id', $project->project_category_id),
            )
            ->ordered()
            ->with(['category', 'images'])
            ->limit(3)
            ->get();

        return view('pages.project', [
            'site' => config('site'),
            'project' => $project,
            'related' => $related,
        ]);
    }
}
