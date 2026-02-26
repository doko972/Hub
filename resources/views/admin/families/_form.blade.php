{{--
    Partiel formulaire famille
    Variables attendues : $family (null pour create), $colors
--}}

<div class="card">
    <div class="card__body">

        <div class="form-row form-row--2">

            {{-- Nom --}}
            <div class="form-group">
                <label class="form-label" for="name">
                    Nom <span class="required">*</span>
                </label>
                <input type="text" id="name" name="name"
                       class="form-control {{ $errors->has('name') ? 'form-control--error' : '' }}"
                       value="{{ old('name', $family->name ?? '') }}"
                       placeholder="Ex : Développement, Infrastructure…"
                       required maxlength="100">
                @error('name')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Couleur d'accent --}}
            <div class="form-group">
                <label class="form-label" for="color">Couleur d'accent</label>
                <select id="color" name="color" class="form-control">
                    @foreach($colors as $value => $label)
                        <option value="{{ $value }}" {{ old('color', $family->color ?? 'violet') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>

        {{-- Description --}}
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" name="description"
                      class="form-control"
                      placeholder="Description courte de cette famille d'outils…"
                      maxlength="500"
                      rows="3">{{ old('description', $family->description ?? '') }}</textarea>
            <span class="form-hint">Maximum 500 caractères.</span>
        </div>

        <div class="form-row form-row--2">

            {{-- Ordre d'affichage --}}
            <div class="form-group">
                <label class="form-label" for="sort_order">Ordre d'affichage</label>
                <input type="number" id="sort_order" name="sort_order"
                       class="form-control"
                       value="{{ old('sort_order', $family->sort_order ?? 0) }}"
                       min="0">
                <span class="form-hint">Les valeurs basses apparaissent en premier.</span>
            </div>

            {{-- Statut actif --}}
            <div class="form-group">
                <label class="form-label">Options</label>
                <div style="padding-top:4px;">
                    <label class="form-toggle">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $family->is_active ?? true) ? 'checked' : '' }}>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label">Famille active</span>
                    </label>
                </div>
            </div>

        </div>

    </div>

    <div class="card__footer">
        <a href="{{ route('admin.families.index') }}" class="btn btn--secondary">Annuler</a>
        <button type="submit" class="btn btn--primary">
            {{ isset($family) ? 'Enregistrer les modifications' : 'Créer la famille' }}
        </button>
    </div>
</div>
