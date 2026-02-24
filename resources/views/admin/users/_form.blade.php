{{--
    Partiel formulaire utilisateur
    Variables attendues : $user (null pour create), $tools, $assigned (tableau d'IDs)
--}}

<div class="card">
    <div class="card__body">

        <div class="form-row form-row--2">

            {{-- Nom --}}
            <div class="form-group">
                <label class="form-label" for="name">
                    Nom complet <span class="required">*</span>
                </label>
                <input type="text" id="name" name="name"
                       class="form-control {{ $errors->has('name') ? 'form-control--error' : '' }}"
                       value="{{ old('name', $user->name ?? '') }}"
                       placeholder="Prénom Nom"
                       required maxlength="100">
                @error('name')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Email --}}
            <div class="form-group">
                <label class="form-label" for="email">
                    Email <span class="required">*</span>
                </label>
                <input type="email" id="email" name="email"
                       class="form-control {{ $errors->has('email') ? 'form-control--error' : '' }}"
                       value="{{ old('email', $user->email ?? '') }}"
                       placeholder="utilisateur@exemple.com"
                       required>
                @error('email')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

        </div>

        <div class="form-row form-row--2">

            {{-- Mot de passe --}}
            <div class="form-group">
                <label class="form-label" for="password">
                    Mot de passe
                    @if(isset($user)) <span style="font-size:11px; color:#9CA3AF; font-weight:400;">(laisser vide pour ne pas changer)</span> @else <span class="required">*</span> @endif
                </label>
                <input type="password" id="password" name="password"
                       class="form-control {{ $errors->has('password') ? 'form-control--error' : '' }}"
                       placeholder="Minimum 8 caractères"
                       {{ !isset($user) ? 'required' : '' }}
                       autocomplete="new-password">
                @error('password')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Confirmation --}}
            <div class="form-group">
                <label class="form-label" for="password_confirmation">
                    Confirmer le mot de passe
                    @if(!isset($user)) <span class="required">*</span> @endif
                </label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       class="form-control"
                       placeholder="Répétez le mot de passe"
                       autocomplete="new-password">
            </div>

        </div>

        <div class="form-row form-row--2">

            {{-- Rôle --}}
            <div class="form-group">
                <label class="form-label" for="role">Rôle</label>
                <select id="role" name="role" class="form-control"
                        {{ (isset($user) && $user->id === auth()->id()) ? 'disabled' : '' }}>
                    <option value="user"  {{ old('role', $user->role ?? 'user')  === 'user'  ? 'selected' : '' }}>Utilisateur</option>
                    <option value="admin" {{ old('role', $user->role ?? 'user') === 'admin' ? 'selected' : '' }}>Administrateur</option>
                </select>
                @if(isset($user) && $user->id === auth()->id())
                    <input type="hidden" name="role" value="admin">
                    <span class="form-hint">Vous ne pouvez pas modifier votre propre rôle.</span>
                @endif
            </div>

            {{-- Statut --}}
            <div class="form-group">
                <label class="form-label">Statut du compte</label>
                <div style="padding-top:4px;">
                    <label class="form-toggle">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}
                               {{ (isset($user) && $user->id === auth()->id()) ? 'disabled' : '' }}>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label">Compte actif</span>
                    </label>
                    @if(isset($user) && $user->id === auth()->id())
                        <input type="hidden" name="is_active" value="1">
                    @endif
                </div>
            </div>

        </div>

        {{-- Assignation des outils (uniquement pour les non-admins) --}}
        <div class="form-group" id="tools-section">
            <label class="form-label">Outils accessibles</label>
            <span class="form-hint" style="margin-bottom:8px; display:block;">
                Sélectionnez les outils que cet utilisateur peut voir dans son dashboard.
                Les outils publics sont toujours visibles.
            </span>
            <div style="display:flex; flex-direction:column; gap:8px; max-height:220px; overflow-y:auto;
                        border:1.5px solid #E5E7EB; border-radius:12px; padding:12px;">
                @foreach($tools as $tool)
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                        <input type="checkbox"
                               name="tools[]"
                               value="{{ $tool->id }}"
                               {{ in_array($tool->id, old('tools', $assigned ?? [])) ? 'checked' : '' }}>
                        <span>{{ $tool->name ?? $tool->title }}</span>
                        @if($tool->is_public)
                            <span class="badge badge--active" style="font-size:10px;">Public</span>
                        @endif
                    </label>
                @endforeach
                @if($tools->isEmpty())
                    <p style="color:#9CA3AF; font-size:13px;">Aucun outil disponible.</p>
                @endif
            </div>
        </div>

    </div>

    <div class="card__footer">
        <a href="{{ route('admin.users.index') }}" class="btn btn--secondary">Annuler</a>
        <button type="submit" class="btn btn--primary">
            {{ isset($user) ? 'Enregistrer les modifications' : 'Créer l\'utilisateur' }}
        </button>
    </div>
</div>
