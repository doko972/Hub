<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tool;
use App\Models\ToolFamily;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ToolController extends Controller
{
    public function index()
    {
        $tools = Tool::with('family')->orderBy('sort_order')->orderBy('title')->paginate(20);
        return view('admin.tools.index', compact('tools'));
    }

    public function create()
    {
        $colors   = Tool::availableColors();
        $users    = User::where('role', 'user')->orderBy('name')->get();
        $families = ToolFamily::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.tools.create', compact('colors', 'users', 'families'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'url'            => ['required', 'url', 'max:500'],
            'color'          => ['required', 'in:' . implode(',', array_keys(Tool::availableColors()))],
            'tool_family_id' => ['required', 'exists:tool_families,id'],
            'is_active'      => ['boolean'],
            'is_public'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:2048'],
            'users'          => ['nullable', 'array'],
            'users.*'        => ['exists:users,id'],
        ], [
            'title.required'          => 'Le titre est obligatoire.',
            'url.required'            => 'L\'URL est obligatoire.',
            'url.url'                 => 'L\'URL n\'est pas valide.',
            'tool_family_id.required' => 'La famille est obligatoire.',
            'tool_family_id.exists'   => 'La famille sélectionnée est invalide.',
            'image.image'             => 'Le fichier doit être une image.',
            'image.max'               => 'L\'image ne doit pas dépasser 2 Mo.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('tools', 'public');
        }

        $tool = Tool::create([
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? null,
            'url'            => $validated['url'],
            'color'          => $validated['color'],
            'tool_family_id' => $validated['tool_family_id'],
            'is_active'      => $request->boolean('is_active'),
            'is_public'      => $request->boolean('is_public'),
            'sort_order'     => $validated['sort_order'] ?? 0,
            'image_path'     => $imagePath,
        ]);

        if (!$request->boolean('is_public') && !empty($validated['users'])) {
            $tool->users()->sync($validated['users']);
        }

        ActivityLog::record('created', 'Outil', $tool->id, $tool->title);

        return redirect()->route('admin.tools.index')
            ->with('success', 'Outil "' . $tool->title . '" créé avec succès.');
    }

    public function show(Tool $tool)
    {
        return redirect()->route('admin.tools.edit', $tool);
    }

    public function edit(Tool $tool)
    {
        $colors   = Tool::availableColors();
        $users    = User::where('role', 'user')->orderBy('name')->get();
        $assigned = $tool->users()->pluck('users.id')->toArray();
        $families = ToolFamily::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.tools.edit', compact('tool', 'colors', 'users', 'assigned', 'families'));
    }

    public function update(Request $request, Tool $tool)
    {
        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'url'            => ['required', 'url', 'max:500'],
            'color'          => ['required', 'in:' . implode(',', array_keys(Tool::availableColors()))],
            'tool_family_id' => ['required', 'exists:tool_families,id'],
            'is_active'      => ['boolean'],
            'is_public'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:2048'],
            'users'          => ['nullable', 'array'],
            'users.*'        => ['exists:users,id'],
        ], [
            'tool_family_id.required' => 'La famille est obligatoire.',
            'tool_family_id.exists'   => 'La famille sélectionnée est invalide.',
        ]);

        if ($request->hasFile('image')) {
            if ($tool->image_path) {
                Storage::disk('public')->delete($tool->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('tools', 'public');
        }

        $tool->update([
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? null,
            'url'            => $validated['url'],
            'color'          => $validated['color'],
            'tool_family_id' => $validated['tool_family_id'],
            'is_active'      => $request->boolean('is_active'),
            'is_public'      => $request->boolean('is_public'),
            'sort_order'     => $validated['sort_order'] ?? 0,
            'image_path'     => $validated['image_path'] ?? $tool->image_path,
        ]);

        if ($request->boolean('is_public')) {
            $tool->users()->detach();
        } else {
            $tool->users()->sync($validated['users'] ?? []);
        }

        ActivityLog::record('updated', 'Outil', $tool->id, $tool->title);

        return redirect()->route('admin.tools.index')
            ->with('success', 'Outil "' . $tool->title . '" mis à jour.');
    }

    public function destroy(Tool $tool)
    {
        if ($tool->image_path) {
            Storage::disk('public')->delete($tool->image_path);
        }
        $name = $tool->title;
        $tool->users()->detach();
        $tool->delete();
        ActivityLog::record('deleted', 'Outil', null, $name);

        return redirect()->route('admin.tools.index')
            ->with('success', 'Outil supprimé.');
    }

    public function reorder(Request $request)
    {
        foreach ($request->input('order', []) as $item) {
            Tool::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['ok' => true]);
    }
}
