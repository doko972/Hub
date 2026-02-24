{{--
    Partiel formulaire outil
    Variables attendues : $tool (null pour create), $colors, $users, $assigned (tableau d'IDs)
--}}

<div class="card">
    <div class="card__body">

        <div class="form-row form-row--2">

            {{-- Titre --}}
            <div class="form-group">
                <label class="form-label" for="title">
                    Titre <span class="required">*</span>
                </label>
                <input type="text" id="title" name="title"
                       class="form-control {{ $errors->has('title') ? 'form-control--error' : '' }}"
                       value="{{ old('title', $tool->title ?? '') }}"
                       placeholder="Ex : Mon Application Laravel"
                       required maxlength="100">
                @error('title')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Couleur d'accent --}}
            <div class="form-group">
                <label class="form-label" for="color">Couleur d'accent</label>
                <select id="color" name="color" class="form-control">
                    @foreach($colors as $value => $label)
                        <option value="{{ $value }}" {{ old('color', $tool->color ?? 'violet') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>

        {{-- URL --}}
        <div class="form-group">
            <label class="form-label" for="url">
                URL <span class="required">*</span>
            </label>
            <input type="url" id="url" name="url"
                   class="form-control {{ $errors->has('url') ? 'form-control--error' : '' }}"
                   value="{{ old('url', $tool->url ?? '') }}"
                   placeholder="https://mon-outil.exemple.com"
                   required>
            <span class="form-hint">S'ouvre dans un nouvel onglet.</span>
            @error('url')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        {{-- Description --}}
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" name="description"
                      class="form-control"
                      placeholder="Description courte affichée au survol de la vignette…"
                      maxlength="500"
                      rows="3">{{ old('description', $tool->description ?? '') }}</textarea>
            <span class="form-hint">Affiché dans le tooltip au survol. Maximum 500 caractères.</span>
        </div>

        {{-- Image --}}
        <div class="form-group">
            <label class="form-label">Image de la vignette</label>

            <div class="form-upload">
                @if(isset($tool) && $tool->imageUrl())
                    <img id="image-preview"
                         src="{{ $tool->imageUrl() }}"
                         alt="Aperçu"
                         class="form-upload__preview">
                @else
                    <img id="image-preview"
                         src=""
                         alt="Aperçu"
                         class="form-upload__preview"
                         style="display:none;">
                @endif

                <svg class="form-upload__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                     {{ isset($tool) && $tool->imageUrl() ? 'style=display:none' : '' }}>
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <p class="form-upload__text" {{ isset($tool) && $tool->imageUrl() ? 'style=display:none' : '' }}>
                    Cliquez pour sélectionner une image (JPG, PNG, WebP, SVG — max 2 Mo)
                </p>
                <input type="file"
                       name="image"
                       id="image"
                       class="form-upload__input"
                       accept="image/*"
                       data-image-preview="image-preview">
            </div>
            @error('image')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-row form-row--2">

            {{-- Ordre d'affichage --}}
            <div class="form-group">
                <label class="form-label" for="sort_order">Ordre d'affichage</label>
                <input type="number" id="sort_order" name="sort_order"
                       class="form-control"
                       value="{{ old('sort_order', $tool->sort_order ?? 0) }}"
                       min="0">
                <span class="form-hint">Les valeurs basses apparaissent en premier.</span>
            </div>

            {{-- Statut actif --}}
            <div class="form-group">
                <label class="form-label">Options</label>
                <div style="display:flex; flex-direction:column; gap:12px; padding-top:4px;">
                    <label class="form-toggle">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $tool->is_active ?? true) ? 'checked' : '' }}>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label">Outil actif</span>
                    </label>
                    <label class="form-toggle">
                        <input type="checkbox" name="is_public" value="1" id="toggle-public"
                               {{ old('is_public', $tool->is_public ?? false) ? 'checked' : '' }}>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label">Visible par tous</span>
                    </label>
                </div>
            </div>

        </div>

        {{-- Assignation des utilisateurs (masqué si public) --}}
        <div class="form-group" id="users-section"
             style="{{ old('is_public', $tool->is_public ?? false) ? 'display:none;' : '' }}">
            <label class="form-label">Accès utilisateurs</label>
            <span class="form-hint" style="margin-bottom:8px; display:block;">
                Sélectionnez les utilisateurs qui peuvent voir cet outil.
                Les administrateurs y ont toujours accès.
            </span>
            <div style="display:flex; flex-direction:column; gap:8px; max-height:220px; overflow-y:auto; border:1.5px solid #E5E7EB; border-radius:12px; padding:12px;">
                @foreach($users as $user)
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                        <input type="checkbox"
                               name="users[]"
                               value="{{ $user->id }}"
                               {{ in_array($user->id, old('users', $assigned ?? [])) ? 'checked' : '' }}>
                        <span>{{ $user->name }}</span>
                        <span style="color:#9CA3AF; font-size:12px;">{{ $user->email }}</span>
                    </label>
                @endforeach
                @if($users->isEmpty())
                    <p style="color:#9CA3AF; font-size:13px;">Aucun utilisateur disponible.</p>
                @endif
            </div>
        </div>

    </div>

    <div class="card__footer">
        <a href="{{ route('admin.tools.index') }}" class="btn btn--secondary">Annuler</a>
        <button type="submit" class="btn btn--primary">
            {{ isset($tool) ? 'Enregistrer les modifications' : 'Créer l\'outil' }}
        </button>
    </div>
</div>

{{-- Toggle "public" → masque la section utilisateurs --}}
<script>
    document.getElementById('toggle-public')?.addEventListener('change', function () {
        const section = document.getElementById('users-section');
        if (section) {
            section.style.display = this.checked ? 'none' : '';
        }
    });
</script>
