<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('tools')->orderBy('name')->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $tools = Tool::where('is_active', true)->orderBy('title')->get();
        return view('admin.users.create', compact('tools'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'role'      => ['required', 'in:admin,user'],
            'is_active' => ['boolean'],
            'tools'     => ['nullable', 'array'],
            'tools.*'   => ['exists:tools,id'],
        ], [
            'name.required'      => 'Le nom est obligatoire.',
            'email.required'     => 'L\'email est obligatoire.',
            'email.unique'       => 'Cet email est déjà utilisé.',
            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => $validated['password'],
            'role'      => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (!empty($validated['tools'])) {
            $user->tools()->sync($validated['tools']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur "' . $user->name . '" créé avec succès.');
    }

    public function show(User $user)
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(User $user)
    {
        $tools    = Tool::where('is_active', true)->orderBy('title')->get();
        $assigned = $user->tools()->pluck('tools.id')->toArray();
        return view('admin.users.edit', compact('user', 'tools', 'assigned'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'      => ['required', 'in:admin,user'],
            'is_active' => ['boolean'],
            'tools'     => ['nullable', 'array'],
            'tools.*'   => ['exists:tools,id'],
        ], [
            'email.unique'       => 'Cet email est déjà utilisé.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $updateData = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        // Empêcher de se désactiver soi-même
        if ($user->id === auth()->id()) {
            $updateData['is_active'] = true;
            $updateData['role']      = 'admin';
        }

        $user->update($updateData);
        $user->tools()->sync($validated['tools'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur "' . $user->name . '" mis à jour.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Impossible de supprimer votre propre compte.']);
        }

        $user->tools()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur supprimé.');
    }
}
