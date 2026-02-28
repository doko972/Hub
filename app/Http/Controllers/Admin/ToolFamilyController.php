<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ToolFamily;
use Illuminate\Http\Request;

class ToolFamilyController extends Controller
{
    public function index()
    {
        $families = ToolFamily::withCount('tools')->orderBy('sort_order')->orderBy('name')->paginate(20);
        return view('admin.families.index', compact('families'));
    }

    public function create()
    {
        $colors = ToolFamily::availableColors();
        return view('admin.families.create', compact('colors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color'       => ['required', 'in:' . implode(',', array_keys(ToolFamily::availableColors()))],
            'sort_order'  => ['integer', 'min:0'],
            'is_active'   => ['boolean'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
        ]);

        $family = ToolFamily::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color'       => $validated['color'],
            'sort_order'  => $validated['sort_order'] ?? 0,
            'is_active'   => $request->boolean('is_active'),
        ]);

        ActivityLog::record('created', 'Famille', $family->id, $family->name);

        return redirect()->route('admin.families.index')
            ->with('success', 'Famille "' . $family->name . '" créée avec succès.');
    }

    public function show(ToolFamily $family)
    {
        return redirect()->route('admin.families.edit', $family);
    }

    public function edit(ToolFamily $family)
    {
        $colors = ToolFamily::availableColors();
        return view('admin.families.edit', compact('family', 'colors'));
    }

    public function update(Request $request, ToolFamily $family)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color'       => ['required', 'in:' . implode(',', array_keys(ToolFamily::availableColors()))],
            'sort_order'  => ['integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        $family->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color'       => $validated['color'],
            'sort_order'  => $validated['sort_order'] ?? 0,
            'is_active'   => $request->boolean('is_active'),
        ]);

        ActivityLog::record('updated', 'Famille', $family->id, $family->name);

        return redirect()->route('admin.families.index')
            ->with('success', 'Famille "' . $family->name . '" mise à jour.');
    }

    public function destroy(ToolFamily $family)
    {
        $name = $family->name;
        $family->delete();
        ActivityLog::record('deleted', 'Famille', null, $name);

        return redirect()->route('admin.families.index')
            ->with('success', 'Famille supprimée. Les outils associés sont désormais sans famille.');
    }

    public function reorder(Request $request)
    {
        foreach ($request->input('order', []) as $item) {
            ToolFamily::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['ok' => true]);
    }
}
